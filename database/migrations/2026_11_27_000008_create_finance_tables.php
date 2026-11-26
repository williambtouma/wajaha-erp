<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ===== أسعار الصرف (ليرة لكل 1 دولار) =====
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('currency', 3)->default('SYP');
            $table->decimal('rate', 15, 4);                 // كم وحدة من العملة = 1 دولار
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['currency', 'date']);
        });

        // ===== الصناديق والحسابات =====
        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('cash');         // cash | bank
            $table->string('currency', 3)->default('USD');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ===== الحركات المالية =====
        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->unique();                    // TX-25
            $table->date('date');
            $table->foreignId('account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->string('direction');                                         // in | out
            $table->string('category');                                          // من الإعدادات، أو transfer
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->decimal('exchange_rate', 15, 4)->default(1);
            $table->decimal('amount_usd', 15, 2);                                // للتقارير
            $table->string('description')->nullable();
            $table->string('attachment')->nullable();

            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('deal_payment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();      // الموظف المستفيد (راتب / عمولة)
            $table->uuid('transfer_group')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['date', 'direction']);
        });

        // ===== حسابات افتراضية =====
        $now = now();
        DB::table('finance_accounts')->insert([
            ['name' => 'الصندوق — دولار', 'type' => 'cash', 'currency' => 'USD', 'opening_balance' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'الصندوق — ليرة', 'type' => 'cash', 'currency' => 'SYP', 'opening_balance' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ===== تصنيفات الإيرادات والمصاريف =====
        $rows = [];
        $add = function (string $list, array $items) use (&$rows, $now) {
            $sort = 0;
            foreach ($items as $key => $item) {
                $item = is_array($item) ? $item : ['label' => $item];
                $rows[] = ['list_name' => $list, 'key' => $key, 'label' => $item['label'], 'parent_key' => null, 'code' => null,
                    'color' => $item['color'] ?? null, 'sort' => $sort++, 'is_active' => true, 'is_system' => $item['system'] ?? false,
                    'created_at' => $now, 'updated_at' => $now];
            }
        };

        $add('income_category', [
            'commission' => ['label' => 'عمولات صفقات', 'color' => 'success', 'system' => true],
            'other_income' => ['label' => 'إيرادات أخرى', 'color' => 'info'],
            'capital' => ['label' => 'رأس مال / إيداع شريك', 'color' => 'gray'],
        ]);

        $add('expense_category', [
            'rent' => ['label' => 'إيجار المكتب', 'color' => 'danger'],
            'salaries' => ['label' => 'رواتب', 'color' => 'danger'],
            'agent_commission' => ['label' => 'عمولات الوكلاء', 'color' => 'warning', 'system' => true],
            'marketing' => ['label' => 'تسويق وإعلانات', 'color' => 'info'],
            'utilities' => ['label' => 'فواتير (كهرباء، إنترنت، هاتف)', 'color' => 'gray'],
            'transport' => ['label' => 'مواصلات', 'color' => 'gray'],
            'fees' => ['label' => 'رسوم حكومية', 'color' => 'gray'],
            'maintenance' => ['label' => 'صيانة ومستلزمات', 'color' => 'gray'],
            'other' => ['label' => 'أخرى', 'color' => 'gray'],
        ]);

        DB::table('lookups')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_transactions');
        Schema::dropIfExists('finance_accounts');
        Schema::dropIfExists('exchange_rates');
        DB::table('lookups')->whereIn('list_name', ['income_category', 'expense_category'])->delete();
    }
};
