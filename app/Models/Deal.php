<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Deal extends Model
{
    protected $guarded = ['id', 'reference'];

    protected $casts = [
        'closed_at' => 'date',
        'contract_start' => 'date',
        'contract_end' => 'date',
        'documents' => 'array',
        'price' => 'decimal:2',
        'buyer_commission_value' => 'decimal:2',
        'buyer_commission_amount' => 'decimal:2',
        'seller_commission_value' => 'decimal:2',
        'seller_commission_amount' => 'decimal:2',
        'total_commission' => 'decimal:2',
        'agent_share_percent' => 'decimal:2',
        'agent_commission' => 'decimal:2',
    ];

    // ===== القيم الافتراضية (قابلة للتعديل في كل صفقة) =====
    public const DEFAULT_BUYER_PERCENT = 2;
    public const DEFAULT_SELLER_PERCENT = 2;
    public const DEFAULT_AGENT_SHARE = 25;

    public const STATUSES = [
        'in_progress' => 'قيد السداد',
        'completed' => 'مكتملة',
        'cancelled' => 'ملغاة',
    ];

    public const STATUS_COLORS = [
        'in_progress' => 'warning',
        'completed' => 'success',
        'cancelled' => 'danger',
    ];

    public const COMMISSION_TYPES = [
        'percent' => 'نسبة %',
        'fixed' => 'مبلغ ثابت',
    ];

    // ===== العلاقات =====

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DealPayment::class)->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id');
    }

    // ===== الحسابات =====

    public static function commissionAmount(?string $type, $value, $price): float
    {
        return $type === 'fixed'
            ? (float) $value
            : round((float) $price * (float) $value / 100, 2);
    }

    public function recalculate(): void
    {
        $this->buyer_commission_amount = static::commissionAmount($this->buyer_commission_type, $this->buyer_commission_value, $this->price);
        $this->seller_commission_amount = static::commissionAmount($this->seller_commission_type, $this->seller_commission_value, $this->price);
        $this->total_commission = (float) $this->buyer_commission_amount + (float) $this->seller_commission_amount;
        $this->agent_commission = round((float) $this->total_commission * (float) $this->agent_share_percent / 100, 2);
    }

    public function paidTotal(string $category = 'deal'): float
    {
        return (float) $this->payments()->where('category', $category)->whereNotNull('paid_at')->sum('amount');
    }

    public function scheduledTotal(string $category = 'deal'): float
    {
        return (float) $this->payments()->where('category', $category)->sum('amount');
    }

    public function money($amount): string
    {
        return Lookup::money($amount, $this->currency);
    }

    // ===== التسكير =====

    /**
     * يُنفَّذ عند تسجيل الصفقة:
     * - العقار يصير غير متاح (مباع / مؤجر)
     * - العميل ← تمت الصفقة
     * - يُرجِع باقي العملاء المهتمين بنفس العقار
     */
    public function applyClosing(): Collection
    {
        $reason = $this->transaction === 'rent' ? 'rented' : 'sold';

        if ($this->property->is_available) {
            $this->property->setAvailability(false, $reason, "صفقة {$this->reference}");
        }

        if ($this->lead && $this->lead->stage !== 'won') {
            $this->lead->changeStage('won', "صفقة {$this->reference} على العقار {$this->property->reference}");
        }

        $others = Lead::query()
            ->open()
            ->where('interested_property_id', $this->property_id)
            ->when($this->lead_id, fn ($q) => $q->whereKeyNot($this->lead_id))
            ->with('assignee')
            ->get();

        foreach ($others as $other) {
            $other->activities()->create([
                'type' => 'note',
                'body' => "⚠️ العقار {$this->property->reference} الذي يهتم به العميل لم يعد متاحاً (صفقة {$this->reference}). اقترح عليه بديلاً.",
            ]);
        }

        return $others;
    }

    /** إلغاء الصفقة مع خيار إعادة إتاحة العقار */
    public function cancel(?string $note, bool $reopenProperty): void
    {
        $this->update(['status' => 'cancelled', 'cancellation_note' => $note]);

        if ($reopenProperty && ! $this->property->is_available) {
            $this->property->setAvailability(true, null, "إلغاء الصفقة {$this->reference}" . ($note ? " — {$note}" : ''));
        }

        $this->lead?->activities()->create(['type' => 'note', 'body' => "تم إلغاء الصفقة {$this->reference}" . ($note ? ": {$note}" : '')]);
    }

    protected static function booted(): void
    {
        static::saving(fn (Deal $deal) => $deal->recalculate());

        static::created(function (Deal $deal) {
            $deal->forceFill(['reference' => 'D-' . $deal->id])->saveQuietly();
        });
    }
}
