<?php

namespace App\Filament\Finance\Widgets;

use App\Models\ExchangeRate;
use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Models\Lookup;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceStats extends StatsOverviewWidget
{
    public ?int $year = null;

    protected function getStats(): array
    {
        $year = $this->year ?? now()->year;
        $usd = fn ($v) => Lookup::money($v, 'USD');

        $base = fn () => FinanceTransaction::query()->real()->inYear($year);
        $income = (float) $base()->where('direction', 'in')->where('category', '!=', 'capital')->sum('amount_usd');
        $expenses = (float) $base()->where('direction', 'out')->sum('amount_usd');
        $net = $income - $expenses;

        $monthBase = fn () => FinanceTransaction::query()->real()->whereYear('date', now()->year)->whereMonth('date', now()->month);
        $monthNet = (float) $monthBase()->where('direction', 'in')->where('category', '!=', 'capital')->sum('amount_usd')
            - (float) $monthBase()->where('direction', 'out')->sum('amount_usd');

        // السيولة الحالية بالدولار
        $liquidity = FinanceAccount::query()->where('is_active', true)->get()
            ->sum(fn (FinanceAccount $a) => ExchangeRate::toUsd($a->balance(), $a->currency));

        $monthly = fn (string $direction) => collect(range(1, 12))->map(fn ($m) => (float) $base()
            ->where('direction', $direction)
            ->when($direction === 'in', fn ($q) => $q->where('category', '!=', 'capital'))
            ->whereMonth('date', $m)->sum('amount_usd'))->all();

        return [
            Stat::make("إيرادات {$year}", $usd($income))
                ->description('عمولات وإيرادات أخرى (بدون رأس المال)')
                ->descriptionIcon('heroicon-m-arrow-down-left')
                ->chart($monthly('in'))
                ->color('success'),

            Stat::make("مصاريف {$year}", $usd($expenses))
                ->description('كل المصاريف بما فيها عمولات الوكلاء')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->chart($monthly('out'))
                ->color('danger'),

            Stat::make("صافي الربح {$year}", $usd($net))
                ->description('هذا الشهر: ' . $usd($monthNet))
                ->descriptionIcon($net >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($net >= 0 ? 'primary' : 'danger'),

            Stat::make('السيولة الحالية', $usd($liquidity))
                ->description(ExchangeRate::current() ? 'كل الصناديق بسعر ' . number_format(ExchangeRate::current()) . ' ل.س' : 'سجّل سعر الصرف!')
                ->descriptionIcon('heroicon-m-wallet')
                ->color('info'),
        ];
    }
}
