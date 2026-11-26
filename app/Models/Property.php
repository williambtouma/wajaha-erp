<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    protected $guarded = [
        'id', 'reference',
        // التوفر يتغير فقط عبر زر "تغيير التوفر"
        'is_available', 'unavailable_reason', 'availability_note', 'availability_changed_at', 'availability_changed_by',
    ];

    protected $casts = [
        'images' => 'array',
        'documents' => 'array',
        'is_featured' => 'boolean',
        'is_available' => 'boolean',
        'price' => 'decimal:2',
        'built_area' => 'decimal:2',
        'land_area' => 'decimal:2',
        'expected_delivery' => 'date',
        'availability_changed_at' => 'datetime',
    ];

    protected $attributes = [
        'is_available' => true,
    ];

    // قيم يعتمد عليها النظام (موجودة في الإعدادات كقيم محمية)
    public const LAND_SUBTYPES = ['land', 'farm'];

    public const SALE = 'sale';

    public const UNDER_CONSTRUCTION = 'under_construction';

    // ===== العلاقات =====

    public function availabilityLogs(): HasMany
    {
        return $this->hasMany(PropertyAvailabilityLog::class)->latest('id');
    }

    public function availabilityChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'availability_changed_by');
    }

    // ===== التوفر =====

    public function setAvailability(bool $available, ?string $reason = null, ?string $note = null): void
    {
        $this->forceFill([
            'is_available' => $available,
            'unavailable_reason' => $available ? null : $reason,
            'availability_note' => $note,
            'availability_changed_at' => now(),
            'availability_changed_by' => auth()->id(),
        ])->saveQuietly();

        $this->availabilityLogs()->create([
            'is_available' => $available,
            'reason' => $available ? null : $reason,
            'note' => $note,
            'user_id' => auth()->id(),
        ]);
    }

    public function availabilityLabel(): string
    {
        return $this->is_available
            ? 'متاح'
            : 'غير متاح' . ($this->unavailable_reason ? ' - ' . Lookup::label('unavailable_reason', $this->unavailable_reason) : '');
    }

    // ===== العرض =====

    public function subtypeLabel(): ?string
    {
        return Lookup::label('subtype', $this->subtype, $this->category);
    }

    public function formattedPrice(): string
    {
        $symbol = Lookup::code('currency', $this->currency) ?? $this->currency;
        $amount = number_format((float) $this->price);

        return $symbol === '$' ? "\${$amount}" : "{$amount} {$symbol}";
    }

    // ===== توليد رقم العقار تلقائياً: S-VIL-12 =====

    protected static function booted(): void
    {
        static::created(function (Property $property) {
            $property->availabilityLogs()->create([
                'is_available' => true,
                'note' => 'إضافة العقار',
                'user_id' => auth()->id(),
            ]);
        });

        static::saved(function (Property $property) {
            $prefix = $property->transaction === 'rent' ? 'R' : 'S';
            $code = Lookup::code('subtype', $property->subtype, $property->category) ?: 'PRP';
            $reference = strtoupper("{$prefix}-{$code}-{$property->id}");

            if ($property->reference !== $reference) {
                $property->reference = $reference;
                $property->saveQuietly();
            }
        });
    }
}
