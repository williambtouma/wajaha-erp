<?php

namespace App\Filament\Resources\Properties;

use App\Filament\Resources\Properties\Pages\CreateProperty;
use App\Filament\Resources\Properties\Pages\EditProperty;
use App\Filament\Resources\Properties\Pages\ListProperties;
use App\Filament\Resources\Properties\Schemas\PropertyForm;
use App\Filament\Resources\Properties\Tables\PropertiesTable;
use App\Models\Lookup;
use App\Models\Property;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PropertyResource extends Resource
{
    protected static ?string $model = Property::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static ?string $modelLabel = 'عقار';

    protected static ?string $pluralModelLabel = 'العقارات';

    protected static ?string $navigationLabel = 'العقارات';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return PropertyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PropertiesTable::configure($table);
    }

    /**
     * شجرة القائمة الجانبية:
     * العقارات ← كل العقارات / سكني (للبيع، للإيجار) / تجاري (للبيع، للإيجار)
     * الأنواع والمعاملات تُقرأ من الإعدادات.
     */
    public static function getNavigationItems(): array
    {
        $group = 'العقارات';
        $listUrl = static::getUrl('index');
        $onList = fn (): bool => request()->routeIs(static::getRouteBaseName() . '.index');
        $is = fn (?string $category, ?string $transaction): bool => $onList()
            && request()->query('category') === $category
            && request()->query('transaction') === $transaction;

        $counts = Property::query()
            ->selectRaw('category, transaction, count(*) as total')
            ->groupBy('category', 'transaction')
            ->get();
        $count = fn (?string $category = null, ?string $transaction = null): int => (int) $counts
            ->when($category, fn ($c) => $c->where('category', $category))
            ->when($transaction, fn ($c) => $c->where('transaction', $transaction))
            ->sum('total');

        $items = [
            NavigationItem::make('كل العقارات')
                ->group($group)
                ->icon(Heroicon::OutlinedHomeModern)
                ->url($listUrl)
                ->badge($count() ?: null)
                ->isActiveWhen(fn (): bool => $is(null, null))
                ->sort(1),
        ];

        $sort = 2;
        foreach (Lookup::options('category') as $categoryKey => $categoryLabel) {
            $items[] = NavigationItem::make($categoryLabel)
                ->group($group)
                ->icon($categoryKey === 'commercial' ? Heroicon::OutlinedBuildingStorefront : Heroicon::OutlinedHome)
                ->url($listUrl . '?' . http_build_query(['category' => $categoryKey]))
                ->badge($count($categoryKey) ?: null)
                ->isActiveWhen(fn (): bool => $is($categoryKey, null))
                ->sort($sort++);

            foreach (Lookup::options('transaction') as $transactionKey => $transactionLabel) {
                $items[] = NavigationItem::make($transactionLabel)
                    ->group($group)
                    ->parentItem($categoryLabel)
                    ->url($listUrl . '?' . http_build_query(['category' => $categoryKey, 'transaction' => $transactionKey]))
                    ->badge($count($categoryKey, $transactionKey) ?: null)
                    ->isActiveWhen(fn (): bool => $is($categoryKey, $transactionKey))
                    ->sort($sort++);
            }
        }

        return $items;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProperties::route('/'),
            'create' => CreateProperty::route('/create'),
            'edit' => EditProperty::route('/{record}/edit'),
        ];
    }
}
