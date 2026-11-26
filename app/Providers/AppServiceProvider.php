<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ===== صلاحيات خاصة (تظهر في تبويب "صلاحيات مخصصة" بصفحة الدور) =====
        config([
            'filament-shield.custom_permissions' => [
                'ViewAll:Lead' => 'العملاء: رؤية كل العملاء (وليس عملاءه فقط)',
                'Assign:Lead' => 'العملاء: توزيع العملاء على الوكلاء',
                'ChangeAvailability:Property' => 'العقارات: تغيير التوفر (متاح / غير متاح)',
            ],
            'filament-shield.shield_resource.tabs.custom_permissions' => true,
        ]);
    }
}
