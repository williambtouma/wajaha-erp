<?php

namespace App\Filament\Resources\Properties\Pages;

use App\Filament\Resources\Properties\Actions\ChangeAvailabilityAction;
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
            ChangeAvailabilityAction::make(),
            DeleteAction::make(),
        ];
    }
}
