<?php

namespace App\Livewire\Articles;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Project;
use App\Models\User;
use App\Services\ArticleWorkflowService;
use App\Support\Money;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Articles')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $projectFilter = '';

    public bool $showForm = false;

    public bool $showImport = false;

    public $importFile;

    public bool $showDelete = false;

    public ?int $deletingId = null;

    public bool $showMore = false;

    public ?int $editingId = null;

    public string $project_id = '';

    public string $title = '';

    public string $target_keyword = '';

    public string $word_count_target = '';

    public string $word_count_actual = '';

    public string $writer_id = '';

    public string $cost = '0';

    public string $meta_title = '';

    public string $meta_description = '';

    public string $published_url = '';

    public string $publish_date = '';

    public string $updated_date = '';

    public string $revision_notes = '';

    public bool $showRevision = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Article::class);
        if ($this->projectFilter === '' && session()->has('articles.last_project_id')) {
            $this->projectFilter = (string) session('articles.last_project_id');
        }

        // Quick-create deep link (?new=1) opens the form straight away
        if (request()->boolean('new') && Auth::user()?->hasPermission('articles.create')) {
            $this->create();
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', Article::class);
        $this->resetForm();
        $this->project_id = $this->projectFilter !== '' ? $this->projectFilter : (string) (session('articles.last_project_id') ?? '');
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $article = Article::query()->findOrFail($id);
        $this->authorize('update', $article);

        $this->editingId = $article->id;
        $this->project_id = (string) $article->project_id;
        $this->title = $article->title;
        $this->target_keyword = $article->target_keyword;
        $this->word_count_target = (string) ($article->word_count_target ?? '');
        $this->word_count_actual = (string) ($article->word_count_actual ?? '');
        $this->writer_id = (string) ($article->writer_id ?? '');
        $this->cost = Money::fromMinor((int) $article->cost_paisa);
        $this->meta_title = (string) $article->meta_title;
        $this->meta_description = (string) $article->meta_description;
        $this->published_url = (string) $article->published_url;
        $this->publish_date = $article->publish_date?->format('Y-m-d') ?? '';
        $this->updated_date = $article->updated_date?->format('Y-m-d') ?? '';
        $this->showForm = true;
        $this->showMore = filled($article->meta_title) || filled($article->published_url);
    }

    public function save(ArticleWorkflowService $workflow): void
    {
        if ($this->editingId) {
            $article = Article::query()->findOrFail($this->editingId);
            $this->authorize('update', $article);
        } else {
            $this->authorize('create', Article::class);
        }

        $validated = $this->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'target_keyword' => ['required', 'string', 'max:255'],
            'word_count_target' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'word_count_actual' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'writer_id' => ['nullable', 'integer', 'exists:users,id'],
            'cost' => ['required', 'numeric', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'published_url' => ['nullable', 'string', 'max:2048'],
            'publish_date' => ['nullable', 'date'],
            'updated_date' => ['nullable', 'date'],
        ]);

        $project = Project::query()->findOrFail($validated['project_id']);
        abort_unless(Auth::user()->canAccessProject($project), 403);

        $data = [
            'project_id' => (int) $validated['project_id'],
            'title' => $validated['title'],
            'target_keyword' => trim($validated['target_keyword']),
            'word_count_target' => $validated['word_count_target'] !== null && $validated['word_count_target'] !== ''
                ? (int) $validated['word_count_target'] : null,
            'word_count_actual' => $validated['word_count_actual'] !== null && $validated['word_count_actual'] !== ''
                ? (int) $validated['word_count_actual'] : null,
            'writer_id' => $validated['writer_id'] ?: null,
            'cost_paisa' => Money::toMinor($validated['cost']),
            'meta_title' => $validated['meta_title'] ?: null,
            'meta_description' => $validated['meta_description'] ?: null,
            'published_url' => $validated['published_url'] ?: null,
            'publish_date' => $validated['publish_date'] ?: null,
            'updated_date' => $validated['updated_date'] ?: null,
        ];

        try {
            if ($this->editingId) {
                $workflow->update(Article::query()->findOrFail($this->editingId), $data);
            } else {
                $workflow->create($data, Auth::user());
            }
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key, $message);
                }
            }

            return;
        }

        session(['articles.last_project_id' => $validated['project_id']]);
        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', message: 'Article saved.', tone: 'success');
    }

    public function openImport(): void
    {
        $this->authorize('create', Article::class);
        $this->showImport = true;
    }

    public function cancelImport(): void
    {
        $this->showImport = false;
        $this->importFile = null;
    }

    public function importCsv(): void
    {
        $this->authorize('create', Article::class);

        $this->validate([
            'importFile' => 'required|file|max:5120', // 5MB max
        ]);

        $filePath = $this->importFile->getRealPath();

        $exitCode = Artisan::call('import:articles', [
            'file' => $filePath,
        ]);

        if ($exitCode === 0) {
            $this->dispatch('toast', message: 'Articles imported successfully.', tone: 'success');
            $this->showImport = false;
            $this->importFile = null;
        } else {
            $this->dispatch('toast', message: 'Import failed. '.Artisan::output(), tone: 'danger');
        }
    }

    public function submitDraft(int $id, ArticleWorkflowService $workflow): void
    {
        $article = Article::query()->findOrFail($id);
        $this->authorize('submit', $article);

        try {
            $workflow->submitDraft($article);
            $this->dispatch('toast', message: 'Draft submitted for approval.', tone: 'success');
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first(), tone: 'danger');
        }
    }

    public function approve(int $id, ArticleWorkflowService $workflow): void
    {
        $article = Article::query()->findOrFail($id);
        $this->authorize('approve', $article);

        try {
            $workflow->approve($article, Auth::user());
            $this->dispatch('toast', message: 'Article approved. Writer cost posted as expense.', tone: 'success');
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first(), tone: 'danger');
        }
    }

    public function openRevision(int $id): void
    {
        $article = Article::query()->findOrFail($id);
        $this->authorize('approve', $article);
        $this->editingId = $id;
        $this->revision_notes = '';
        $this->showRevision = true;
    }

    public function requestRevision(ArticleWorkflowService $workflow): void
    {
        $article = Article::query()->findOrFail($this->editingId);
        $this->authorize('approve', $article);

        try {
            $workflow->requestRevision($article, $this->revision_notes);
            $this->showRevision = false;
            $this->editingId = null;
            $this->dispatch('toast', message: 'Revision requested.', tone: 'success');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key, $message);
                }
            }
        }
    }

    public function confirmDelete(int $id): void
    {
        $article = Article::query()->findOrFail($id);
        $this->authorize('delete', $article);
        $this->deletingId = $id;
        $this->showDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->showDelete = false;
        $this->deletingId = null;
    }

    public function delete(): void
    {
        if (! $this->deletingId) {
            return;
        }
        $article = Article::query()->findOrFail($this->deletingId);
        $this->authorize('delete', $article);
        $article->delete();

        $this->dispatch('toast', message: 'Article deleted successfully.', tone: 'success');
        $this->showDelete = false;
        $this->deletingId = null;
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->showRevision = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->project_id = '';
        $this->title = '';
        $this->target_keyword = '';
        $this->word_count_target = '';
        $this->word_count_actual = '';
        $this->writer_id = '';
        $this->cost = '0';
        $this->meta_title = '';
        $this->meta_description = '';
        $this->published_url = '';
        $this->publish_date = '';
        $this->updated_date = '';
        $this->showMore = false;
        $this->resetValidation();
    }

    public function render()
    {
        $user = Auth::user();

        $articles = Article::query()
            ->with(['project', 'writer', 'expense'])
            ->accessibleBy($user)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('title', 'like', $term)
                        ->orWhere('target_keyword', 'like', $term);
                });
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->projectFilter !== '', fn ($q) => $q->where('project_id', $this->projectFilter))
            ->orderByRaw("CASE status WHEN 'draft_submitted' THEN 0 WHEN 'revision_requested' THEN 1 WHEN 'assigned' THEN 2 WHEN 'brief' THEN 3 ELSE 4 END")
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.articles.index', [
            'articles' => $articles,
            'statusOptions' => ArticleStatus::options(),
            'projects' => Project::query()->accessibleBy($user)->orderBy('domain')->get(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(),
            'canCreate' => $user->hasPermission('articles.create'),
            'canApprove' => $user->hasPermission('articles.approve'),
        ]);
    }
}
