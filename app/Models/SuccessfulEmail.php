<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EmailEnvelopeCast;
use App\Observers\SuccessfulEmailObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $affiliate_id
 * @property array $envelope
 * @property string $from
 * @property string $subject
 * @property string|null $dkim
 * @property string|null $SPF
 * @property float $spam_score
 * @property string $email
 * @property string|null $raw_text
 * @property string|null $sender_ip
 * @property string $to
 * @property int $timestamp
 *
 * @property-read string $formatted_date
 * @property-read bool $is_processed
 * @property-read int $word_count
 * @property-read string|null $preview
 */
#[ObservedBy([SuccessfulEmailObserver::class])]
class SuccessfulEmail extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'affiliate_id',
        'envelope',
        'from',
        'subject',
        'dkim',
        'SPF',
        'spam_score',
        'email',
        'raw_text',
        'sender_ip',
        'to',
        'timestamp',
    ];

    protected $attributes = [
        'raw_text' => '',
    ];

    protected $casts = [
        'envelope' => EmailEnvelopeCast::class,
        'spam_score' => 'float',
        'timestamp' => 'integer',
    ];

    protected $appends = [
        'formatted_date',
        'is_processed',
        'word_count',
    ];

    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('raw_text')
                ->orWhere('raw_text', '');
        });
    }

    public function scopeProcessed(Builder $query): Builder
    {
        return $query->whereNotNull('raw_text')
            ->where('raw_text', '!=', '');
    }

    public function scopeFromAffiliate(Builder $query, int $affiliateId): Builder
    {
        return $query->where('affiliate_id', $affiliateId);
    }

    public function scopeWithSpamScoreBelow(Builder $query, float $threshold): Builder
    {
        return $query->where('spam_score', '<', $threshold);
    }

    public function scopeBetweenDates(Builder $query, int $startTimestamp, int $endTimestamp): Builder
    {
        return $query->whereBetween('timestamp', [$startTimestamp, $endTimestamp]);
    }

    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        $timestamp = now()->subDays($days)->timestamp;
        return $query->where('timestamp', '>=', $timestamp);
    }

    public function scopeSearchInContent(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('subject', 'like', "%{$search}%")
                ->orWhere('raw_text', 'like', "%{$search}%")
                ->orWhere('from', 'like', "%{$search}%");
        });
    }

    public function markAsProcessed(string $rawText): bool
    {
        return $this->update(['raw_text' => $rawText]);
    }

    public function needsReparsing(): bool
    {
        return empty($this->raw_text) ||
            (strlen($this->raw_text) < 10 && strlen($this->email) > 100);
    }
}
