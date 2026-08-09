<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('articles.view');
    }

    public function view(User $user, Article $article): bool
    {
        return $user->hasPermission('articles.view') && $user->canAccessProject($article->project);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('articles.create');
    }

    public function update(User $user, Article $article): bool
    {
        if (! $user->canAccessProject($article->project)) {
            return false;
        }

        if ($user->hasPermission('articles.approve')) {
            return true;
        }

        if ($user->hasPermission('articles.update') && (int) $article->writer_id === (int) $user->id) {
            return true;
        }

        // Supervisors who can create may edit briefs not yet assigned to someone else
        if ($user->hasPermission('articles.create') && $user->hasPermission('tasks.assign')) {
            return true;
        }

        return false;
    }

    public function approve(User $user, Article $article): bool
    {
        return $user->hasPermission('articles.approve') && $user->canAccessProject($article->project);
    }

    public function submit(User $user, Article $article): bool
    {
        if (! $user->canAccessProject($article->project)) {
            return false;
        }

        if ($user->hasPermission('articles.approve')) {
            return true;
        }

        return $user->hasPermission('articles.update') && (int) $article->writer_id === (int) $user->id;
    }

    public function delete(User $user, Article $article): bool
    {
        return $user->isAdmin();
    }
}
