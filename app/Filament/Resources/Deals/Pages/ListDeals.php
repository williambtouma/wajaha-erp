<?php

namespace App\Filament\Resources\Deals\Pages;

use App\Filament\Resources\Deals\DealResource;
use App\Models\Deal;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListDeals extends ListRecords
{
    protected static string $resource = DealResource::class;

    #[Url(as: 'status')]
    public ?string $statusFilter = null;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->when($this->statusFilter, fn (Builder $q, string $v) => $q->where('status', $v)));
    }

    public function getTitle(): string
    {
        return $this->statusFilter ? 'الصفقات: ' . (Deal::STATUSES[$this->statusFilter] ?? '') : 'كل الصفقات';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('تسكير صفقة جديدة'),
        ];
    }
}
