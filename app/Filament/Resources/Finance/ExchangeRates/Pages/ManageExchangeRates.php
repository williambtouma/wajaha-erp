<?php

namespace App\Filament\Resources\Finance\ExchangeRates\Pages;

use App\Filament\Resources\Finance\ExchangeRates\ExchangeRateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExchangeRates extends ManageRecords
{
    protected static string $resource = ExchangeRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('تسجيل سعر اليوم'),
        ];
    }
}
