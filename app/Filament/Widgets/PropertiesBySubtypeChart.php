<?php

namespace App\Filament\Widgets;

use App\Models\Lookup;
use App\Models\Property;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/** رسم دائري: توزيع العقارات حسب التصنيف */
class PropertiesBySubtypeChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'توزيع العقارات حسب التصنيف';

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $rows = Property::query()
            ->selectRaw('subtype, count(*) as total')
            ->groupBy('subtype')
            ->orderByDesc('total')
            ->get();

        return [
            'datasets' => [[
                'data' => $rows->pluck('total')->all(),
                'backgroundColor' => ['#043c36', '#a79570', '#37655e', '#c4b596', '#698f89', '#e2d8c3', '#9bb8b3', '#5c4f36'],
                'borderColor' => '#ffffff',
                'borderWidth' => 2,
            ]],
            'labels' => $rows->map(fn ($row) => Lookup::label('subtype', $row->subtype))->all(),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return [
            'cutout' => '62%',
            'plugins' => [
                'legend' => ['position' => 'bottom', 'rtl' => true],
            ],
            'scales' => [
                'x' => ['display' => false],
                'y' => ['display' => false],
            ],
        ];
    }
}
