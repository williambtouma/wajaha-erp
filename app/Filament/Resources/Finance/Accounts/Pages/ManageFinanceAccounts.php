<?php

namespace App\Filament\Resources\Finance\Accounts\Pages;

use App\Filament\Resources\Finance\Accounts\FinanceAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFinanceAccounts extends ManageRecords
{
    protected static string $resource = FinanceAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('إضافة حساب'),
        ];
    }
}
