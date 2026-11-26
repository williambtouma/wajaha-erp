<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'rate' => 'decimal:4',
    ];

    /** آخر سعر صرف مسجّل (كم وحدة = 1 دولار) */
    public static function current(string $currency = 'SYP'): ?float
    {
        if ($currency === 'USD') {
            return 1.0;
        }

        $rate = static::query()->where('currency', $currency)->latest('date')->latest('id')->value('rate');

        return $rate ? (float) $rate : null;
    }

    /** تحويل مبلغ إلى الدولار بسعر محدد أو بآخر سعر */
    public static function toUsd($amount, ?string $currency, ?float $rate = null): float
    {
        if (! $currency || $currency === 'USD') {
            return round((float) $amount, 2);
        }

        $rate ??= static::current($currency);

        return $rate ? round((float) $amount / $rate, 2) : 0.0;
    }
}
