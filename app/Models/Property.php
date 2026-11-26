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

    // ===== التصنيفات (نفس الموقع) =====

    public const CATEGORIES = [
        'residential' => 'سكني',
        'commercial' => 'تجاري',
    ];

    public const SUBTYPES = [
        'residential' => [
            'apartment' => 'شقة',
            'villa' => 'فيلا',
            'house' => 'فيلا / منزل مستقل',
            'land' => 'قطعة أرض',
            'farm' => 'أرض زراعية',
            'building' => 'مبنى',
        ],
        'commercial' => [
            'shop' => 'متجر',
            'mall' => 'مجمع تجاري',
            'villa' => 'فيلا',
            'house' => 'فيلا / منزل مستقل',
            'land' => 'قطعة أرض',
            'building' => 'مبنى',
        ],
    ];

    // رمز التصنيف في رقم العقار
    public const SUBTYPE_CODES = [
        'apartment' => 'APT', 'villa' => 'VIL', 'house' => 'VIL', 'land' => 'LND',
        'farm' => 'FRM', 'building' => 'BLD', 'shop' => 'SHP', 'mall' => 'MAL',
    ];

    public const TRANSACTIONS = [
        'sale' => 'للبيع',
        'rent' => 'للإيجار',
    ];

    // للعقارات المعروضة للبيع فقط
    public const READINESS = [
        'ready' => 'جاهز',
        'under_construction' => 'قيد الإنشاء',
    ];

    public const STATUSES = [
        'available' => 'متاح',
        'reserved' => 'محجوز',
        'sold' => 'مباع',
        'rented' => 'مؤجر',
    ];

    public const CURRENCIES = [
        'USD' => 'دولار ($)',
        'SYP' => 'ليرة سورية (ل.س)',
    ];

    public const CITIES = [
        'حلب' => 'حلب',
        'دمشق' => 'دمشق',
    ];

    public const CONDITIONS = [
        'new' => 'جديد',
        'existing' => 'قائم',
    ];

    public const FURNISHINGS = [
        'unfurnished' => 'غير مفروش',
        'semi_furnished' => 'نصف مفروش',
        'furnished' => 'مفروش',
    ];

    public const OWNERSHIP_TYPES = [
        'owner' => 'المالك',
        'representative' => 'وكيل / مفوض',
    ];

    /** كل التصنيفات الفرعية بدون تكرار (للفلاتر) */
    public static function allSubtypes(): array
    {
        return array_merge(...array_values(self::SUBTYPES));
    }

    public function subtypeLabel(): string
    {
        return self::SUBTYPES[$this->category][$this->subtype] ?? $this->subtype;
    }

    public function formattedPrice(): string
    {
        $symbol = $this->currency === 'SYP' ? 'ل.س' : '$';
        $amount = number_format((float) $this->price);

        return $this->currency === 'SYP' ? "{$amount} {$symbol}" : "{$symbol}{$amount}";
    }

    // ===== توليد رقم العقار تلقائياً: S-VIL-12 =====

    protected static function booted(): void
    {
        static::saved(function (Property $property) {
            $prefix = $property->transaction === 'rent' ? 'R' : 'S';
            $code = self::SUBTYPE_CODES[$property->subtype] ?? 'PRP';
            $reference = "{$prefix}-{$code}-{$property->id}";

            if ($property->reference !== $reference) {
                $property->reference = $reference;
                $property->saveQuietly();
            }
        });
    }
}
