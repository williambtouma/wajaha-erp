<?php

namespace App\Providers\Filament;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()

            // ===== هوية وجاهة =====
            ->brandName('وجاهة')
            ->brandLogo(asset('images/logo.png'))
            ->brandLogoHeight('4.5rem')
            ->favicon(asset('images/logo.png'))
            ->font('Noto Kufi Arabic')
            ->darkMode(false)
            ->sidebarCollapsibleOnDesktop()
            // ترتيب مجموعات القائمة الجانبية
            ->navigationGroups([
                'العقارات المتاحة',
                'العقارات غير المتاحة',
                'كل العقارات',
                'إدارة العملاء',
                'الصفقات',
                'المالية',
                'الإعدادات',
            ])
            // تصميم وجاهة: القائمة الجانبية + ألوان الصفوف (public/css/wajaha.css)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => '<link rel="stylesheet" href="' . asset('css/wajaha.css') . '?v=' . @filemtime(public_path('css/wajaha.css')) . '">')
            // الخرائط: Leaflet + OpenStreetMap (مجاني، بدون مفتاح)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => '
                <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
                <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
                <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
                <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
                <script src="' . asset('js/wajaha-map.js') . '?v=' . @filemtime(public_path('js/wajaha-map.js')) . '"></script>')
            ->colors([
                // أخضر وجاهة: الدرجة 600 = #043C36 (لون الأزرار)
                'primary' => [
                    50 => 'oklch(0.970 0.011 183.6)',
                    100 => 'oklch(0.930 0.016 183.6)',
                    200 => 'oklch(0.860 0.025 183.6)',
                    300 => 'oklch(0.760 0.033 183.6)',
                    400 => 'oklch(0.620 0.044 183.6)',
                    500 => 'oklch(0.470 0.052 183.6)',
                    600 => 'oklch(0.321 0.055 183.6)',
                    700 => 'oklch(0.281 0.055 183.6)',
                    800 => 'oklch(0.251 0.052 183.6)',
                    900 => 'oklch(0.221 0.049 183.6)',
                    950 => 'oklch(0.191 0.044 183.6)',
                ],
            ])

            // ===== الأدوار والصلاحيات (Filament Shield) =====
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('الإعدادات')
                    ->navigationLabel('الأدوار والصلاحيات')
                    ->navigationIcon('heroicon-o-shield-check')
                    ->navigationSort(80)
                    ->modelLabel('دور')
                    ->pluralModelLabel('الأدوار')
                    ->gridColumns([
                        'default' => 1,
                        'lg' => 2,
                    ])
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 4,
                    ])
                    ->resourceCheckboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 4,
                    ]),
            ])

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // الويدجتات في app/Filament/Widgets تُكتشف تلقائياً
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
