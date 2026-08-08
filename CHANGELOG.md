# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security

- **Expense receipt downloads are project-scoped.** The route previously only
  checked `expenses.view` / `expenses.manage` globally, so a partner with view
  permission but no access to Project B could still download B’s receipt by
  requesting `/money/expenses/{id}/receipt` directly. Access now uses the same
  `Expense::accessibleBy()` scope as the expenses list, P&L and AI expense
  spikes report. Reported by Muneeb.

## [1.0.1]

A security and performance pass over the whole app ahead of announcing it publicly.
No schema changes and no data migration; existing installations upgrade by
uploading the new code and rebuilt assets.

### Security

- **Decrypted credential secrets no longer persist in Livewire component state.**
  Revealing a secret stored the plaintext in a public property, which Livewire
  serialises into `wire:snapshot` and echoes back to the browser on every later
  interaction with that page. Only the revealed credential's id is kept now, and
  the secret is decrypted per render. Any cross-site scripting on that page, or
  anything logging request payloads, would previously have seen the plaintext.
- **Closed cross-project reads through client-controlled component state.** A
  partner could change `userId` on their own statement to read another partner's
  ledger, the approval queue would render work items from projects the viewer was
  not assigned to, and the links screen exposed budget totals for unauthorised
  projects. Those properties are `#[Locked]` and the queries are scoped to the
  caller's project assignments.
- **Staff can no longer reassign tasks without `tasks.assign`**, including through
  the bulk status bar, which previously aborted the batch instead of skipping
  unauthorised rows.
- **Exported CSVs neutralise spreadsheet formulas.** A description beginning `=`,
  `+`, `-` or `@` executed on open in Excel and Sheets. Negative amounts still
  export as numbers.
- **The ops route refuses a token shorter than 32 characters** by returning 404,
  and its responses are sent `no-store`, `no-referrer` and `noindex` so the token
  in the URL is not carried into caches, crawlers or Referer headers.
- **The ops cache-clear action no longer rewrites application layouts**, and the
  Livewire asset recovery action discards a download that is not plausibly the
  asset rather than saving an error page as executable JavaScript.
- **Livewire is served from the application instead of a third-party CDN**, so no
  runtime dependency on jsDelivr and no supply-chain exposure through it.

### Fixed

- **Distribution shares add up exactly.** Rounding each owner's share half-up
  independently could distribute more than the profit — 101 paisa split 50/50 paid
  out 102. The remainder now goes to the last owner and the lines always sum to the
  distributable amount.
- **Concurrent distribution approvals can no longer double-credit the partner
  ledger**, and an approved run keeps the ownership snapshot taken at draft time
  rather than re-capturing it, so the credited amounts always match the snapshot
  they were computed from.
- **Auto-expenses from article and link approval are idempotent** under
  double-submit and concurrent requests, and a recurring expense that was
  deliberately deleted is no longer resurrected by the next cron run.
- **Attachments can be downloaded.** Task evidence, project files and expense
  receipts could be uploaded and deleted but never read back — there was no link
  and no route, so an approver could not open the evidence they were approving
  against. Downloads are authorised against the record the file hangs off, and
  everything is served as an attachment with an opaque content type so an uploaded
  `.html` or `.svg` cannot execute on the app's origin.
- Shared-expense allocations are no longer rewritten on every page view.
- Currency column defaults no longer ship as one organisation's currency
  (new migration; the original migrations are untouched).

### Performance

- The sixteen main screens went from roughly 3,100 database queries to 170, with no
  change in what they display. The causes were per-request permission lookups,
  settings reads hitting the database cache store repeatedly, per-row aggregate
  helpers called from Blade, a P&L report that ran three queries per project, and
  shared-expense allocations rebuilt on read.
- Lazy loading now throws outside production, so a missing eager load fails in
  development and CI instead of silently costing a query per row.

### Changed

- Base currency, its minor-unit exponent and its display symbol are now driven by
  `config/money.php` and the Settings screen instead of being fixed to one currency.
  `App\Support\Currency` resolves them, with a saved setting taking precedence over
  the environment default.
- Display timezone falls back to `APP_DISPLAY_TIMEZONE` (default `UTC`) rather than a
  hardcoded zone.
- Demo seeders use generic, obviously fake data throughout.
- The seeder now generates and prints a random admin password outside `local` and
  `testing`, instead of silently seeding a known one. `ADMIN_PASSWORD` overrides it.
- The demo seeders no longer run outside `local` and `testing`, so `migrate --seed`
  can no longer create fake projects or financial records on a real installation.
  `SEED_DEMO_DATA` forces the decision either way.

### Added

- Open-source project files: MIT `LICENSE`, rewritten `README.md`, `CONTRIBUTING.md`,
  `CODE_OF_CONDUCT.md`, `SECURITY.md`, this changelog, GitHub Actions CI, and issue
  and pull request templates.
- SIL Open Font License notices for the bundled Geist, Geist Mono and Instrument Sans
  subsets (`resources/fonts/LICENSE`).
- `tests/Feature/SmokeRenderTest.php` renders every parameterless page and the main
  detail pages against the demo dataset.
- `tests/Feature/CurrencyTest.php` covers zero-, two- and three-decimal currencies,
  frozen-rate conversion across differing exponents, and settings-over-config
  precedence.
- Regression tests for each security and money fix above: vault snapshot leakage,
  cross-project reads, CSV formula escaping, ops route hardening, distribution
  rounding and concurrency, and expense idempotency.

### Documentation

- README rewritten around what the app does, with setup moved to
  `docs/INSTALL.md`.
- `SECURITY.md` states plainly that two-factor authentication is columns and a
  settings toggle with no enrolment and no login challenge. The settings screen
  says the same, so the toggle can no longer be mistaken for a control.
- `DEPLOYMENT.md` documents the 32-character ops token floor, since a shorter
  token now makes the route 404.

## [1.0.0]

First complete release. Built as seven milestones, each shipped and verified before
the next began.

### Milestone 1 — Foundation

- Laravel application skeleton, authentication, password reset.
- Many-to-many roles and permissions: 49 granular permissions across 5 seeded roles
  (admin, partner, supervisor, staff, accountant). Effective permissions are the union
  of a user's roles; no `role` column and no role switcher.
- User administration, activation state, and an app settings store.
- Application shell: navigation rail, top bar, command palette, mobile drawer and
  thumb bar.
- Design system: tokens, dark mode, density modes and a Blade component library.
- Seeders for roles, permissions, settings and task templates.

### Milestone 2 — Projects & credentials

- Project portfolio CRUD with status, CMS, niche, monetisation state, acquisition cost.
- Per-project ownership shares validated to total 100%, and team assignment that scopes
  staff visibility.
- Encrypted credential vault, with reveal gated by its own permission and every reveal
  written to an audit log.
- Scheduled credential expiry alerts with configurable warning windows.
- Portfolio dashboard.

### Milestone 3 — Work

- Tasks with checklists, priorities, due dates, attachments, comments and evidence
  on submit.
- Recurring task generation and reusable task templates.
- Article pipeline from brief to published, with word-count targets and per-article cost.
- Link building log with per-project monthly budgets.
- A single keyboard-driven approval queue across tasks, articles and links, optionally
  raising the matching expense on approval.

### Milestone 4 — People

- Attendance derived from the first login of the day, with a configurable late hour,
  plus supervisor leave and holiday marking.
- Login history with IP and user agent.
- Daily work logs.
- Monthly scorecards aggregating output per person, costed against mixed pay rates
  (monthly salary and/or per-article, per-link, per-task).

### Milestone 5 — Money

- Revenue per project per month, entered directly or imported from CSV, with the FX
  rate frozen on each row so historical figures never re-convert.
- Direct and shared expenses, receipt uploads, recurring expense templates, paid state.
- Monthly profit and loss with shared costs allocated across projects in proportion
  to revenue.
- Manual partner distributions computed from ownership shares; approving a run locks it
  permanently, and corrections are new adjusting entries.
- Partner ledger and per-partner statements for capital, withdrawals and distribution
  credits.
- All monetary values stored as integer minor units.

### Milestone 6 — Deployment

- FTP packaging scripts for shared hosting (`deploy/package.sh`, `deploy/package.ps1`)
  producing an app archive and a public archive.
- Dual-path `public/index.php` that works both in the split shared-hosting layout and
  locally.
- Token-gated `/_ops/{action}` maintenance route for hosts without SSH, disabled
  entirely when no token is set.
- `storage:link` fallback that copies when `symlink()` is unavailable, plus a public
  media fallback route.
- Pure-PHP database backup command requiring no `mysqldump` or shell access.
- `DEPLOYMENT.md`.

### Milestone 7 — AI assistant

- Optional natural-language "ask your data" assistant and drafted monthly summaries,
  completely hidden when no provider key is configured.
- Questions map onto a fixed whitelist of read-only report methods that re-apply the
  caller's permissions and project scope; a model never generates executed SQL.
- Credentials, passwords, keys and bank details are stripped before any prompt is built.
- Monthly spend cap, per-request token and cost logging, and response caching.

[Unreleased]: https://github.com/tnandla/portfolio-os/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/tnandla/portfolio-os/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/tnandla/portfolio-os/releases/tag/v1.0.0
