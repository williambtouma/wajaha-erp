<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ===== الصفقات =====
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->unique();                 // D-12
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();   // المشتري / المستأجر
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('transaction');                                     // sale | rent
            $table->string('status')->default('in_progress');                  // in_progress | completed | cancelled
            $table->date('closed_at');

            // الأطراف
            $table->string('buyer_name');
            $table->string('buyer_phone')->nullable();
            $table->string('seller_name')->nullable();
            $table->string('seller_phone')->nullable();

            // السعر والعقد
            $table->decimal('price', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->date('contract_start')->nullable();                        // للإيجار
            $table->date('contract_end')->nullable();

            // العمولة
            $table->string('buyer_commission_type')->default('percent');       // percent | fixed
            $table->decimal('buyer_commission_value', 15, 2)->default(0);
            $table->decimal('buyer_commission_amount', 15, 2)->default(0);
            $table->string('seller_commission_type')->default('percent');
            $table->decimal('seller_commission_value', 15, 2)->default(0);
            $table->decimal('seller_commission_amount', 15, 2)->default(0);
            $table->decimal('total_commission', 15, 2)->default(0);
            $table->decimal('agent_share_percent', 5, 2)->default(0);
            $table->decimal('agent_commission', 15, 2)->default(0);

            $table->json('documents')->nullable();
            $table->text('notes')->nullable();
            $table->text('cancellation_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'closed_at']);
        });

        // ===== الدفعات =====
        Schema::create('deal_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->string('category')->default('deal');                       // deal (دفعات الصفقة) | commission (تحصيل العمولة)
            $table->string('title');                                           // عربون، دفعة أولى، قسط 1...
            $table->decimal('amount', 15, 2);
            $table->date('due_date')->nullable();
            $table->date('paid_at')->nullable();
            $table->string('method')->nullable();                              // cash | transfer | cheque
            $table->string('receipt')->nullable();                             // صورة الإيصال
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['due_date', 'paid_at']);
        });

        // ===== الإعدادات =====
        $now = now();
        $rows = [];
        foreach ([
            'cash' => 'نقداً',
            'transfer' => 'حوالة بنكية',
            'cheque' => 'شيك',
            'other' => 'أخرى',
        ] as $key => $label) {
            $rows[] = ['list_name' => 'payment_method', 'key' => $key, 'label' => $label, 'parent_key' => null, 'code' => null, 'color' => null,
                'sort' => count($rows), 'is_active' => true, 'is_system' => false, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('lookups')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_payments');
        Schema::dropIfExists('deals');
        DB::table('lookups')->where('list_name', 'payment_method')->delete();
    }
};
