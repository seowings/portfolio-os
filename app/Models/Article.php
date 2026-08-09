<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'title',
        'target_keyword',
        'word_count_target',
        'word_count_actual',
        'writer_id',
        'status',
        'cost_paisa',
        'meta_title',
        'meta_description',
        'published_url',
        'publish_date',
        'updated_date',
        'revision_notes',
        'created_by',
        'approved_by',
        'submitted_at',
        'approved_at',
        'expense_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'word_count_target' => 'integer',
            'word_count_actual' => 'integer',
            'cost_paisa' => 'integer',
            'publish_date' => 'date',
            'updated_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function writer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'writer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('project_id', $user->accessibleProjectIds() ?: [0]);
    }

    /**
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopeAwaitingApproval(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::DraftSubmitted->value);
    }

    public function costFormatted(): string
    {
        return Money::formatted((int) $this->cost_paisa);
    }
}
