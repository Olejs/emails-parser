<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class EmailEnvelopeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (empty($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        return [
            'to' => $decoded['to'] ?? [],
            'from' => $decoded['from'] ?? null,
            'cc' => $decoded['cc'] ?? [],
            'bcc' => $decoded['bcc'] ?? [],
        ];
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (is_string($value)) {
            return $value;
        }

        return json_encode($value);
    }
}
