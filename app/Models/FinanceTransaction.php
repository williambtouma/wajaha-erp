<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceTransaction extends Model
{
    protected $guarded = ['id', 'reference'];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'amount_usd' => 'decimal:2',
    ];

    public const DIRECTIONS = [
        'in' => 'إيراد',
        'out' => 'مصروف',
    ];

    public const TRANSFER = 'transfer';

    // ===== العلاقات =====

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'account_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ===== نطاقات =====

    /** الحركات الحقيقية (بدون التحويلات الداخلية بين الحسابات) */
    public function scopeReal(Builder $query): Builder
    {
        return $query->where('category', '!=', self::TRANSFER);
    }

    public function scopeInYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('date', $year);
    }

    // ===== العرض =====

    public function categoryLabel(): string
    {
        if ($this->category === self::TRANSFER) {
            return 'تحويل بين الحسابات';
        }

        return Lookup::label($this->direction === 'in' ? 'income_category' : 'expense_category', $this->category) ?? $this->category;
    }

    public function categoryColor(): string
    {
        if ($this->category === self::TRANSFER) {
            return 'gray';
        }

        return Lookup::color($this->direction === 'in' ? 'income_category' : 'expense_category', $this->category);
    }

    public static function categoryOptions(?string $direction): array
    {
        return match ($direction) {
            'in' => Lookup::options('income_category'),
            'out' => Lookup::options('expense_category'),
            default => [],
        };
    }

    protected static function booted(): void
    {
        static::saving(function (FinanceTransaction $tx) {
            $tx->currency = $tx->account?->currency ?? $tx->currency ?? 'USD';
            $tx->exchange_rate = $tx->currency === 'USD' ? 1 : ($tx->exchange_rate ?: ExchangeRate::current($tx->currency) ?: 1);
            $tx->amount_usd = ExchangeRate::toUsd($tx->amount, $tx->currency, (float) $tx->exchange_rate);
            $tx->created_by ??= auth()->id();
        });

        static::created(function (FinanceTransaction $tx) {
            $tx->forceFill(['reference' => 'TX-' . $tx->id])->saveQuietly();
        });

        // حذف أحد طرفي التحويل يحذف الطرف الآخر
        static::deleted(function (FinanceTransaction $tx) {
            if ($tx->transfer_group) {
                static::query()->where('transfer_group', $tx->transfer_group)->whereKeyNot($tx->id)->get()->each->deleteQuietly();
            }
        });
    }
}
