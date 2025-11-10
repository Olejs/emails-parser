<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read int $affiliate_id
 * @property-read string $from
 * @property-read string $to
 * @property-read string $subject
 * @property-read string|null $preview
 * @property-read int $word_count
 * @property-read bool $is_processed
 * @property-read float $spam_score
 * @property-read string $formatted_date
 * @property-read int $timestamp
 * @property-read string|null $raw_text
 * @property-read string $email
 */
class EmailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'affiliate_id' => $this->affiliate_id,
            'from' => $this->from,
            'to' => $this->to,
            'subject' => $this->subject,
            'preview' => $this->preview,
            'word_count' => $this->word_count,
            'is_processed' => $this->is_processed,
            'spam_score' => $this->spam_score,
            'formatted_date' => $this->formatted_date,
            'timestamp' => $this->timestamp,
            'raw_text' => $this->raw_text,
            'email' => $this->email,
            'links' => [
                'self' => route('emails.show', $this->id),
            ],
        ];
    }
}
