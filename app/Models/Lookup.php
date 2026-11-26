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
        'unavailable_reason' => 'أسباب عدم التوفر',
        'currency' => 'العملات',
        'city' => 'المدن',
        'condition' => 'حالة العقار',
        'furnishing' => 'التأثيث',
        'ownership_type' => 'صفة المعلن',
        'document_type' => 'أنواع المستندات',
        // ===== العملاء (CRM) =====
        'lead_type' => 'العملاء: الأنواع',
        'lead_stage' => 'العملاء: المراحل',
        'lead_source' => 'العملاء: المصادر',
        'lead_priority' => 'العملاء: الأولوية',
        'lost_reason' => 'العملاء: أسباب الخسارة',
        'activity_type' => 'العملاء: أنواع المتابعة',
        'investment_type' => 'المستثمر: نوع الاستثمار',
        'investment_objective' => 'المستثمر: الهدف',
        'investment_timeline' => 'المستثمر: الإطار الزمني',
        'project_type' => 'المطوّر: نوع المشروع',
        'project_status' => 'المطوّر: حالة المشروع',
        'payment_method' => 'الصفقات: طرق الدفع',
        'income_category' => 'المالية: تصنيفات الإيرادات',
        'expense_category' => 'المالية: تصنيفات المصاريف',
    ];

    /** القوائم المرتبطة بعمود في جدول العملاء */
    /** قوائم المالية => عمود التصنيف في الحركات */
    public const FINANCE_LISTS = ['income_category' => 'in', 'expense_category' => 'out'];

    public const LEAD_COLUMNS = [
        'lead_type' => 'type',
        'lead_stage' => 'stage',
        'lead_source' => 'source',
        'lead_priority' => 'priority',
        'lost_reason' => 'lost_reason',
    ];

    /** القوائم المرتبطة بعمود في جدول العقارات (لمنع حذف قيمة مستخدمة) */
    public const PROPERTY_COLUMNS = [
        'category', 'subtype', 'transaction', 'readiness', 'unavailable_reason',
        'currency', 'city', 'condition', 'furnishing', 'ownership_type',
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

    /** تنسيق مبلغ بعملته: $120,000 أو 5,000,000 ل.س */
    public static function money($amount, ?string $currency = 'USD'): string
    {
        $symbol = static::code('currency', $currency) ?? $currency;
        $value = number_format((float) $amount);

        return "\u{2066}" . ($symbol === '$' ? "\${$value}" : "{$value} {$symbol}") . "\u{2069}";
    }

    public static function color(string $list, ?string $key): string
    {
        return static::entry($list, $key)?->color ?? 'gray';
    }

    /** هل هذه القيمة مستخدمة في عقار؟ (لمنع حذفها) */
    public function isInUse(): bool
    {
        if (isset(self::FINANCE_LISTS[$this->list_name])) {
            return FinanceTransaction::query()
                ->where('direction', self::FINANCE_LISTS[$this->list_name])
                ->where('category', $this->key)
                ->exists();
        }

        if (isset(self::LEAD_COLUMNS[$this->list_name])) {
            return Lead::query()->where(self::LEAD_COLUMNS[$this->list_name], $this->key)->exists();
        }

        if (! in_array($this->list_name, self::PROPERTY_COLUMNS)) {
            return false;
        }

        return Property::query()
            ->where($this->list_name, $this->key)
            ->when($this->list_name === 'subtype', fn ($q) => $q->where('category', $this->parent_key))
            ->exists();
    }
}
