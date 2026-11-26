<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** أرقام العملاء (كل مستخدم يرى أرقام العملاء المسموح له بها) */
class LeadStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'العملاء';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('viewAny', Lead::class);
    }

    protected function getStats(): array
    {
        $base = fn () => LeadResource::getEloquentQuery();

        $newThisMonth = $base()->where('created_at', '>=', now()->startOfMonth())->count();
        $open = $base()->open()->count();
        $due = $base()->followUpDue()->count();
        $overdue = $base()->open()->where('next_follow_up_at', '<', now())->count();

        $closedThisMonth = $base()->whereIn('stage', Lead::CLOSED_STAGES)->where('stage_changed_at', '>=', now()->startOfMonth());
        $wonThisMonth = (clone $closedThisMonth)->where('stage', 'won')->count();
        $closedCount = (clone $closedThisMonth)->count();
        $conversion = $closedCount ? round($wonThisMonth / $closedCount * 100) : 0;

        // العملاء الجدد في آخر 7 أيام (للرسم الصغير)
        $daily = collect(range(6, 0))->map(fn (int $i) => $base()->whereDate('created_at', now()->subDays($i))->count())->all();

        $url = fn (array $query = []) => LeadResource::getUrl('index', $query);

        return [
            Stat::make('عملاء جدد هذا الشهر', number_format($newThisMonth))
                ->description('آخر 7 أيام')
                ->descriptionIcon('heroicon-m-user-plus')
                ->chart($daily)
                ->color('primary')
                ->url($url()),

            Stat::make('العملاء النشطون', number_format($open))
                ->description('قيد المتابعة')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info')
                ->url(LeadResource::getUrl('board')),

            Stat::make('متابعات اليوم', number_format($due))
                ->description($overdue ? "منها {$overdue} متأخرة" : 'لا يوجد متأخر 👌')
                ->descriptionIcon($overdue ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check')
                ->color($overdue ? 'danger' : ($due ? 'warning' : 'success'))
                ->url($url(['view' => 'due'])),

            Stat::make('صفقات هذا الشهر', number_format($wonThisMonth))
                ->description("نسبة النجاح {$conversion}% من المغلقة")
                ->descriptionIcon('heroicon-m-trophy')
                ->color('success')
                ->url($url(['view' => 'closed'])),
        ];
    }
}
