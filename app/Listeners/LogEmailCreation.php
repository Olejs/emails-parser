<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\EmailCreated;
use Illuminate\Support\Facades\Log;

class LogEmailCreation
{
    /**
     * Handle the event
     */
    public function handle(EmailCreated $event): void
    {
        Log::info('Email created event handled', [
            'email_id' => $event->email->id,
            'affiliate_id' => $event->email->affiliate_id,
            'timestamp' => now()->toDateTimeString()
        ]);
    }
}
