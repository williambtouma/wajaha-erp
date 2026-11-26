<?php

namespace App\Filament\Resources\Properties;

use App\Filament\Resources\Properties\Pages\CreateProperty;
use App\Filament\Resources\Properties\Pages\EditProperty;
use App\Filament\Resources\Properties\Pages\ListProperties;
use App\Filament\Resources\Properties\RelationManagers\AvailabilityLogsRelationManager;
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

    /** مجموعات القائمة الجانبية بالترتيب (مسجّلة أيضاً في AdminPanelProvider) */
    public const NAV_GROUPS = [
        'available' => 'العقارات المتاحة',
        'unavailable' => 'العقارات غير المتاحة',
        'all' => 'كل العقارات',
    ];

    /**
     * شجرة القائمة الجانبية:
     * العقارات المتاحة      ← الكل / سكني (للبيع، للإيجار) / تجاري (للبيع، للإيجار)
     * العقارات غير المتاحة  ← نفس التفرع
     * كل العقارات           ← نفس التفرع
     * الأنواع والمعاملات تُقرأ من الإعدادات.
     */
    public static function getNavigationItems(): array
    {
        $listUrl = static::getUrl('index');
        $url = fn (array $query): string => ($query = array_filter($query)) ? $listUrl . '?' . http_build_query($query) : $listUrl;
        $onList = fn (): bool => request()->routeIs(static::getRouteBaseName() . '.index');
        $is = fn (?string $availability, ?string $category, ?string $transaction): bool => $onList()
            && request()->query('availability') === $availability
            && request()->query('category') === $category
            && request()->query('transaction') === $transaction;

        $counts = Property::query()
            ->selectRaw('is_available, category, transaction, count(*) as total')
            ->groupBy('is_available', 'category', 'transaction')
            ->get();
        $count = fn (?string $availability, ?string $category = null, ?string $transaction = null): ?int => (int) $counts
            ->when($availability === 'available', fn ($c) => $c->filter(fn ($row) => (bool) $row->is_available))
            ->when($availability === 'unavailable', fn ($c) => $c->reject(fn ($row) => (bool) $row->is_available))
            ->when($category, fn ($c) => $c->where('category', $category))
            ->when($transaction, fn ($c) => $c->where('transaction', $transaction))
            ->sum('total') ?: null;

        $sections = [
            'available' => ['summary' => 'كل المتاحة', 'icon' => Heroicon::OutlinedCheckCircle, 'color' => 'success'],
            'unavailable' => ['summary' => 'كل غير المتاحة', 'icon' => Heroicon::OutlinedNoSymbol, 'color' => 'danger'],
            'all' => ['summary' => 'عرض الكل', 'icon' => Heroicon::OutlinedHomeModern, 'color' => 'gray'],
        ];

        $items = [];
        $sort = 0;

        foreach ($sections as $sectionKey => $section) {
            $group = self::NAV_GROUPS[$sectionKey];
            $availability = $sectionKey === 'all' ? null : $sectionKey;

            $items[] = NavigationItem::make($section['summary'])
                ->group($group)
                ->icon($section['icon'])
                ->url($url(['availability' => $availability]))
                ->badge($count($availability), $section['color'])
                ->isActiveWhen(fn (): bool => $is($availability, null, null))
                ->sort($sort++);

            foreach (Lookup::options('category') as $categoryKey => $categoryLabel) {
                $items[] = NavigationItem::make($categoryLabel)
                    ->group($group)
                    ->icon($categoryKey === 'commercial' ? Heroicon::OutlinedBuildingStorefront : Heroicon::OutlinedHome)
                    ->url($url(['availability' => $availability, 'category' => $categoryKey]))
                    ->badge($count($availability, $categoryKey), $section['color'])
                    ->isActiveWhen(fn (): bool => $is($availability, $categoryKey, null))
                    ->sort($sort++);

                foreach (Lookup::options('transaction') as $transactionKey => $transactionLabel) {
                    $items[] = NavigationItem::make($transactionLabel)
                        ->group($group)
                        ->parentItem($categoryLabel)
                        ->url($url(['availability' => $availability, 'category' => $categoryKey, 'transaction' => $transactionKey]))
                        ->badge($count($availability, $categoryKey, $transactionKey), $section['color'])
                        ->isActiveWhen(fn (): bool => $is($availability, $categoryKey, $transactionKey))
                        ->sort($sort++);
                }
            }
        }

        return $items;
    }

    public static function getRelations(): array
    {
        return [
            AvailabilityLogsRelationManager::class,
        ];
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
