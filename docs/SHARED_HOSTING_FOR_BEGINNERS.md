# Shared hosting checklist (for non-techies)

Plain English. Tick boxes as you go.

**Watch first (≈2.5 min):** [portfolio-os-shared-hosting-beginners.mp4](videos/portfolio-os-shared-hosting-beginners.mp4) — narrated walkthrough of this checklist.

**Honest start:** you will need **one tech-friendly friend for about an hour** (Part A). They prepare the upload files on a normal computer. **You** do Hostinger (Part B) and day-to-day use (Part C).

You need a Hostinger (or similar) account with **PHP 8.3+**, **MySQL**, **FTP**, and **cron**. No Redis. No “Node on the server.”

---

## Part A — Ask a tech friend (once)

Give them this list and the link to the full guide: [DEPLOYMENT.md](../DEPLOYMENT.md).

- [ ] On their laptop: install PHP 8.3+, Composer, and Node (only on the laptop).
- [ ] Download Portfolio OS from GitHub and run the package script so you get two files:
  - `app.zip`
  - `public.zip`
- [ ] Generate a secret **APP_KEY** and a long random **OPS_TOKEN** (32+ characters). Write both down somewhere safe (password manager).
- [ ] Help you fill in the server settings file (Part B) and create the first admin login.
- [ ] After install works: help you **erase OPS_TOKEN** so that “maintenance door” is locked.

You do **not** need to understand those steps. You only need the two zip files and the secrets written down.

---

## Part B — You + Hostinger (about 30–60 minutes)

### 1. Database

- [ ] In hPanel → **Databases** → create a MySQL database and user.
- [ ] Write down: database name, username, password, host (often `localhost`).

### 2. Upload the app

- [ ] Connect with **FileZilla** (or Hostinger File Manager upload).
- [ ] Create a folder named `laravel_app` next to `public_html` (same level, not inside it).
- [ ] Extract **`app.zip` into `laravel_app/`**  
  You should see things like `artisan` and `vendor` inside `laravel_app`.
- [ ] Extract **`public.zip` into `public_html/`**  
  You should see `index.php` and a `build` folder inside `public_html`.
- [ ] Delete the zip files from the server when done.

### 3. Settings file (the important one)

- [ ] In `laravel_app`, create a file named `.env` (your friend can paste from `.env.production.example`).
- [ ] Fill in at least:
  - Your real website address (`APP_URL=https://yoursite.com`)
  - `APP_DEBUG=false`
  - The **APP_KEY** from Part A
  - Database name / user / password
  - Your currency settings (**choose once** — don’t change later after you enter money)
  - A temporary strong password in `ADMIN_PASSWORD=...` (this becomes your first login)
  - The temporary **OPS_TOKEN** from Part A
- [ ] Make `laravel_app/storage` and `laravel_app/bootstrap/cache` writable (File Manager → permissions, often 775).

### 4. Turn the database on (browser links)

Replace `yoursite.com` and paste your real OPS token. Open each link once:

- [ ] `https://yoursite.com/_ops/migrate?token=YOUR_OPS_TOKEN`
- [ ] Ask your friend to run the **seed** once (creates roles + your admin).  
  On Hostinger this usually needs SSH or their help:  
  `cd ~/laravel_app && php artisan db:seed --force`  
  Then sign in as `admin@example.com` with the `ADMIN_PASSWORD` you set.
- [ ] `https://yoursite.com/_ops/storage-link?token=YOUR_OPS_TOKEN`
- [ ] `https://yoursite.com/_ops/optimize?token=YOUR_OPS_TOKEN`
- [ ] Edit `.env` and **clear OPS_TOKEN** (leave it empty). Save.
- [ ] Optional: open the cache-clear ops link once more, then lock the door again.

### 5. Cron (so reminders and background jobs run)

In hPanel → Cron jobs, add two (ask support for the exact PHP path if unsure):

- [ ] Every minute:  
  `cd /home/YOUR_USER/laravel_app && /usr/bin/php artisan schedule:run`
- [ ] Every 5 minutes:  
  `cd /home/YOUR_USER/laravel_app && /usr/bin/php artisan queue:work --stop-when-empty --max-time=280`

### 6. Smoke check

- [ ] Open your site → login page looks styled (not plain broken HTML).
- [ ] Sign in as admin.
- [ ] Change the admin password under your profile / users.
- [ ] Create one test project. Upload a small file. It should stick.
- [ ] Never share `.env`, APP_KEY, or OPS_TOKEN in chat or email.

---

## Part C — How to use it (first week)

Do this order. Don’t start with money day one.

1. **Settings** — org name, timezone, currency symbol, “late after” hour.
2. **Users** — add partners, supervisors, writers. Give people **roles** (someone can be partner *and* supervisor).
3. **Projects** — add each website. Set **ownership %** so they add up to **100%**.
4. **Credentials** — put hosting / CMS / domain logins in the vault (not a shared Google Doc).
5. **Task templates** — checklist you want on every new site.
6. Daily work:
   - Writers: sign in (that’s check-in) → Tasks / Articles / Links → submit.
   - Supervisors: **Approvals** → approve or reject.
7. Money (accountant / admin / partner as allowed):
   - Enter **Revenue** for the month (or CSV).
   - Enter **Expenses** (mark shared costs when they span sites).
   - Check **P&L**.
   - When ready: **Distributions** → draft → approve.  
     Approved runs are **locked**. Mistakes = new correcting entry, not an edit.
8. **Partners** — capital in/out and statements when you pay people out.

Keyboard tip on Approvals: `j` / `k` move, `a` approve, `r` reject.

More detail by role: [USER_GUIDE.md](USER_GUIDE.md).

---

## Don’ts (read once)

- Don’t put Redis or “sync queue forever” in settings — use **database** or **file**.
- Don’t change `MONEY_BASE_EXPONENT` after real money is entered.
- Don’t leave `OPS_TOKEN` filled in after deploy.
- Don’t overwrite `.env` when you update the app later.
- Don’t expect the server to run `npm` — friend rebuilds zips on their laptop, you re-upload.

---

## When something breaks

| Symptom | Likely fix |
|--------|------------|
| White / 500 page | Check `.env` DB details; ask friend to peek at `laravel_app/storage/logs` |
| Login page unstyled / login does nothing | Re-upload `public.zip`; ask friend to hit `/_ops/livewire-assets` with a temporary OPS token |
| Can’t upload files | Permissions on `laravel_app/storage` |
| Emails never arrive | Fill SMTP in `.env`; wait for the 5‑minute queue cron |
| “Who is owed what” fight returns | Ownership not at 100%, or distributions never approved for that month |

Full technical deploy notes: [DEPLOYMENT.md](../DEPLOYMENT.md).  
Local try-before-you-buy (friend’s laptop): [INSTALL.md](INSTALL.md).
