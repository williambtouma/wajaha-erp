<?php

namespace App\Filament\Resources\Lookups\Pages;

use App\Filament\Resources\Lookups\LookupResource;
use App\Models\Lookup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageLookups extends ManageRecords
{
    protected static string $resource = LookupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('إضافة قيمة'),
        ];
    }

    /** تبويب لكل قائمة */
    public function getTabs(): array
    {
        $tabs = [];

        foreach (Lookup::LISTS as $key => $label) {
            $tabs[$key] = Tab::make($label)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('list_name', $key));
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'category';
    }
}
