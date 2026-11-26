<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Models\Lookup;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/** قمع المبيعات: عدد العملاء في كل مرحلة */
class LeadsByStageChart extends ChartWidget
{
    protected static ?int $sort = 6;

    protected ?string $heading = 'قمع المبيعات';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('viewAny', Lead::class);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $counts = LeadResource::getEloquentQuery()
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $stages = Lookup::options('lead_stage');
        $colors = ['primary' => '#043c36', 'success' => '#16a34a', 'warning' => '#a79570', 'danger' => '#dc2626', 'info' => '#37655e', 'gray' => '#9ca3af'];

        return [
            'datasets' => [[
                'label' => 'العملاء',
                'data' => collect($stages)->keys()->map(fn ($stage) => (int) ($counts[$stage] ?? 0))->all(),
                'backgroundColor' => collect($stages)->keys()->map(fn ($stage) => $colors[Lookup::color('lead_stage', $stage)] ?? '#9ca3af')->all(),
                'borderRadius' => 6,
            ]],
            'labels' => array_values($stages),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                'y' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
