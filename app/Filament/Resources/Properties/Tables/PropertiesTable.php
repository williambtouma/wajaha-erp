<?php

namespace App\Filament\Resources\Properties\Tables;

use App\Models\Lookup;
use App\Models\Property;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PropertiesTable
{
    public static function configure(Table $table): Table
    {
        $filter = fn (string $list, string $label) => SelectFilter::make($list)
            ->label($label)
            ->options(fn (): array => Lookup::options($list));

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('images')
                    ->label('')
                    ->disk('public')
                    ->visibility('public')
                    ->limit(1)
                    ->square(),

                TextColumn::make('reference')
                    ->label('رقم العقار')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->limit(40)
                    ->description(fn (Property $record): string => trim($record->city . ' - ' . $record->area, ' -')),

                TextColumn::make('transaction')
                    ->label('المعاملة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): ?string => Lookup::label('transaction', $state))
                    ->color(fn (?string $state): string => Lookup::color('transaction', $state)),

                TextColumn::make('readiness')
                    ->label('الجاهزية')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): ?string => Lookup::label('readiness', $state))
                    ->color(fn (?string $state): string => Lookup::color('readiness', $state))
                    ->description(fn (Property $record): ?string => $record->readiness === Property::UNDER_CONSTRUCTION && $record->expected_delivery
                        ? 'التسليم ' . $record->expected_delivery->format('m/Y')
                        : null),

                TextColumn::make('category')
                    ->label('النوع')
                    ->formatStateUsing(fn (?string $state): ?string => Lookup::label('category', $state))
                    ->description(fn (Property $record): ?string => $record->subtypeLabel()),

                TextColumn::make('price')
                    ->label('السعر')
                    ->sortable()
                    ->formatStateUsing(fn (Property $record): string => $record->formattedPrice()),

                TextColumn::make('built_area')
                    ->label('المساحة')
                    ->suffix(' م²')
                    ->numeric()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('bedrooms')
                    ->label('غرف')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): ?string => Lookup::label('status', $state))
                    ->color(fn (?string $state): string => Lookup::color('status', $state)),

                IconColumn::make('is_featured')
                    ->label('مميز')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-minus')
                    ->falseColor('gray')
                    ->toggleable(),
            ])
            ->filters([
                $filter('transaction', 'نوع المعاملة'),
                $filter('readiness', 'الجاهزية'),
                $filter('category', 'نوع العقار'),
                $filter('subtype', 'التصنيف الفرعي'),
                $filter('city', 'المدينة'),
                $filter('status', 'الحالة'),
                $filter('currency', 'العملة'),
                TernaryFilter::make('is_featured')->label('مميز'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
