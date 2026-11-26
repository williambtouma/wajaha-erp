<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealPayment extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'date',
    ];

    public const CATEGORIES = [
        'deal' => 'دفعة صفقة',
        'commission' => 'تحصيل عمولة',
    ];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function status(): string
    {
        return match (true) {
            $this->paid_at !== null => 'paid',
            $this->due_date?->isPast() && ! $this->due_date->isToday() => 'overdue',
            default => 'upcoming',
        };
    }

    public const STATUS_LABELS = ['paid' => 'مدفوعة', 'overdue' => 'متأخرة', 'upcoming' => 'مستحقة'];

    public const STATUS_COLORS = ['paid' => 'success', 'overdue' => 'danger', 'upcoming' => 'warning'];

    protected static function booted(): void
    {
        static::creating(fn (DealPayment $payment) => $payment->recorded_by ??= auth()->id());

        // اكتمال الصفقة تلقائياً عند سداد كل دفعاتها
        static::saved(function (DealPayment $payment) {
            $deal = $payment->deal;
            if ($deal->status === 'in_progress'
                && $deal->payments()->exists()
                && ! $deal->payments()->whereNull('paid_at')->exists()) {
                $deal->update(['status' => 'completed']);
            }
        });
    }
}
