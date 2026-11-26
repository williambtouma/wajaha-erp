<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use App\Models\Lookup;
use App\Models\PropertyAvailabilityLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** سجل تغييرات التوفر (للقراءة فقط) */
class AvailabilityLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'availabilityLogs';

    protected static ?string $title = 'سجل التوفر';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('Y/m/d H:i'),

                TextColumn::make('is_available')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'متاح' : 'غير متاح')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),

                TextColumn::make('reason')
                    ->label('السبب')
                    ->formatStateUsing(fn (?string $state): ?string => Lookup::label('unavailable_reason', $state))
                    ->placeholder('—'),

                TextColumn::make('note')
                    ->label('التعليق')
                    ->wrap()
                    ->placeholder('—'),

                TextColumn::make('user.name')
                    ->label('بواسطة')
                    ->placeholder('—'),
            ]);
    }
}
