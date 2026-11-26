<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Models\Lookup;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/** من أين يأتي العملاء؟ */
class LeadsBySourceChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'مصادر العملاء';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('viewAny', Lead::class);
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $rows = LeadResource::getEloquentQuery()
            ->selectRaw("coalesce(source, 'other') as source, count(*) as total")
            ->groupBy('source')
            ->orderByDesc('total')
            ->get();

        return [
            'datasets' => [[
                'data' => $rows->pluck('total')->all(),
                'backgroundColor' => ['#043c36', '#a79570', '#37655e', '#c4b596', '#698f89', '#e2d8c3', '#9bb8b3', '#5c4f36'],
                'borderColor' => '#ffffff',
                'borderWidth' => 2,
            ]],
            'labels' => $rows->map(fn ($row) => Lookup::label('lead_source', $row->source))->all(),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return [
            'cutout' => '62%',
            'plugins' => ['legend' => ['position' => 'bottom', 'rtl' => true]],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
