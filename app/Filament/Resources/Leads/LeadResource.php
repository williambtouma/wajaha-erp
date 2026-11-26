<?php

namespace App\Filament\Resources\Leads;

use App\Filament\Resources\Leads\Pages\CreateLead;
use App\Filament\Resources\Leads\Pages\EditLead;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Leads\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\Leads\Schemas\LeadForm;
use App\Filament\Resources\Leads\Tables\LeadsTable;
use App\Models\Lead;
use App\Models\Lookup;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $modelLabel = 'عميل';

    protected static ?string $pluralModelLabel = 'العملاء';

    protected static ?string $recordTitleAttribute = 'name';

    public const NAV_GROUP = 'إدارة العملاء';

    /** الوكيل يرى عملاءه فقط */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function form(Schema $schema): Schema
    {
        return LeadForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeadsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
        ];
    }

    /**
     * القائمة الجانبية: إدارة العملاء
     * متابعات اليوم / العملاء النشطون (حسب النوع) / الصفقات المغلقة
     */
    public static function getNavigationItems(): array
    {
        $listUrl = static::getUrl('index');
        $url = fn (array $query): string => ($query = array_filter($query)) ? $listUrl . '?' . http_build_query($query) : $listUrl;
        $onList = fn (): bool => request()->routeIs(static::getRouteBaseName() . '.index');
        $is = fn (?string $view, ?string $type = null): bool => $onList()
            && request()->query('view') === $view
            && request()->query('type') === $type;

        $base = fn () => static::getEloquentQuery();
        $due = $base()->followUpDue()->count();
        $openByType = $base()->open()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');
        $closed = $base()->whereIn('stage', Lead::CLOSED_STAGES)->count();

        $items = [
            NavigationItem::make('متابعات اليوم')
                ->group(self::NAV_GROUP)
                ->icon(Heroicon::OutlinedBellAlert)
                ->url($url(['view' => 'due']))
                ->badge($due ?: null, 'danger')
                ->isActiveWhen(fn (): bool => $is('due'))
                ->sort(0),

            NavigationItem::make('العملاء النشطون')
                ->group(self::NAV_GROUP)
                ->icon(Heroicon::OutlinedUserGroup)
                ->url($url(['view' => 'open']))
                ->badge($openByType->sum() ?: null, 'primary')
                ->isActiveWhen(fn (): bool => $is('open'))
                ->sort(1),
        ];

        $sort = 2;
        foreach (Lookup::options('lead_type') as $typeKey => $typeLabel) {
            $items[] = NavigationItem::make($typeLabel)
                ->group(self::NAV_GROUP)
                ->parentItem('العملاء النشطون')
                ->url($url(['view' => 'open', 'type' => $typeKey]))
                ->badge(($openByType[$typeKey] ?? 0) ?: null, 'gray')
                ->isActiveWhen(fn (): bool => $is('open', $typeKey))
                ->sort($sort++);
        }

        $items[] = NavigationItem::make('الصفقات المغلقة')
            ->group(self::NAV_GROUP)
            ->icon(Heroicon::OutlinedArchiveBox)
            ->url($url(['view' => 'closed']))
            ->badge($closed ?: null, 'gray')
            ->isActiveWhen(fn (): bool => $is('closed'))
            ->sort(50);

        $items[] = NavigationItem::make('كل العملاء')
            ->group(self::NAV_GROUP)
            ->icon(Heroicon::OutlinedListBullet)
            ->url($listUrl)
            ->isActiveWhen(fn (): bool => $is(null))
            ->sort(51);

        return $items;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
            'create' => CreateLead::route('/create'),
            'edit' => EditLead::route('/{record}/edit'),
        ];
    }
}
