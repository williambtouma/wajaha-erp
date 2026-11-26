<?php

namespace App\Filament\Finance\Widgets;

use App\Models\FinanceTransaction;
use App\Models\Lookup;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class IncomeByCategoryChart extends ChartWidget
{
    public ?int $year = null;

    protected ?string $maxHeight = '280px';

    public function getHeading(): ?string
    {
        return 'الإيرادات حسب التصنيف';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $rows = FinanceTransaction::query()
            ->real()
            ->inYear($this->year ?? now()->year)
            ->where('direction', 'in')
            ->selectRaw('category, sum(amount_usd) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return [
            'datasets' => [[
                'data' => $rows->map(fn ($r) => round((float) $r->total, 2))->all(),
                'backgroundColor' => ['#043c36', '#a79570', '#37655e', '#c4b596', '#698f89'],
                'borderColor' => '#ffffff',
                'borderWidth' => 2,
            ]],
            'labels' => $rows->map(fn ($r) => Lookup::label('income_category', $r->category))->all(),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return [
            'cutout' => '60%',
            'plugins' => ['legend' => ['position' => 'bottom', 'rtl' => true]],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
