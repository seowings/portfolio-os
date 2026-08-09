<?php

use App\Livewire\Articles\Index as ArticlesIndex;
use App\Models\Article;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function createAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function createStaff(): User
{
    $user = User::factory()->create();
    $user->assignRole('staff');

    return $user;
}

it('imports articles from a valid csv using artisan command', function () {
    $project = Project::factory()->create();

    $csvPath = storage_path('app/temp_test_articles.csv');
    $csvContent = "project_id,title,target_keyword,cost,status,updated_date\n{$project->id},Test Article,test keyword,50.00,brief,2026-08-09\n";
    File::put($csvPath, $csvContent);

    $this->artisan('import:articles', ['file' => $csvPath])
        ->assertExitCode(0)
        ->expectsOutputToContain('Successfully imported 1 articles');

    $this->assertDatabaseHas('articles', [
        'project_id' => $project->id,
        'title' => 'Test Article',
        'target_keyword' => 'test keyword',
        'cost_paisa' => 5000,
        'updated_date' => '2026-08-09 00:00:00',
    ]);

    File::delete($csvPath);
});

it('generates a template csv using artisan command', function () {
    $templatePath = public_path('articles_import_template.csv');
    if (File::exists($templatePath)) {
        File::delete($templatePath);
    }

    $this->artisan('import:articles', ['--template' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('Template created at:');

    expect(File::exists($templatePath))->toBeTrue();
});

it('admin can delete an article via livewire', function () {
    $admin = createAdmin();
    $project = Project::factory()->create();
    $article = Article::create([
        'project_id' => $project->id,
        'title' => 'To Delete',
        'target_keyword' => 'delete me',
        'cost_paisa' => 0,
    ]);

    Livewire::actingAs($admin)
        ->test(ArticlesIndex::class)
        ->call('confirmDelete', $article->id)
        ->assertSet('showDelete', true)
        ->call('delete')
        ->assertSet('showDelete', false)
        ->assertDispatched('toast');

    $this->assertSoftDeleted('articles', [
        'id' => $article->id,
    ]);
});

it('staff cannot delete an article via livewire', function () {
    $staff = createStaff();
    $project = Project::factory()->create();
    $project->teamMembers()->attach($staff->id);

    $article = Article::create([
        'project_id' => $project->id,
        'title' => 'To Delete',
        'target_keyword' => 'delete me',
        'cost_paisa' => 0,
    ]);

    Livewire::actingAs($staff)
        ->test(ArticlesIndex::class)
        ->call('confirmDelete', $article->id)
        ->assertForbidden();
});
