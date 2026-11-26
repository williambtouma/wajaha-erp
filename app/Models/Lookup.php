<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Lookup extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    /** القوائم المتاحة في الإعدادات => عمود العقار المرتبط بها */
    public const LISTS = [
        'category' => 'أنواع العقار',
        'subtype' => 'التصنيفات الفرعية',
        'transaction' => 'أنواع المعاملة',
        'readiness' => 'الجاهزية',
        'status' => 'حالات التوفر',
        'currency' => 'العملات',
        'city' => 'المدن',
        'condition' => 'حالة العقار',
        'furnishing' => 'التأثيث',
        'ownership_type' => 'صفة المعلن',
    ];

    public const COLORS = [
        'primary' => 'أخضر وجاهة',
        'success' => 'أخضر',
        'info' => 'أزرق',
        'warning' => 'أصفر',
        'danger' => 'أحمر',
        'gray' => 'رمادي',
    ];

    protected static array $cache = [];

    protected static function booted(): void
    {
        static::saving(function (Lookup $lookup) {
            if (blank($lookup->key)) {
                $lookup->key = trim($lookup->label);
            }
            if ($lookup->list_name !== 'subtype') {
                $lookup->parent_key = null;
            }
        });

        static::saved(fn () => static::$cache = []);
        static::deleted(fn () => static::$cache = []);
    }

    public static function rows(string $list): Collection
    {
        return static::$cache[$list] ??= static::query()
            ->where('list_name', $list)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    /** خيارات القائمة المنسدلة: [key => label] */
    public static function options(string $list, ?string $parent = null): array
    {
        return static::rows($list)
            ->where('is_active', true)
            ->when($parent !== null, fn (Collection $rows) => $rows->where('parent_key', $parent))
            ->unique('key')
            ->pluck('label', 'key')
            ->all();
    }

    public static function entry(string $list, ?string $key, ?string $parent = null): ?self
    {
        if (blank($key)) {
            return null;
        }

        $rows = static::rows($list)->where('key', $key);

        return ($parent !== null ? $rows->firstWhere('parent_key', $parent) : null) ?? $rows->first();
    }

    public static function label(string $list, ?string $key, ?string $parent = null): ?string
    {
        return static::entry($list, $key, $parent)?->label ?? $key;
    }

    public static function code(string $list, ?string $key, ?string $parent = null): ?string
    {
        return static::entry($list, $key, $parent)?->code;
    }

    public static function color(string $list, ?string $key): string
    {
        return static::entry($list, $key)?->color ?? 'gray';
    }

    /** هل هذه القيمة مستخدمة في عقار؟ (لمنع حذفها) */
    public function isInUse(): bool
    {
        return Property::query()
            ->where($this->list_name, $this->key)
            ->when($this->list_name === 'subtype', fn ($q) => $q->where('category', $this->parent_key))
            ->exists();
    }
}
