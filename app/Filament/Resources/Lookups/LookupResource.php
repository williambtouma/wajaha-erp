<?php

namespace App\Filament\Resources\Lookups;

use App\Filament\Resources\Lookups\Pages\ManageLookups;
use App\Models\Lookup;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LookupResource extends Resource
{
    protected static ?string $model = Lookup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?string $navigationLabel = 'القوائم المنسدلة';

    protected static ?string $modelLabel = 'قيمة';

    protected static ?string $pluralModelLabel = 'القوائم المنسدلة';

    protected static ?int $navigationSort = 100;

    public static function form(Schema $schema): Schema
    {
        $list = fn (Get $get): ?string => $get('list_name');

        return $schema
            ->columns(2)
            ->components([
                Select::make('list_name')
                    ->label('القائمة')
                    ->options(Lookup::LISTS)
                    ->default(fn ($livewire): ?string => array_key_exists($livewire->activeTab ?? '', Lookup::LISTS) ? $livewire->activeTab : null)
                    ->required()
                    ->live()
                    ->disabledOn('edit'),

                Select::make('parent_key')
                    ->label('يتبع لنوع العقار')
                    ->options(fn (): array => Lookup::options('category'))
                    ->visible(fn (Get $get): bool => $list($get) === 'subtype')
                    ->required(fn (Get $get): bool => $list($get) === 'subtype')
                    ->disabled(fn (?Lookup $record): bool => (bool) $record?->is_system),

                TextInput::make('label')
                    ->label('الاسم الظاهر')
                    ->required()
                    ->maxLength(100),

                TextInput::make('key')
                    ->label('القيمة المخزنة')
                    ->helperText('اختياري. إذا تركته فارغاً يُستخدم الاسم الظاهر.')
                    ->maxLength(100)
                    ->disabled(fn (?Lookup $record): bool => (bool) $record?->is_system || (bool) $record?->isInUse())
                    ->dehydrated(fn (?Lookup $record): bool => ! ($record?->is_system || $record?->isInUse())),

                TextInput::make('code')
                    ->label(fn (Get $get): string => $list($get) === 'currency' ? 'رمز العملة (مثل $)' : 'رمز رقم العقار (مثل VIL)')
                    ->maxLength(10)
                    ->visible(fn (Get $get): bool => in_array($list($get), ['subtype', 'currency'])),

                Select::make('color')
                    ->label('لون البادج')
                    ->options(Lookup::COLORS)
                    ->visible(fn (Get $get): bool => in_array($list($get), ['transaction', 'readiness', 'unavailable_reason', 'lead_type', 'lead_stage', 'lead_priority', 'activity_type'])),

                Toggle::make('is_active')
                    ->label('مفعّل')
                    ->helperText('القيم غير المفعّلة لا تظهر في القوائم.')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('label')
                    ->label('الاسم')
                    ->badge()
                    ->color(fn (Lookup $record): string => $record->color ?? 'gray')
                    ->searchable(),

                TextColumn::make('parent_key')
                    ->label('يتبع لـ')
                    ->formatStateUsing(fn (?string $state): ?string => Lookup::label('category', $state))
                    ->placeholder('—'),

                TextColumn::make('key')
                    ->label('القيمة المخزنة')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('code')
                    ->label('الرمز')
                    ->placeholder('—'),

                IconColumn::make('is_active')
                    ->label('مفعّل')
                    ->boolean(),

                IconColumn::make('is_system')
                    ->label('محمي')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('gray')
                    ->falseColor('gray')
                    ->tooltip(fn (Lookup $record): ?string => $record->is_system ? 'قيمة يعتمد عليها النظام: يمكن تعديل اسمها فقط' : null),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Lookup $record): bool => ! $record->is_system && ! $record->isInUse()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLookups::route('/'),
        ];
    }
}
