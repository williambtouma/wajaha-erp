<?php

namespace App\Filament\Resources\Properties\Pages;

use App\Filament\Resources\Properties\PropertyResource;
use App\Models\Lookup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListProperties extends ListRecords
{
    protected static string $resource = PropertyResource::class;

    // تُعبّأ من روابط القائمة الجانبية: ?category=residential&transaction=sale
    #[Url]
    public ?string $category = null;

    #[Url]
    public ?string $transaction = null;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->when($this->category, fn (Builder $q, string $v) => $q->where('category', $v))
                ->when($this->transaction, fn (Builder $q, string $v) => $q->where('transaction', $v)));
    }

    public function getTitle(): string
    {
        $parts = array_filter([
            Lookup::label('category', $this->category),
            Lookup::label('transaction', $this->transaction),
        ]);

        return $parts ? 'عقارات ' . implode(' - ', $parts) : 'كل العقارات';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('إضافة عقار')
                ->url(fn (): string => PropertyResource::getUrl('create', array_filter([
                    'category' => $this->category,
                    'transaction' => $this->transaction,
                ]))),
        ];
    }
}
