<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property-read int $affiliate_id
 * @property-read string $envelope
 * @property-read string $from
 * @property-read string $subject
 * @property-read string|null $dkim
 * @property-read string|null $SPF
 * @property-read float|null $spam_score
 * @property-read string $email
 * @property-read string|null $sender_ip
 * @property-read string $to
 * @property-read int $timestamp
 */

class UpdateEmailRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (!$this->has('raw_text')) {
            $this->merge([
                'raw_text' => '',
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'affiliate_id' => ['sometimes', 'integer', 'min:1'],
            'envelope' => ['sometimes', 'string'],
            'from' => ['sometimes', 'string', 'max:255'],
            'subject' => ['sometimes', 'string', 'max:1000'],
            'dkim' => ['nullable', 'string', 'max:255'],
            'SPF' => ['nullable', 'string', 'max:255'],
            'spam_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'email' => ['sometimes', 'string'],
            'sender_ip' => ['nullable', 'ip'],
            'to' => ['sometimes', 'string'],
            'timestamp' => ['sometimes', 'integer', 'min:0'],
            'raw_text' => ['required', 'string'],
        ];
    }
}
