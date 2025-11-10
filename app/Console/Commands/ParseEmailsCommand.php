<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ParseEmailJob;
use App\Models\SuccessfulEmail;
use App\Services\EmailParserService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class ParseEmailsCommand extends Command
{
    protected $signature = 'emails:parse
                            {--limit=100 : Number of emails to process}
                            {--queue : Process via queue}';

    protected $description = 'Parse unprocessed emails to extract plain text';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $useQueue = $this->option('queue');

        $this->components->info("Starting email parsing...");
        $this->components->info("Limit: {$limit} emails");

        $query = SuccessfulEmail::unprocessed()->limit($limit);

        $emails = $query->get();

        if ($emails->isEmpty()) {
            $this->components->warn('No unprocessed emails found.');
            return self::SUCCESS;
        }

        $this->components->info("Found {$emails->count()} emails to process.");

        if ($useQueue) {
            $this->processViaQueue($emails);
        } else {
            $this->processSynchronously($emails);
        }

        return self::SUCCESS;
    }

    private function processViaQueue($emails): void
    {
        $this->components->info("Dispatching jobs to queue...");

        $jobs = $emails->map(fn($email) => new ParseEmailJob($email));

        Bus::batch($jobs)
            ->name('Email Parsing Batch')
            ->allowFailures()
            ->dispatch();

        $this->components->success("Dispatched {$emails->count()} jobs to queue!");
    }

    private function processSynchronously($emails): void
    {
        $bar = $this->output->createProgressBar($emails->count());
        $bar->start();

        $processed = 0;
        $failed = 0;

        foreach ($emails as $email) {
            try {
                $email->touch();
                $this->components->info("Parsing email ID: {$email->id}");
                Log::info("Parsing email ID: {$email->id}");
                $rawText = app(EmailParserService::class)->parseEmailToPlainText($email->email);
                $email->markAsProcessed($rawText);
                $this->components->info("Parsed {$email->id} | Length: " . strlen($rawText));
                $processed++;
            } catch (\Throwable $e) {
                $this->components->error("Failed: {$email->id} | " . $e->getMessage());
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->components->success("Processed: {$processed}");
        if ($failed > 0) {
            $this->components->warn("Failed: {$failed}");
        }
    }
}
