<?php

namespace App\Filament\Resources\Properties\Actions;

use App\Models\Lookup;
use App\Models\Property;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * زر تغيير التوفر:
 * - عقار متاح ← "إيقاف التوفر" (سبب + تعليق إلزامي)
 * - عقار غير متاح ← "إعادة الإتاحة" (تعليق اختياري)
 */
class ChangeAvailabilityAction
{
    public static function make(): Action
    {
        return Action::make('changeAvailability')
            ->label(fn (Property $record): string => $record->is_available ? 'إيقاف التوفر' : 'إعادة الإتاحة')
            ->icon(fn (Property $record): string => $record->is_available ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
            ->color(fn (Property $record): string => $record->is_available ? 'danger' : 'success')
            ->visible(fn (): bool => (bool) (auth()->user()?->isAdmin() || auth()->user()?->can('ChangeAvailability:Property')))
            ->modalHeading(fn (Property $record): string => $record->is_available
                ? "إيقاف توفر العقار {$record->reference}"
                : "إعادة إتاحة العقار {$record->reference}")
            ->modalDescription(fn (Property $record): string => $record->title)
            ->modalSubmitActionLabel('حفظ')
            ->schema(fn (Property $record): array => $record->is_available
                ? [
                    Select::make('reason')
                        ->label('سبب عدم التوفر')
                        ->options(fn (): array => Lookup::options('unavailable_reason'))
                        ->required(),
                    Textarea::make('note')
                        ->label('التعليق')
                        ->placeholder('مثال: تم البيع للعميل فلان بتاريخ ...')
                        ->required()
                        ->rows(3),
                ]
                : [
                    Textarea::make('note')
                        ->label('التعليق (اختياري)')
                        ->placeholder('مثال: المالك أعاد عرض العقار')
                        ->rows(3),
                ])
            ->action(function (Property $record, array $data): void {
                $makeAvailable = ! $record->is_available;

                $record->setAvailability($makeAvailable, $data['reason'] ?? null, $data['note'] ?? null);

                Notification::make()
                    ->title($makeAvailable ? 'أصبح العقار متاحاً' : 'أصبح العقار غير متاح')
                    ->color($makeAvailable ? 'success' : 'danger')
                    ->success()
                    ->send();
            });
    }
}
