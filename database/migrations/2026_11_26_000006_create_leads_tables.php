<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ===== العملاء =====
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->unique();          // L-15
            $table->string('type');                                     // buyer | owner | investor | developer
            $table->string('name');
            $table->string('phone');
            $table->string('phone2')->nullable();                       // واتساب / رقم إضافي
            $table->string('email')->nullable();
            $table->string('source')->nullable();                       // المصدر
            $table->string('stage')->default('new');                    // المرحلة
            $table->string('priority')->nullable();                     // hot | warm | cold
            $table->string('lost_reason')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // العقار المطلوب (مشتري) أو المعروض (مالك)
            $table->string('transaction')->nullable();
            $table->string('category')->nullable();
            $table->string('subtype')->nullable();
            $table->string('city')->nullable();
            $table->string('area')->nullable();
            $table->decimal('budget_min', 15, 2)->nullable();
            $table->decimal('budget_max', 15, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->unsignedSmallInteger('bedrooms_min')->nullable();

            $table->foreignId('interested_property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete(); // عقار المالك بعد التحويل

            $table->json('details')->nullable();                        // حقول المستثمر والمطور
            $table->text('notes')->nullable();
            $table->dateTime('next_follow_up_at')->nullable();
            $table->timestamp('stage_changed_at')->nullable();
            $table->timestamps();

            $table->index(['stage', 'type']);
            $table->index('next_follow_up_at');
        });

        // ===== سجل المتابعة =====
        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');                                     // call | whatsapp | meeting | viewing | note | email | stage_change
            $table->text('body')->nullable();
            $table->dateTime('scheduled_at')->nullable();               // موعد (اجتماع / معاينة)
            $table->timestamps();
        });

        // ===== قوائم الإعدادات =====
        $rows = [];
        $add = function (string $list, array $items) use (&$rows) {
            $sort = 0;
            foreach ($items as $key => $item) {
                $item = is_array($item) ? $item : ['label' => $item];
                $rows[] = [
                    'list_name' => $list,
                    'key' => $key,
                    'label' => $item['label'],
                    'parent_key' => null,
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

        $add('lead_type', [
            'buyer' => ['label' => 'مشتري / مستأجر', 'color' => 'primary', 'system' => true],
            'owner' => ['label' => 'مالك يعرض عقاره', 'color' => 'warning', 'system' => true],
            'investor' => ['label' => 'مستثمر', 'color' => 'info', 'system' => true],
            'developer' => ['label' => 'مطوّر / مشروع', 'color' => 'gray', 'system' => true],
        ]);

        $add('lead_stage', [
            'new' => ['label' => 'جديد', 'color' => 'info', 'system' => true],
            'contacted' => ['label' => 'تم التواصل', 'color' => 'primary'],
            'viewing' => ['label' => 'معاينة / اجتماع', 'color' => 'warning'],
            'negotiation' => ['label' => 'تفاوض', 'color' => 'warning'],
            'won' => ['label' => 'تمت الصفقة', 'color' => 'success', 'system' => true],
            'lost' => ['label' => 'خسارة', 'color' => 'danger', 'system' => true],
        ]);

        $add('lead_source', [
            'website' => 'الموقع الإلكتروني',
            'facebook' => 'فيسبوك',
            'instagram' => 'إنستغرام',
            'whatsapp' => 'واتساب',
            'phone_call' => 'اتصال هاتفي',
            'referral' => 'توصية',
            'walk_in' => 'زيارة المكتب',
            'other' => 'أخرى',
        ]);

        $add('lead_priority', [
            'hot' => ['label' => 'ساخن 🔥', 'color' => 'danger'],
            'warm' => ['label' => 'دافئ', 'color' => 'warning'],
            'cold' => ['label' => 'بارد', 'color' => 'info'],
        ]);

        $add('lost_reason', [
            'price' => 'السعر غير مناسب',
            'no_match' => 'لا يوجد عقار مناسب',
            'competitor' => 'تعامل مع جهة أخرى',
            'no_response' => 'لا يرد',
            'postponed' => 'أجّل القرار',
            'other' => 'أخرى',
        ]);

        $add('activity_type', [
            'call' => ['label' => 'اتصال', 'color' => 'primary'],
            'whatsapp' => ['label' => 'واتساب', 'color' => 'success'],
            'meeting' => ['label' => 'اجتماع', 'color' => 'warning'],
            'viewing' => ['label' => 'معاينة عقار', 'color' => 'warning'],
            'email' => ['label' => 'إيميل', 'color' => 'info'],
            'note' => ['label' => 'ملاحظة', 'color' => 'gray'],
        ]);

        // من نماذج الموقع
        $add('investment_type', [
            'residential' => 'سكني', 'commercial' => 'تجاري', 'land' => 'أرض', 'projects' => 'مشاريع عقارية', 'other' => 'أخرى',
        ]);
        $add('investment_objective', [
            'long_term' => 'عوائد طويلة الأجل', 'rental' => 'دخل إيجاري', 'appreciation' => 'نمو رأس المال', 'development' => 'فرص التطوير', 'other' => 'أخرى',
        ]);
        $add('investment_timeline', [
            'immediate' => 'فوري', '3_months' => 'خلال 3 أشهر', '6_months' => 'خلال 6 أشهر', 'flexible' => 'مرن',
        ]);
        $add('project_type', [
            'residential' => 'سكني', 'commercial' => 'تجاري', 'mixed' => 'متعدد الاستخدامات', 'other' => 'أخرى',
        ]);
        $add('project_status', [
            'planning' => 'تحت التخطيط', 'construction' => 'تحت الإنشاء', 'ready' => 'جاهز', 'sold' => 'تم البيع / بيع جزئي',
        ]);

        DB::table('lookups')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('leads');
        DB::table('lookups')->whereIn('list_name', [
            'lead_type', 'lead_stage', 'lead_source', 'lead_priority', 'lost_reason', 'activity_type',
            'investment_type', 'investment_objective', 'investment_timeline', 'project_type', 'project_status',
        ])->delete();
    }
};
