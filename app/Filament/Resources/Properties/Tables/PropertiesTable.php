<?php

namespace App\Filament\Resources\Properties\Tables;

use App\Filament\Resources\Properties\Actions\ChangeAvailabilityAction;
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
            // تلوين الصفوف: متاح = أخضر فاتح، غير متاح = أحمر فاتح
            ->recordClasses(fn (Property $record): string => $record->is_available ? 'wajaha-row-available' : 'wajaha-row-unavailable')
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

                TextColumn::make('is_available')
                    ->label('التوفر')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'متاح' : 'غير متاح')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
                    ->description(fn (Property $record): ?string => $record->is_available
                        ? null
                        : Lookup::label('unavailable_reason', $record->unavailable_reason))
                    ->tooltip(fn (Property $record): ?string => $record->availability_note)
                    ->sortable(),

                TextColumn::make('availability_changed_at')
                    ->label('آخر تحديث للتوفر')
                    ->since()
                    ->placeholder('—')
                    ->tooltip(fn (Property $record): ?string => $record->availabilityChangedBy?->name)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('documents')
                    ->label('مستندات')
                    ->state(fn (Property $record): ?int => count($record->documents ?? []) ?: null)
                    ->icon('heroicon-m-paper-clip')
                    ->placeholder('—')
                    ->toggleable(),

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
                TernaryFilter::make('is_available')
                    ->label('التوفر')
                    ->placeholder('الكل')
                    ->trueLabel('متاح')
                    ->falseLabel('غير متاح'),
                $filter('unavailable_reason', 'سبب عدم التوفر'),
                $filter('currency', 'العملة'),
                TernaryFilter::make('is_featured')->label('مميز'),
            ])
            ->recordActions([
                ChangeAvailabilityAction::make()->button(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
