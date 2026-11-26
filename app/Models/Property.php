<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $guarded = ['id', 'reference'];

    protected $casts = [
        'images' => 'array',
        'is_featured' => 'boolean',
        'price' => 'decimal:2',
        'built_area' => 'decimal:2',
        'land_area' => 'decimal:2',
        'expected_delivery' => 'date',
    ];

    // قيم يعتمد عليها النظام (موجودة في الإعدادات كقيم محمية)
    public const LAND_SUBTYPES = ['land', 'farm'];

    public const SALE = 'sale';

    public const UNDER_CONSTRUCTION = 'under_construction';

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
