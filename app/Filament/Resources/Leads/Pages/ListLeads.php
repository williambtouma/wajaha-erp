<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Models\Lookup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    // من روابط القائمة الجانبية: ?view=due|open|closed&type=buyer
    // ملاحظة: الاسم $view محجوز في Filament، لذلك نستخدم $segment مع الرابط ?view=
    #[Url(as: 'view')]
    public ?string $segment = null;

    #[Url]
    public ?string $type = null;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->when($this->segment === 'due', fn (Builder $q) => $q->followUpDue()->orderBy('next_follow_up_at'))
                ->when($this->segment === 'open', fn (Builder $q) => $q->open())
                ->when($this->segment === 'closed', fn (Builder $q) => $q->whereIn('stage', Lead::CLOSED_STAGES))
                ->when($this->type, fn (Builder $q, string $v) => $q->where('type', $v)));
    }

    public function getTitle(): string
    {
        $title = match ($this->segment) {
            'due' => 'متابعات اليوم',
            'open' => 'العملاء النشطون',
            'closed' => 'الصفقات المغلقة',
            default => 'كل العملاء',
        };

        return $this->type ? $title . ' — ' . Lookup::label('lead_type', $this->type) : $title;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('إضافة عميل')
                ->url(fn (): string => LeadResource::getUrl('create', array_filter(['type' => $this->type]))),
        ];
    }
}
