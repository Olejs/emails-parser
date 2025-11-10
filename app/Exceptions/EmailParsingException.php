<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class EmailParsingException extends Exception
{
    public function report(): void
    {
        \Log::channel('email-errors')->error($this->getMessage(), [
            'exception' => static::class,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ]);
    }

    public function render()
    {
        return response()->json([
            'error' => 'Email parsing failed',
            'message' => $this->getMessage()
        ], 500);
    }
}
