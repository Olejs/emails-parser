<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\EmailParsed;
use App\Exceptions\EmailParsingException;
use App\Models\SuccessfulEmail;
use App\Services\EmailParserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ParseEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [10, 30, 60];

    /**
     * Create a new job instance
     */
    public function __construct(
        public SuccessfulEmail $email
    ) {
        $this->onQueue('emails');
    }

    /**
     * Execute the job
     */
    public function handle(EmailParserService $parser): void
    {
        try {
            Log::info("Parsing email via job", ['email_id' => $this->email->id]);

            $rawText = $parser->parseEmailToPlainText($this->email->email);

            $this->email->markAsProcessed($rawText);

            event(new EmailParsed($this->email, $rawText));

        } catch (\Exception $e) {
            Log::error("Email parsing failed in job", [
                'email_id' => $this->email->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            if ($this->attempts() < $this->tries) {
                throw $e;
            }

            throw new EmailParsingException(
                "Failed to parse email {$this->email->id} after {$this->tries} attempts",
                previous: $e
            );
        }
    }

    /**
     * Handle a job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Email parsing job failed permanently", [
            'email_id' => $this->email->id,
            'error' => $exception->getMessage()
        ]);
    }
}
