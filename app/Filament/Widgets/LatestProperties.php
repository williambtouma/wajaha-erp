<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Properties\PropertyResource;
use App\Models\Lookup;
use App\Models\Property;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** جدول: أحدث العقارات المضافة */
class LatestProperties extends TableWidget
{
    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('أحدث العقارات المضافة')
            ->query(fn () => Property::query()->latest()->limit(5))
            ->paginated(false)
            ->recordUrl(fn (Property $record): string => PropertyResource::getUrl('edit', ['record' => $record]))
            ->recordClasses(fn (Property $record): string => $record->is_available ? 'wajaha-row-available' : 'wajaha-row-unavailable')
            ->columns([
                ImageColumn::make('images')
                    ->label('')
                    ->disk('public')
                    ->visibility('public')
                    ->limit(1)
                    ->square(),

                TextColumn::make('reference')
                    ->label('رقم العقار'),

                TextColumn::make('title')
                    ->label('العنوان')
                    ->limit(45)
                    ->description(fn (Property $record): string => trim($record->city . ' - ' . $record->area, ' -')),

                TextColumn::make('transaction')
                    ->label('المعاملة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): ?string => Lookup::label('transaction', $state))
                    ->color(fn (?string $state): string => Lookup::color('transaction', $state)),

                TextColumn::make('price')
                    ->label('السعر')
                    ->formatStateUsing(fn (Property $record): string => $record->formattedPrice()),

                TextColumn::make('is_available')
                    ->label('التوفر')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'متاح' : 'غير متاح')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),

                TextColumn::make('created_at')
                    ->label('أضيف')
                    ->since(),
            ]);
    }
}
