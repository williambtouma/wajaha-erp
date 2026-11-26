<?php

namespace App\Filament\Resources\Deals;

use App\Filament\Resources\Deals\Pages\CreateDeal;
use App\Filament\Resources\Deals\Pages\EditDeal;
use App\Filament\Resources\Deals\Pages\ListDeals;
use App\Filament\Resources\Deals\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Deals\Schemas\DealForm;
use App\Filament\Resources\Deals\Tables\DealsTable;
use App\Models\Deal;
use App\Models\DealPayment;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DealResource extends Resource
{
    protected static ?string $model = Deal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $modelLabel = 'صفقة';

    protected static ?string $pluralModelLabel = 'الصفقات';

    protected static ?string $recordTitleAttribute = 'reference';

    public const NAV_GROUP = 'الصفقات';

    /** من لا يرى كل العملاء يرى صفقاته فقط */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->when(! $user?->canSeeAllLeads(), fn (Builder $q) => $q->where('agent_id', $user?->id));
    }

    public static function form(Schema $schema): Schema
    {
        return DealForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DealsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }

    public static function getNavigationItems(): array
    {
        $listUrl = static::getUrl('index');
        $onList = fn (): bool => request()->routeIs(static::getRouteBaseName() . '.index');

        $inProgress = static::getEloquentQuery()->where('status', 'in_progress')->count();
        $duePayments = DealPayment::query()
            ->whereNull('paid_at')
            ->whereNotNull('due_date')
            ->where('due_date', '<=', now()->addDays(7))
            ->whereHas('deal', fn ($q) => $q->whereIn('id', static::getEloquentQuery()->select('id'))->where('status', '!=', 'cancelled'))
            ->count();

        return [
            NavigationItem::make('الصفقات قيد السداد')
                ->group(self::NAV_GROUP)
                ->icon(Heroicon::OutlinedClock)
                ->url($listUrl . '?status=in_progress')
                ->badge($inProgress ?: null, 'warning')
                ->isActiveWhen(fn (): bool => $onList() && request()->query('status') === 'in_progress')
                ->sort(0),

            NavigationItem::make('دفعات مستحقة')
                ->group(self::NAV_GROUP)
                ->icon(Heroicon::OutlinedBanknotes)
                ->url(static::getUrl('payments'))
                ->badge($duePayments ?: null, 'danger')
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName() . '.payments'))
                ->sort(1),

            NavigationItem::make('كل الصفقات')
                ->group(self::NAV_GROUP)
                ->icon(Heroicon::OutlinedDocumentCheck)
                ->url($listUrl)
                ->isActiveWhen(fn (): bool => $onList() && blank(request()->query('status')))
                ->sort(2),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeals::route('/'),
            'payments' => \App\Filament\Resources\Deals\Pages\DuePayments::route('/payments'),
            'create' => CreateDeal::route('/create'),
            'edit' => EditDeal::route('/{record}/edit'),
        ];
    }
}
