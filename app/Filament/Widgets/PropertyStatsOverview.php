<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Properties\PropertyResource;
use App\Models\Property;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/** بطاقات الأرقام الرئيسية */
class PropertyStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $total = Property::count();
        $available = Property::where('is_available', true)->count();
        $unavailable = $total - $available;
        $percent = $total ? round($available / $total * 100) : 0;

        $forSale = Property::where('is_available', true)->where('transaction', 'sale');
        $saleCount = (clone $forSale)->count();
        $saleValueUsd = (clone $forSale)->where('currency', 'USD')->sum('price');
        $rentCount = Property::where('is_available', true)->where('transaction', 'rent')->count();

        $needsFollowUp = Property::where('is_available', true)
            ->where(DB::raw('coalesce(availability_changed_at, created_at)'), '<', now()->subDays(30))
            ->count();

        // عدد العقارات المضافة في آخر 6 أشهر (للرسم الصغير)
        $monthly = collect(range(5, 0))->map(fn (int $i) => Property::whereBetween('created_at', [
            now()->subMonths($i)->startOfMonth(),
            now()->subMonths($i)->endOfMonth(),
        ])->count())->all();

        $url = fn (array $query = []) => PropertyResource::getUrl('index', $query);

        return [
            Stat::make('إجمالي العقارات', number_format($total))
                ->description('المضاف خلال آخر 6 أشهر')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($monthly)
                ->color('primary')
                ->url($url()),

            Stat::make('العقارات المتاحة', number_format($available))
                ->description("{$percent}% من إجمالي العقارات")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->url($url(['availability' => 'available'])),

            Stat::make('العقارات غير المتاحة', number_format($unavailable))
                ->description('مباع، مؤجر، محجوز أو مسحوب')
                ->descriptionIcon('heroicon-m-no-symbol')
                ->color('danger')
                ->url($url(['availability' => 'unavailable'])),

            Stat::make('متاح للبيع', number_format($saleCount))
                ->description("بقيمة \u{2066}\$" . number_format($saleValueUsd) . "\u{2069}")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary')
                ->url($url(['availability' => 'available', 'transaction' => 'sale'])),

            Stat::make('متاح للإيجار', number_format($rentCount))
                ->description('عقارات جاهزة للتأجير')
                ->descriptionIcon('heroicon-m-key')
                ->color('info')
                ->url($url(['availability' => 'available', 'transaction' => 'rent'])),

            Stat::make('بحاجة لتأكيد التوفر', number_format($needsFollowUp))
                ->description('لم يُحدَّث توفرها منذ أكثر من 30 يوماً')
                ->descriptionIcon('heroicon-m-clock')
                ->color($needsFollowUp ? 'warning' : 'gray')
                ->url($url(['availability' => 'available'])),
        ];
    }
}
