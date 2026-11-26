<?php

namespace App\Filament\Resources\Leads\Actions;

use App\Models\Lead;
use App\Models\Lookup;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;

/** زر تغيير مرحلة العميل (يُسجَّل في سجل المتابعة) */
class ChangeStageAction
{
    public static function make(): Action
    {
        return Action::make('changeStage')
            ->label('المرحلة')
            ->icon('heroicon-o-arrows-right-left')
            ->color('primary')
            ->visible(fn (Lead $record): bool => (bool) auth()->user()?->can('update', $record))
            ->modalHeading(fn (Lead $record): string => "تغيير مرحلة العميل: {$record->name}")
            ->modalSubmitActionLabel('حفظ')
            ->fillForm(fn (Lead $record): array => [
                'stage' => $record->stage,
                'next_follow_up_at' => $record->next_follow_up_at,
            ])
            ->schema([
                Select::make('stage')
                    ->label('المرحلة الجديدة')
                    ->options(fn (): array => Lookup::options('lead_stage'))
                    ->required()
                    ->live(),

                Select::make('lost_reason')
                    ->label('سبب الخسارة')
                    ->options(fn (): array => Lookup::options('lost_reason'))
                    ->visible(fn (Get $get): bool => $get('stage') === 'lost')
                    ->required(fn (Get $get): bool => $get('stage') === 'lost'),

                DateTimePicker::make('next_follow_up_at')
                    ->label('موعد المتابعة القادمة')
                    ->native(false)
                    ->seconds(false)
                    ->visible(fn (Get $get): bool => ! in_array($get('stage'), Lead::CLOSED_STAGES)),

                Textarea::make('note')
                    ->label('ملاحظة')
                    ->rows(3),
            ])
            ->action(function (Lead $record, array $data): void {
                $record->changeStage($data['stage'], $data['note'] ?? null, $data['lost_reason'] ?? null, $data['next_follow_up_at'] ?? null);

                Notification::make()
                    ->title('تم تحديث المرحلة: ' . Lookup::label('lead_stage', $data['stage']))
                    ->success()
                    ->send();
            });
    }
}
