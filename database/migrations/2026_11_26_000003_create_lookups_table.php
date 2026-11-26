<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // قيم القوائم المنسدلة (تُدار من: الإعدادات ← القوائم المنسدلة)
        Schema::create('lookups', function (Blueprint $table) {
            $table->id();
            $table->string('list_name');                    // اسم القائمة: city, subtype, ...
            $table->string('key');                          // القيمة المحفوظة في قاعدة البيانات
            $table->string('label');                        // الاسم الظاهر
            $table->string('parent_key')->nullable();       // للتصنيفات الفرعية: نوع العقار التابعة له
            $table->string('code')->nullable();             // رمز إضافي: VIL للتصنيف، $ للعملة
            $table->string('color')->nullable();            // لون البادج
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);   // قيم يعتمد عليها النظام: لا تُحذف ولا يتغير مفتاحها
            $table->timestamps();

            $table->unique(['list_name', 'parent_key', 'key']);
        });

        $rows = [];
        $add = function (string $list, array $items, ?string $parent = null) use (&$rows) {
            $sort = 0;
            foreach ($items as $key => $item) {
                $item = is_array($item) ? $item : ['label' => $item];
                $rows[] = [
                    'list_name' => $list,
                    'key' => $key,
                    'label' => $item['label'],
                    'parent_key' => $parent,
                    'code' => $item['code'] ?? null,
                    'color' => $item['color'] ?? null,
                    'sort' => $sort++,
                    'is_active' => true,
                    'is_system' => $item['system'] ?? false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        };

        $add('category', [
            'residential' => 'سكني',
            'commercial' => 'تجاري',
        ]);

        $add('subtype', [
            'apartment' => ['label' => 'شقة', 'code' => 'APT'],
            'villa' => ['label' => 'فيلا', 'code' => 'VIL'],
            'house' => ['label' => 'فيلا / منزل مستقل', 'code' => 'VIL'],
            'land' => ['label' => 'قطعة أرض', 'code' => 'LND', 'system' => true],
            'farm' => ['label' => 'أرض زراعية', 'code' => 'FRM', 'system' => true],
            'building' => ['label' => 'مبنى', 'code' => 'BLD'],
        ], 'residential');

        $add('subtype', [
            'shop' => ['label' => 'متجر', 'code' => 'SHP'],
            'mall' => ['label' => 'مجمع تجاري', 'code' => 'MAL'],
            'villa' => ['label' => 'فيلا', 'code' => 'VIL'],
            'house' => ['label' => 'فيلا / منزل مستقل', 'code' => 'VIL'],
            'land' => ['label' => 'قطعة أرض', 'code' => 'LND', 'system' => true],
            'building' => ['label' => 'مبنى', 'code' => 'BLD'],
        ], 'commercial');

        $add('transaction', [
            'sale' => ['label' => 'للبيع', 'color' => 'primary', 'system' => true],
            'rent' => ['label' => 'للإيجار', 'color' => 'info', 'system' => true],
        ]);

        $add('readiness', [
            'ready' => ['label' => 'جاهز', 'color' => 'success', 'system' => true],
            'under_construction' => ['label' => 'قيد الإنشاء', 'color' => 'warning', 'system' => true],
        ]);

        $add('status', [
            'available' => ['label' => 'متاح', 'color' => 'success', 'system' => true],
            'reserved' => ['label' => 'محجوز', 'color' => 'warning'],
            'sold' => ['label' => 'مباع', 'color' => 'gray'],
            'rented' => ['label' => 'مؤجر', 'color' => 'gray'],
        ]);

        $add('currency', [
            'USD' => ['label' => 'دولار', 'code' => '$'],
            'SYP' => ['label' => 'ليرة سورية', 'code' => 'ل.س'],
        ]);

        $add('city', [
            'حلب' => 'حلب',
            'دمشق' => 'دمشق',
        ]);

        $add('condition', [
            'new' => 'جديد',
            'existing' => 'قائم',
        ]);

        $add('furnishing', [
            'unfurnished' => 'غير مفروش',
            'semi_furnished' => 'نصف مفروش',
            'furnished' => 'مفروش',
        ]);

        $add('ownership_type', [
            'owner' => 'المالك',
            'representative' => 'وكيل / مفوض',
        ]);

        DB::table('lookups')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('lookups');
    }
};
