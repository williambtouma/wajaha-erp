<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('readiness')->nullable()->after('transaction');        // ready | under_construction (للبيع فقط)
            $table->date('expected_delivery')->nullable()->after('readiness');    // تاريخ التسليم المتوقع
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['readiness', 'expected_delivery']);
        });
    }
};
