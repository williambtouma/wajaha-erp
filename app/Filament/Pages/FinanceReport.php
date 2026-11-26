<?php

namespace App\Filament\Pages;

use App\Filament\Finance\Widgets\ExpensesByCategoryChart;
use App\Filament\Finance\Widgets\FinanceStats;
use App\Filament\Finance\Widgets\IncomeByCategoryChart;
use App\Filament\Finance\Widgets\MonthlyFinanceChart;
use App\Models\FinanceTransaction;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** التقارير المالية: الإيرادات، المصاريف، صافي الربح (بالدولار) */
class FinanceReport extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'المالية';

    protected static ?string $navigationLabel = 'التقارير المالية';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.finance-report';

    public ?int $year = null;

    public function getTitle(): string
    {
        return 'التقارير المالية';
    }

    public function mount(): void
    {
        $this->year = request()->integer('year') ?: now()->year;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FinanceStats::class,
            MonthlyFinanceChart::class,
            IncomeByCategoryChart::class,
            ExpensesByCategoryChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }

    public function getWidgetData(): array
    {
        return ['year' => $this->year];
    }

    protected function getHeaderActions(): array
    {
        $first = (int) (FinanceTransaction::query()->min('date') ? substr(FinanceTransaction::query()->min('date'), 0, 4) : now()->year);
        $years = range(now()->year, min($first, now()->year));

        return [
            ActionGroup::make(collect($years)->map(fn (int $y) => Action::make("year{$y}")
                ->label((string) $y)
                ->url(static::getUrl(['year' => $y])))->all())
                ->label('السنة: ' . $this->year)
                ->icon('heroicon-o-calendar')
                ->button()
                ->color('gray'),
        ];
    }
}
