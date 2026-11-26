<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->unique();      // رقم العقار مثل S-VIL-3706
            $table->string('title');                                 // عنوان الإعلان
            $table->text('description')->nullable();                 // وصف العقار

            $table->string('category');                              // نوع العقار: residential | commercial
            $table->string('subtype');                               // التصنيف الفرعي
            $table->string('transaction');                           // نوع المعاملة: sale | rent
            $table->string('status')->default('available');          // متاح / محجوز / مباع / مؤجر
            $table->boolean('is_featured')->default(false);           // مميز

            $table->string('city');                                  // المدينة
            $table->string('area')->nullable();                      // المنطقة

            $table->decimal('price', 15, 2);                         // السعر
            $table->string('currency', 3)->default('USD');           // USD | SYP

            $table->decimal('built_area', 10, 2)->nullable();        // مساحة العقار
            $table->decimal('land_area', 10, 2)->nullable();         // مساحة الأرض
            $table->unsignedSmallInteger('bedrooms')->nullable();    // غرف النوم
            $table->unsignedSmallInteger('bathrooms')->nullable();   // الحمامات
            $table->unsignedSmallInteger('year_built')->nullable();  // سنة البناء
            $table->string('condition')->nullable();                 // new | existing | under_construction
            $table->string('furnishing')->nullable();                // unfurnished | semi_furnished | furnished

            $table->json('images')->nullable();                      // صور العقار

            $table->string('owner_name')->nullable();                // المالك
            $table->string('owner_phone')->nullable();
            $table->string('ownership_type')->nullable();            // owner | representative

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
