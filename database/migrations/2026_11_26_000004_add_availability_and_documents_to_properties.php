<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ===== التوفر + المستندات =====
        Schema::table('properties', function (Blueprint $table) {
            $table->boolean('is_available')->default(true)->after('transaction');
            $table->string('unavailable_reason')->nullable()->after('is_available');
            $table->text('availability_note')->nullable()->after('unavailable_reason');
            $table->timestamp('availability_changed_at')->nullable()->after('availability_note');
            $table->foreignId('availability_changed_by')->nullable()->after('availability_changed_at')
                ->constrained('users')->nullOnDelete();
            $table->json('documents')->nullable()->after('images');
        });

        // نقل الحالة القديمة إلى نظام التوفر الجديد
        DB::table('properties')->where('status', 'available')->update(['is_available' => true]);
        DB::table('properties')->where('status', '!=', 'available')->update([
            'is_available' => false,
            'unavailable_reason' => DB::raw('status'),
        ]);

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        // ===== سجل تغييرات التوفر =====
        Schema::create('property_availability_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_available');
            $table->string('reason')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // ===== الإعدادات: "حالات التوفر" تصبح "أسباب عدم التوفر" =====
        DB::table('lookups')->where('list_name', 'status')->where('key', 'available')->delete();
        DB::table('lookups')->where('list_name', 'status')->update(['list_name' => 'unavailable_reason', 'is_system' => false]);

        $now = now();
        DB::table('lookups')->insert([
            ['list_name' => 'unavailable_reason', 'key' => 'withdrawn', 'label' => 'سحبه المالك', 'color' => 'danger', 'sort' => 10, 'is_system' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['list_name' => 'unavailable_reason', 'key' => 'other', 'label' => 'أخرى', 'color' => 'gray', 'sort' => 11, 'is_system' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            ['list_name' => 'document_type', 'key' => 'deed', 'label' => 'سند ملكية', 'color' => null, 'sort' => 0, 'is_system' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['list_name' => 'document_type', 'key' => 'power_of_attorney', 'label' => 'وكالة', 'color' => null, 'sort' => 1, 'is_system' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['list_name' => 'document_type', 'key' => 'contract', 'label' => 'عقد', 'color' => null, 'sort' => 2, 'is_system' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['list_name' => 'document_type', 'key' => 'floor_plan', 'label' => 'مخطط هندسي', 'color' => null, 'sort' => 3, 'is_system' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['list_name' => 'document_type', 'key' => 'photos', 'label' => 'صور إضافية', 'color' => null, 'sort' => 4, 'is_system' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['list_name' => 'document_type', 'key' => 'other', 'label' => 'أخرى', 'color' => null, 'sort' => 5, 'is_system' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('status')->default('available');
        });
        DB::table('properties')->where('is_available', false)->update(['status' => DB::raw("coalesce(unavailable_reason, 'reserved')")]);

        Schema::dropIfExists('property_availability_logs');

        Schema::table('properties', function (Blueprint $table) {
            $table->dropConstrainedForeignId('availability_changed_by');
            $table->dropColumn(['is_available', 'unavailable_reason', 'availability_note', 'availability_changed_at', 'documents']);
        });

        DB::table('lookups')->where('list_name', 'document_type')->delete();
        DB::table('lookups')->where('list_name', 'unavailable_reason')->whereIn('key', ['withdrawn', 'other'])->delete();
        DB::table('lookups')->where('list_name', 'unavailable_reason')->update(['list_name' => 'status']);
    }
};
