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
class StoreEmailRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (!$this->has('raw_text')) {
            $this->merge([
                'raw_text' => '',
            ]);
        }
    }

    /**
     * Get the validation rules
     */
    public function rules(): array
    {
        return [
            'affiliate_id' => ['required', 'integer', 'min:1'],
            'envelope' => ['required', 'string'],
            'from' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:1000'],
            'dkim' => ['nullable', 'string', 'max:255'],
            'SPF' => ['nullable', 'string', 'max:255'],
            'spam_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'email' => ['required', 'string'],
            'sender_ip' => ['nullable', 'ip'],
            'to' => ['required', 'string'],
            'timestamp' => ['required', 'integer', 'min:0'],
            'raw_text' => ['nullable', 'string'],
        ];
    }
}
