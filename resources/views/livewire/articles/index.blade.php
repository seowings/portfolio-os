<div class="space-y-6">
    <x-page-header
        title="Articles"
        subtitle="Briefs, drafts, keyword lock, and writer cost → expense on approve."
        :breadcrumbs="[['label' => 'Work'], ['label' => 'Articles']]"
    >
        @if ($canCreate)
            <x-slot:actions>
                <x-button variant="secondary" icon="arrow-up" wire:click="openImport">Bulk import</x-button>
                <x-button icon="plus" wire:click="create">New article</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <x-filter-bar target="search,projectFilter,statusFilter">
        <x-input
            class="min-w-[12rem] flex-1 sm:max-w-xs"
            size="sm"
            icon="search"
            type="search"
            data-page-search
            wire:model.live.debounce.300ms="search"
            placeholder="Search title or keyword…"
            aria-label="Search articles"
        />
        <x-select size="sm" class="w-auto" wire:model.live="projectFilter" placeholder="All projects" aria-label="Filter by project">
            @foreach ($projects as $project)
                <option value="{{ $project->id }}">{{ $project->domain }}</option>
            @endforeach
        </x-select>
        <x-select size="sm" class="w-auto" wire:model.live="statusFilter" placeholder="All statuses" aria-label="Filter by status">
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </x-select>

        <x-slot:trailing>{{ $articles->total() }} articles</x-slot:trailing>
    </x-filter-bar>

    <div wire:loading.delay.long.flex wire:target="search,projectFilter,statusFilter" class="hidden">
        <x-skeleton variant="table" class="w-full" :rows="6" :cols="5" />
    </div>

    @if ($articles->isEmpty())
        <x-empty-state
            icon="articles"
            title="No articles in this view"
            description="Start a brief with a unique target keyword per project. Approving an article posts the writer cost as an expense."
        >
            @if ($canCreate)
                <x-button icon="plus" wire:click="create">New article</x-button>
            @endif
        </x-empty-state>
    @else
        <div wire:loading.class="opacity-60" wire:target="search,projectFilter,statusFilter">
            <x-table :headers="[
                'Article',
                'Project',
                'Writer',
                'Status',
                ['label' => 'Words', 'align' => 'right'],
                ['label' => \App\Support\Currency::code(), 'align' => 'right'],
                ['label' => 'Updated', 'align' => 'right'],
                ['label' => 'Actions', 'sr' => true, 'align' => 'right', 'width' => 'relative'],
            ]">
                @foreach ($articles as $article)
                    <x-table.row wire:key="article-{{ $article->id }}">
                        <x-table.cell class="min-w-[12rem]">
                            <p class="font-medium text-ink">{{ $article->title }}</p>
                            <p class="mt-0.5 font-mono text-[11px] text-muted">{{ $article->target_keyword }}</p>
                            @if ($article->revision_notes)
                                <p class="mt-1 text-xs text-danger">Revision: {{ $article->revision_notes }}</p>
                            @endif
                        </x-table.cell>
                        <x-table.cell muted nowrap>{{ $article->project?->domain }}</x-table.cell>
                        <x-table.cell>
                            @if ($article->writer)
                                <span class="inline-flex items-center gap-2 whitespace-nowrap">
                                    <x-avatar :name="$article->writer->name" size="xs" />
                                    {{ $article->writer->name }}
                                </span>
                            @else
                                <span class="text-faint">No writer</span>
                            @endif
                        </x-table.cell>
                        <x-table.cell>
                            <div class="flex flex-col items-start gap-1">
                                <x-badge :tone="match($article->status->value) {
                                    'approved' => 'success',
                                    'revision_requested' => 'danger',
                                    'draft_submitted' => 'warn',
                                    default => 'accent',
                                }">{{ $article->status->label() }}</x-badge>
                                @if ($article->expense_id)
                                    <x-badge tone="success" size="sm">Expense #{{ $article->expense_id }}</x-badge>
                                @endif
                            </div>
                        </x-table.cell>
                        <x-table.cell numeric muted nowrap>
                            {{ $article->word_count_actual ?? '—' }} / {{ $article->word_count_target ?? '—' }}
                        </x-table.cell>
                        <x-table.cell numeric>
                            <x-money :paisa="$article->cost_paisa" />
                        </x-table.cell>
                        <x-table.cell numeric muted nowrap>
                            {{ $article->updated_date?->format('M j, Y') ?? '—' }}
                        </x-table.cell>
                        <x-table.cell align="right" nowrap>
                            <div class="flex items-center justify-end gap-1">
                                @can('update', $article)
                                    <x-tooltip text="Edit article">
                                        <x-button size="sm" variant="ghost" square icon="pencil" wire:click="edit({{ $article->id }})" aria-label="Edit {{ $article->title }}" />
                                    </x-tooltip>
                                @endcan
                                @can('delete', $article)
                                    <x-tooltip text="Delete article">
                                        <x-button size="sm" variant="danger-ghost" square icon="trash" wire:click="confirmDelete({{ $article->id }})" aria-label="Delete {{ $article->title }}" />
                                    </x-tooltip>
                                @endcan
                                @can('submit', $article)
                                    @if (in_array($article->status->value, ['assigned', 'revision_requested', 'brief'], true))
                                        <x-button size="sm" variant="secondary" wire:click="submitDraft({{ $article->id }})">Submit draft</x-button>
                                    @endif
                                @endcan
                                @can('approve', $article)
                                    @if ($article->status->value === 'draft_submitted')
                                        <x-button size="sm" icon="check" wire:click="approve({{ $article->id }})">Approve</x-button>
                                        <x-tooltip text="Request revision">
                                            <x-button size="sm" variant="danger-ghost" square icon="refresh" wire:click="openRevision({{ $article->id }})" aria-label="Request revision on {{ $article->title }}" />
                                        </x-tooltip>
                                    @endif
                                @endcan
                            </div>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>

        <div>{{ $articles->links() }}</div>
    @endif

    <x-modal
        :show="$showForm"
        :title="$editingId ? 'Edit article' : 'New article'"
        subtitle="Keywords are locked to one article per project."
        close="cancel"
        size="lg"
    >
        <form id="article-form" wire:submit="save" class="grid gap-4 sm:grid-cols-2">
            <x-select
                label="Project"
                wire:model="project_id"
                placeholder="Select…"
                :error="$errors->first('project_id')"
                class="sm:col-span-2"
                required
            >
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}">{{ $project->domain }}</option>
                @endforeach
            </x-select>

            <x-input label="Title" wire:model="title" :error="$errors->first('title')" required />
            <x-input
                label="Target keyword"
                wire:model="target_keyword"
                :error="$errors->first('target_keyword')"
                hint="Unique per project."
                required
            />
            <x-input label="Word count target" type="number" min="0" wire:model="word_count_target" :error="$errors->first('word_count_target')" />
            <x-input label="Word count actual" type="number" min="0" wire:model="word_count_actual" :error="$errors->first('word_count_actual')" />

            <x-select label="Writer" wire:model="writer_id" placeholder="Unassigned" :error="$errors->first('writer_id')">
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </x-select>

            <x-input
                label="Writer cost"
                type="number"
                step="0.01"
                min="0"
                wire:model="cost"
                :error="$errors->first('cost')"
                :suffix="\App\Support\Currency::code()"
            />

            <div class="sm:col-span-2">
                <x-button type="button" variant="link" size="sm" wire:click="$toggle('showMore')">
                    {{ $showMore ? 'Hide' : 'More' }} details
                </x-button>
            </div>

            @if ($showMore)
                <x-input label="Meta title" wire:model="meta_title" :error="$errors->first('meta_title')" />
                <x-input label="Published URL" wire:model="published_url" :error="$errors->first('published_url')" />
                <x-textarea label="Meta description" wire:model="meta_description" rows="2" :error="$errors->first('meta_description')" class="sm:col-span-2" />
                <x-input label="Publish date" type="date" wire:model="publish_date" :error="$errors->first('publish_date')" />
                <x-input label="Last updated" type="date" wire:model="updated_date" :error="$errors->first('updated_date')" />
            @endif
        </form>

        <x-slot:footer>
            <x-button variant="ghost" wire:click="cancel">Cancel</x-button>
            <x-button type="submit" form="article-form" target="save">Save article</x-button>
        </x-slot:footer>
    </x-modal>

    <x-modal :show="$showRevision" title="Request revision" subtitle="The writer sees this note on the article." close="cancel" size="sm">
        <x-textarea
            label="What needs to change"
            wire:model="revision_notes"
            rows="4"
            placeholder="Reason required…"
            :error="$errors->first('revision_notes')"
            required
        />

        <x-slot:footer>
            <x-button variant="ghost" wire:click="cancel">Cancel</x-button>
            <x-button variant="danger" wire:click="requestRevision">Send</x-button>
        </x-slot:footer>
    </x-modal>

    <x-modal :show="$showImport" title="Bulk import articles" subtitle="Upload a CSV file to import articles." close="cancelImport" size="sm">
        <form id="import-form" wire:submit="importCsv">
            <input type="file" wire:model="importFile" accept=".csv" required class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
            <div class="mt-4">
                <a href="/articles_import_template.csv" download class="text-sm text-indigo-600 hover:underline">Download CSV template</a>
            </div>
            
            <div wire:loading wire:target="importFile" class="mt-2 text-sm text-slate-500">
                Uploading...
            </div>
            @error('importFile') <span class="mt-2 text-sm text-red-600">{{ $message }}</span> @enderror
        </form>

        <x-slot:footer>
            <x-button variant="ghost" wire:click="cancelImport">Cancel</x-button>
            <x-button type="submit" form="import-form" target="importCsv" wire:loading.attr="disabled">Import</x-button>
        </x-slot:footer>
    </x-modal>

    <x-modal :show="$showDelete" title="Delete article" subtitle="Are you sure you want to delete this article? This action cannot be undone." close="cancelDelete" size="sm">
        <x-slot:footer>
            <x-button variant="ghost" wire:click="cancelDelete">Cancel</x-button>
            <x-button variant="danger" wire:click="delete">Delete</x-button>
        </x-slot:footer>
    </x-modal>
</div>
