<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\EmailNeedsProcessing;
use App\Jobs\ParseEmailJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessEmailContent implements ShouldQueue
{
    public $queue = 'emails';

    /**
     * Handle the event
     */
    public function handle(EmailNeedsProcessing $event): void
    {
        ParseEmailJob::dispatch($event->email);
    }
}
