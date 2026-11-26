<?php

namespace App\Filament\Resources\Finance\Accounts;

use App\Filament\Resources\Finance\Accounts\Pages\ManageFinanceAccounts;
use App\Filament\Resources\Finance\Transactions\FinanceTransactionResource;
use App\Models\ExchangeRate;
use App\Models\FinanceAccount;
use App\Models\Lookup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class FinanceAccountResource extends Resource
{
    protected static ?string $model = FinanceAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = 'المالية';

    protected static ?string $navigationLabel = 'الصناديق والحسابات';

    protected static ?string $modelLabel = 'حساب';

    protected static ?string $pluralModelLabel = 'الصناديق والحسابات';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('الاسم')
                    ->placeholder('مثال: الصندوق — دولار / بنك البركة')
                    ->required(),

                Select::make('type')
                    ->label('النوع')
                    ->options(FinanceAccount::TYPES)
                    ->default('cash')
                    ->required(),

                Select::make('currency')
                    ->label('العملة')
                    ->options(fn (): array => Lookup::options('currency'))
                    ->default('USD')
                    ->required()
                    ->disabledOn('edit'),

                TextInput::make('opening_balance')
                    ->label('الرصيد الافتتاحي')
                    ->numeric()
                    ->default(0),

                Toggle::make('is_active')
                    ->label('مفعّل')
                    ->default(true),

                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withSum(['transactions as total_in' => fn ($q) => $q->where('direction', 'in')], 'amount')
                ->withSum(['transactions as total_out' => fn ($q) => $q->where('direction', 'out')], 'amount'))
            ->columns([
                TextColumn::make('name')
                    ->label('الحساب')
                    ->weight('bold')
                    ->description(fn (FinanceAccount $record): string => FinanceAccount::TYPES[$record->type] ?? ''),

                TextColumn::make('currency')
                    ->label('العملة')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('total_in')
                    ->label('الوارد')
                    ->color('success')
                    ->formatStateUsing(fn (FinanceAccount $record): string => Lookup::money($record->total_in ?? 0, $record->currency)),

                TextColumn::make('total_out')
                    ->label('الصادر')
                    ->color('danger')
                    ->formatStateUsing(fn (FinanceAccount $record): string => Lookup::money($record->total_out ?? 0, $record->currency)),

                TextColumn::make('balance')
                    ->label('الرصيد')
                    ->weight('bold')
                    ->state(fn (FinanceAccount $record): float => (float) $record->opening_balance + (float) $record->total_in - (float) $record->total_out)
                    ->formatStateUsing(fn (FinanceAccount $record, $state): string => Lookup::money($state, $record->currency))
                    ->color(fn ($state): string => $state < 0 ? 'danger' : 'primary')
                    ->description(fn (FinanceAccount $record, $state): ?string => $record->currency !== 'USD'
                        ? '≈ ' . Lookup::money(ExchangeRate::toUsd($state, $record->currency), 'USD')
                        : null),

                IconColumn::make('is_active')
                    ->label('مفعّل')
                    ->boolean(),
            ])
            ->recordActions([
                Action::make('statement')
                    ->label('كشف حساب')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->url(fn (FinanceAccount $record): string => FinanceTransactionResource::getUrl('index', ['account' => $record->id])),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (FinanceAccount $record): bool => ! $record->transactions()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFinanceAccounts::route('/'),
        ];
    }
}
