<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Project;
use App\Models\User;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportArticlesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:articles 
                            {file? : The path to the CSV file} 
                            {--template : Generate a template CSV file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bulk import articles from a CSV file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('template')) {
            return $this->generateTemplate();
        }

        $file = $this->argument('file');

        if (! $file || ! File::exists($file)) {
            $this->error("File not found: {$file}");
            $this->info('To generate a template, run: php artisan import:articles --template');

            return self::FAILURE;
        }

        $this->info("Importing articles from {$file}...");

        $fp = fopen($file, 'r');
        $headers = fgetcsv($fp);

        // Clean headers (e.g., lowercasing and trimming spaces)
        $headers = array_map(fn ($h) => strtolower(trim($h)), $headers);

        // Ensure required database columns are present
        $requiredHeaders = ['project_id', 'title', 'target_keyword'];

        foreach ($requiredHeaders as $req) {
            if (! in_array($req, $headers)) {
                $this->error("Missing required column in CSV: {$req}");

                return self::FAILURE;
            }
        }

        $successCount = 0;
        $rowNumber = 1;

        while (($row = fgetcsv($fp)) !== false) {
            $rowNumber++;

            // Skip completely empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Pad row with nulls if it is shorter than the headers
            $row = array_pad($row, count($headers), null);
            $data = array_combine($headers, $row);

            // Clean up empty strings to null
            $data = array_map(fn ($val) => trim($val) === '' ? null : $val, $data);

            // Validate Project ID
            $projectId = $data['project_id'];
            if (! Project::query()->find($projectId)) {
                $this->warn("Row {$rowNumber}: Project ID '{$projectId}' not found. Skipping.");

                continue;
            }

            // Validate Writer ID if provided
            if (isset($data['writer_id']) && $data['writer_id'] !== null) {
                if (! User::query()->find($data['writer_id'])) {
                    $this->warn("Row {$rowNumber}: Writer ID '{$data['writer_id']}' not found. Skipping.");

                    continue;
                }
            }

            try {
                $cost = (float) ($data['cost'] ?? 0);

                Article::create([
                    'project_id' => (int) $projectId,
                    'title' => $data['title'],
                    'target_keyword' => $data['target_keyword'],
                    'word_count_target' => isset($data['word_count_target']) ? (int) $data['word_count_target'] : null,
                    'word_count_actual' => isset($data['word_count_actual']) ? (int) $data['word_count_actual'] : null,
                    'writer_id' => isset($data['writer_id']) ? (int) $data['writer_id'] : null,
                    'cost_paisa' => Money::toMinor($cost),
                    'status' => $data['status'] ?? 'brief',
                    'meta_title' => $data['meta_title'] ?? null,
                    'meta_description' => $data['meta_description'] ?? null,
                    'published_url' => $data['published_url'] ?? null,
                    'publish_date' => $data['publish_date'] ?? null,
                    'updated_date' => $data['updated_date'] ?? null,
                ]);

                $successCount++;
            } catch (\Exception $e) {
                $this->error("Error on row {$rowNumber}: ".$e->getMessage());
            }
        }

        fclose($fp);

        $this->info("Successfully imported {$successCount} articles.");

        return self::SUCCESS;
    }

    protected function generateTemplate()
    {
        $path = public_path('articles_import_template.csv');
        $headers = [
            'project_id',
            'title',
            'target_keyword',
            'cost',
            'status',
            'word_count_target',
            'word_count_actual',
            'writer_id',
            'meta_title',
            'meta_description',
            'published_url',
            'publish_date',
            'updated_date',
        ];

        $fp = fopen($path, 'w');
        fputcsv($fp, $headers);
        fclose($fp);

        $this->info("Template created at: {$path}");

        return self::SUCCESS;
    }
}
