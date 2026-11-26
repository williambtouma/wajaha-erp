<?php

namespace App\Filament\Resources\Finance\Transactions;

use App\Filament\Resources\Finance\Transactions\Pages\ManageFinanceTransactions;
use App\Models\ExchangeRate;
use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Models\Lookup;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FinanceTransactionResource extends Resource
{
    protected static ?string $model = FinanceTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $modelLabel = 'حركة مالية';

    protected static ?string $pluralModelLabel = 'الحركات المالية';

    public const NAV_GROUP = 'المالية';

    public static function form(Schema $schema): Schema
    {
        $account = fn (Get $get): ?FinanceAccount => FinanceAccount::find($get('account_id'));
        $isForeign = fn (Get $get): bool => ($account($get)?->currency ?? 'USD') !== 'USD';

        return $schema
            ->columns(2)
            ->components([
                ToggleButtons::make('direction')
                    ->label('النوع')
                    ->options(FinanceTransaction::DIRECTIONS)
                    ->colors(['in' => 'success', 'out' => 'danger'])
                    ->icons(['in' => 'heroicon-m-arrow-down-left', 'out' => 'heroicon-m-arrow-up-right'])
                    ->default(fn (): string => request()->query('direction', 'out'))
                    ->inline()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('category', null))
                    ->disabled(fn (?FinanceTransaction $record): bool => $record?->category === FinanceTransaction::TRANSFER),

                DatePicker::make('date')
                    ->label('التاريخ')
                    ->native(false)
                    ->default(now())
                    ->required(),

                Select::make('category')
                    ->label('التصنيف')
                    ->options(fn (Get $get): array => FinanceTransaction::categoryOptions($get('direction')))
                    ->required()
                    ->live()
                    ->hidden(fn (?FinanceTransaction $record): bool => $record?->category === FinanceTransaction::TRANSFER),

                Select::make('account_id')
                    ->label('الصندوق / الحساب')
                    ->options(fn (): array => FinanceAccount::options())
                    ->default(fn () => array_key_first(FinanceAccount::options()))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set, Get $get) => $set('exchange_rate', $isForeign($get) ? ExchangeRate::current($account($get)->currency) : 1)),

                TextInput::make('amount')
                    ->label('المبلغ')
                    ->numeric()
                    ->minValue(0.01)
                    ->required()
                    ->suffix(fn (Get $get): ?string => Lookup::code('currency', $account($get)?->currency)),

                TextInput::make('exchange_rate')
                    ->label('سعر الصرف (ليرة لكل 1 دولار)')
                    ->numeric()
                    ->default(fn () => ExchangeRate::current())
                    ->visible($isForeign)
                    ->required($isForeign)
                    ->helperText(fn () => ExchangeRate::current() ? null : 'سجّل سعر الصرف من: المالية ← سعر الصرف'),

                Select::make('user_id')
                    ->label('الموظف')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->visible(fn (Get $get): bool => in_array($get('category'), ['salaries', 'agent_commission'])),

                TextInput::make('description')
                    ->label('البيان')
                    ->placeholder('مثال: إيجار شهر 11 / إعلان فيسبوك حملة أبراج النخيل')
                    ->columnSpanFull(),

                FileUpload::make('attachment')
                    ->label('الفاتورة / الإيصال')
                    ->disk('local')
                    ->directory('finance')
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->maxSize(10240)
                    ->downloadable()
                    ->openable()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(fn (Builder $q) => $q->with(['account', 'user', 'creator']))
            ->columns([
                TextColumn::make('date')
                    ->label('التاريخ')
                    ->date('Y/m/d')
                    ->sortable()
                    ->description(fn (FinanceTransaction $record): ?string => $record->reference),

                TextColumn::make('category')
                    ->label('التصنيف')
                    ->badge()
                    ->formatStateUsing(fn (FinanceTransaction $record): string => $record->categoryLabel())
                    ->color(fn (FinanceTransaction $record): string => $record->categoryColor()),

                TextColumn::make('description')
                    ->label('البيان')
                    ->limit(45)
                    ->placeholder('—')
                    ->searchable()
                    ->description(fn (FinanceTransaction $record): ?string => $record->user?->name),

                TextColumn::make('account.name')
                    ->label('الحساب'),

                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->sortable()
                    ->weight('bold')
                    ->color(fn (FinanceTransaction $record): string => $record->direction === 'in' ? 'success' : 'danger')
                    ->formatStateUsing(fn (FinanceTransaction $record): string => ($record->direction === 'in' ? '+ ' : '− ') . Lookup::money($record->amount, $record->currency))
                    ->description(fn (FinanceTransaction $record): ?string => $record->currency !== 'USD'
                        ? '≈ ' . Lookup::money($record->amount_usd, 'USD') . ' (سعر ' . number_format((float) $record->exchange_rate) . ')'
                        : null),

                TextColumn::make('creator.name')
                    ->label('بواسطة')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('direction')->label('النوع')->options(FinanceTransaction::DIRECTIONS),
                SelectFilter::make('account_id')->label('الحساب')->options(fn (): array => FinanceAccount::options()),
                SelectFilter::make('category')
                    ->label('التصنيف')
                    ->options(fn (): array => [
                        ...Lookup::options('income_category'),
                        ...Lookup::options('expense_category'),
                        FinanceTransaction::TRANSFER => 'تحويل بين الحسابات',
                    ]),
                Filter::make('period')
                    ->label('الفترة')
                    ->schema([
                        DatePicker::make('from')->label('من')->native(false),
                        DatePicker::make('until')->label('إلى')->native(false),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
                        ->when($data['until'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (FinanceTransaction $record): bool => ! $record->deal_payment_id && $record->category !== FinanceTransaction::TRANSFER),
                DeleteAction::make()
                    ->visible(fn (FinanceTransaction $record): bool => ! $record->deal_payment_id),
            ]);
    }

    public static function getNavigationItems(): array
    {
        $url = static::getUrl('index');
        $onList = fn (): bool => request()->routeIs(static::getRouteBaseName() . '.index');

        return [
            NavigationItem::make('المصاريف')
                ->group(self::NAV_GROUP)
                ->icon(Heroicon::OutlinedArrowUpRight)
                ->url($url . '?direction=out')
                ->isActiveWhen(fn (): bool => $onList() && request()->query('direction') === 'out')
                ->sort(1),

            NavigationItem::make('الإيرادات')
                ->group(self::NAV_GROUP)
                ->icon(Heroicon::OutlinedArrowDownLeft)
                ->url($url . '?direction=in')
                ->isActiveWhen(fn (): bool => $onList() && request()->query('direction') === 'in')
                ->sort(2),

            NavigationItem::make('كل الحركات')
                ->group(self::NAV_GROUP)
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->url($url)
                ->isActiveWhen(fn (): bool => $onList() && blank(request()->query('direction')))
                ->sort(3),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFinanceTransactions::route('/'),
        ];
    }
}
