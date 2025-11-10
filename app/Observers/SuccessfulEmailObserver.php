<?php

namespace App\Observers;

use App\Events\EmailCreated;
use App\Events\EmailNeedsProcessing;
use App\Models\SuccessfulEmail;
use Illuminate\Support\Facades\Log;

class SuccessfulEmailObserver
{
    public function creating(SuccessfulEmail $email): void
    {
        if (empty($email->timestamp)) {
            $email->timestamp = now()->timestamp;
        }
    }

    public function created(SuccessfulEmail $email): void
    {
        if ($email->needsReparsing()) {
            event(new EmailNeedsProcessing($email));
        }

        event(new EmailCreated($email));
    }

    public function updating(SuccessfulEmail $email): void
    {
        if ($email->isDirty('email') && $email->wasChanged('email')) {
            $email->raw_text = null;
        }
    }

    public function updated(SuccessfulEmail $email): void
    {
        if ($email->wasChanged('email') && empty($email->raw_text)) {
            event(new EmailNeedsProcessing($email));
        }
    }

    public function deleted(SuccessfulEmail $email): void
    {
        Log::info('Email deleted', [
            'id' => $email->id,
            'subject' => $email->subject
        ]);
    }
}
