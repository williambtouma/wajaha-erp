<?php

namespace App\Filament\Resources\Properties\Tables;

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
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('images')
                    ->label('')
                    ->disk('public')
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
                    ->formatStateUsing(fn (string $state): string => Property::TRANSACTIONS[$state] ?? $state)
                    ->color(fn (string $state): string => $state === 'sale' ? 'primary' : 'info'),

                TextColumn::make('readiness')
                    ->label('الجاهزية')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => Property::READINESS[$state] ?? '')
                    ->color(fn (?string $state): string => $state === 'ready' ? 'success' : 'warning')
                    ->description(fn (Property $record): ?string => $record->readiness === 'under_construction' && $record->expected_delivery
                        ? 'التسليم ' . $record->expected_delivery->format('m/Y')
                        : null),

                TextColumn::make('category')
                    ->label('النوع')
                    ->formatStateUsing(fn (string $state): string => Property::CATEGORIES[$state] ?? $state)
                    ->description(fn (Property $record): string => $record->subtypeLabel()),

                TextColumn::make('price')
                    ->label('السعر')
                    ->sortable()
                    ->formatStateUsing(fn (Property $record): string => $record->formattedPrice()),

                TextColumn::make('built_area')
                    ->label('المساحة')
                    ->suffix(' م²')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('bedrooms')
                    ->label('غرف')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Property::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'reserved' => 'warning',
                        default => 'gray',
                    }),

                IconColumn::make('is_featured')
                    ->label('مميز')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('transaction')->label('نوع المعاملة')->options(Property::TRANSACTIONS),
                SelectFilter::make('readiness')->label('الجاهزية')->options(Property::READINESS),
                SelectFilter::make('category')->label('نوع العقار')->options(Property::CATEGORIES),
                SelectFilter::make('subtype')->label('التصنيف الفرعي')->options(Property::allSubtypes()),
                SelectFilter::make('city')->label('المدينة')->options(Property::CITIES),
                SelectFilter::make('status')->label('الحالة')->options(Property::STATUSES),
                SelectFilter::make('currency')->label('العملة')->options(Property::CURRENCIES),
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
