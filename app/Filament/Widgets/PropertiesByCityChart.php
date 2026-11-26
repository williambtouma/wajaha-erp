<?php

namespace App\Filament\Widgets;

use App\Models\Lookup;
use App\Models\Property;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/** رسم أعمدة: العقارات حسب المدينة (متاح / غير متاح) */
class PropertiesByCityChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'العقارات حسب المدينة';

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = Property::query()
            ->selectRaw('city, is_available, count(*) as total')
            ->groupBy('city', 'is_available')
            ->get();

        $cities = $rows->pluck('city')->unique()->values();
        $series = fn (bool $available) => $cities->map(fn ($city) => (int) $rows
            ->where('city', $city)
            ->filter(fn ($row) => (bool) $row->is_available === $available)
            ->sum('total'))->all();

        return [
            'datasets' => [
                [
                    'label' => 'متاح',
                    'data' => $series(true),
                    'backgroundColor' => '#043c36',
                    'borderRadius' => 6,
                ],
                [
                    'label' => 'غير متاح',
                    'data' => $series(false),
                    'backgroundColor' => '#c4b596',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $cities->map(fn ($city) => Lookup::label('city', $city))->all(),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'bottom', 'rtl' => true],
            ],
            'scales' => [
                'x' => ['stacked' => true, 'grid' => ['display' => false]],
                'y' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
