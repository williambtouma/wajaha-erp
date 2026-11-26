<?php

namespace App\Filament\Resources\Finance\ExchangeRates;

use App\Filament\Resources\Finance\ExchangeRates\Pages\ManageExchangeRates;
use App\Models\ExchangeRate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ExchangeRateResource extends Resource
{
    protected static ?string $model = ExchangeRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'المالية';

    protected static ?string $navigationLabel = 'سعر الصرف';

    protected static ?string $modelLabel = 'سعر صرف';

    protected static ?string $pluralModelLabel = 'أسعار الصرف';

    protected static ?int $navigationSort = 7;

    public static function getNavigationBadge(): ?string
    {
        $rate = ExchangeRate::current();

        return $rate ? number_format($rate) : '!';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return ExchangeRate::current() ? 'gray' : 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                DatePicker::make('date')
                    ->label('التاريخ')
                    ->native(false)
                    ->default(now())
                    ->required(),

                TextInput::make('rate')
                    ->label('سعر الدولار بالليرة السورية')
                    ->helperText('كم ليرة = 1 دولار')
                    ->numeric()
                    ->minValue(1)
                    ->required(),

                TextInput::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('التاريخ')
                    ->date('Y/m/d')
                    ->sortable(),

                TextColumn::make('rate')
                    ->label('1 دولار =')
                    ->weight('bold')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state) . ' ل.س'),

                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExchangeRates::route('/'),
        ];
    }
}
