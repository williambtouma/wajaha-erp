<?php

namespace App\Filament\Resources\Properties\Pages;

use App\Filament\Resources\Deals\DealResource;
use App\Filament\Resources\Properties\Actions\ChangeAvailabilityAction;
use App\Models\Deal;
use Filament\Actions\Action;
use App\Filament\Resources\Properties\PropertyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditProperty extends EditRecord
{
    protected static string $resource = PropertyResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $record = $this->getRecord();
        $text = $record->reference . ' — ' . $record->availabilityLabel();

        if (! $record->is_available && $record->availability_note) {
            $text .= ' — ' . $record->availability_note;
        }

        return $text;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('closeDeal')
                ->label('تسكير صفقة')
                ->icon('heroicon-o-document-check')
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->is_available && (bool) auth()->user()?->can('create', Deal::class))
                ->url(fn (): string => DealResource::getUrl('create', ['property' => $this->getRecord()->id])),
            ChangeAvailabilityAction::make(),
            DeleteAction::make(),
        ];
    }
}
