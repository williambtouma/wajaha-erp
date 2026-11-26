<?php

namespace App\Filament\Finance\Widgets;

use App\Models\FinanceTransaction;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class MonthlyFinanceChart extends ChartWidget
{
    public ?int $year = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    public function getHeading(): ?string
    {
        return 'الإيرادات والمصاريف بالشهر (بالدولار) — ' . ($this->year ?? now()->year);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $year = $this->year ?? now()->year;

        $rows = FinanceTransaction::query()
            ->real()
            ->inYear($year)
            ->where(fn ($q) => $q->where('direction', 'out')->orWhere('category', '!=', 'capital'))
            ->selectRaw('month(date) as m, direction, sum(amount_usd) as total')
            ->groupByRaw('month(date), direction')
            ->get();

        $series = fn (string $dir) => collect(range(1, 12))
            ->map(fn ($m) => round((float) $rows->where('m', $m)->where('direction', $dir)->sum('total'), 2))->all();

        $in = $series('in');
        $out = $series('out');

        return [
            'datasets' => [
                ['type' => 'bar', 'label' => 'الإيرادات', 'data' => $in, 'backgroundColor' => '#043c36', 'borderRadius' => 6],
                ['type' => 'bar', 'label' => 'المصاريف', 'data' => $out, 'backgroundColor' => '#c4b596', 'borderRadius' => 6],
                ['type' => 'line', 'label' => 'صافي الربح', 'data' => array_map(fn ($a, $b) => round($a - $b, 2), $in, $out),
                    'borderColor' => '#a79570', 'backgroundColor' => '#a79570', 'tension' => 0.3],
            ],
            'labels' => ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom', 'rtl' => true]],
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }
}
