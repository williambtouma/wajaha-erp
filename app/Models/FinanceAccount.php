<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceAccount extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public const TYPES = [
        'cash' => 'صندوق نقدي',
        'bank' => 'حساب بنكي',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class, 'account_id');
    }

    public function balance(): float
    {
        $in = (float) $this->transactions()->where('direction', 'in')->sum('amount');
        $out = (float) $this->transactions()->where('direction', 'out')->sum('amount');

        return (float) $this->opening_balance + $in - $out;
    }

    public function label(): string
    {
        return "{$this->name} (" . (Lookup::code('currency', $this->currency) ?? $this->currency) . ')';
    }

    public static function options(?string $currency = null): array
    {
        return static::query()
            ->where('is_active', true)
            ->when($currency, fn ($q) => $q->where('currency', $currency))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (FinanceAccount $a) => [$a->id => $a->label()])
            ->all();
    }
}
