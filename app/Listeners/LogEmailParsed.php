<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\EmailParsed;
use Illuminate\Support\Facades\Log;

class LogEmailParsed
{
    /**
     * Handle the event
     */
    public function handle(EmailParsed $event): void
    {
        Log::info("Email parsed successfully", [
            'email_id' => $event->email->id,
            'text_length' => strlen($event->rawText)
        ]);
    }
}
