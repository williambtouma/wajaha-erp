<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Components\Attributes\Exposed;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

/** لوحة الكانبان: عمود لكل مرحلة، اسحب العميل لتغيير مرحلته */
class LeadsBoard extends Page
{
    protected static string $resource = LeadResource::class;

    protected string $view = 'filament.leads.board';

    #[Url]
    public ?string $type = null;

    #[Url]
    public ?string $agent = null;

    public string $search = '';

    public function getTitle(): string|Htmlable
    {
        return 'لوحة مراحل العملاء';
    }

    protected function getViewData(): array
    {
        $user = auth()->user();

        $query = LeadResource::getEloquentQuery()
            ->with('assignee')
            ->when($this->type, fn ($q, $v) => $q->where('type', $v))
            ->when($this->agent && $user?->canSeeAllLeads(), fn ($q) => $q->where('assigned_to', $this->agent))
            ->when(filled($this->search), fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")
                ->orWhere('reference', 'like', "%{$this->search}%")));

        $columns = [];
        foreach (Lookup::options('lead_stage') as $stage => $label) {
            $closed = in_array($stage, Lead::CLOSED_STAGES);

            $leads = (clone $query)
                ->where('stage', $stage)
                // المراحل المغلقة: آخر 30 يوماً فقط
                ->when($closed, fn ($q) => $q->where('stage_changed_at', '>=', now()->subDays(30))->limit(20))
                ->orderByRaw('next_follow_up_at is null')
                ->orderBy('next_follow_up_at')
                ->latest('id')
                ->get();

            $columns[] = [
                'key' => $stage,
                'label' => $label,
                'color' => Lookup::color('lead_stage', $stage),
                'closed' => $closed,
                'leads' => $leads,
            ];
        }

        return [
            'columns' => $columns,
            'types' => Lookup::options('lead_type'),
            'agents' => $user?->canSeeAllLeads()
                ? User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()
                : [],
        ];
    }

    /** يُستدعى عند إفلات بطاقة في عمود */
    #[Exposed]
    public function moveLead(int $leadId, string $stage): void
    {
        $lead = LeadResource::getEloquentQuery()->find($leadId);

        if (! $lead || $lead->stage === $stage || ! array_key_exists($stage, Lookup::options('lead_stage'))) {
            return;
        }

        if (! auth()->user()?->can('update', $lead)) {
            Notification::make()->title('لا تملك صلاحية تعديل هذا العميل')->danger()->send();

            return;
        }

        // تمت الصفقة (مشتري) ← نموذج تسكير الصفقة
        if ($stage === 'won' && $lead->type === Lead::BUYER) {
            $this->redirect(\App\Filament\Resources\Deals\DealResource::getUrl('create', array_filter([
                'lead' => $lead->id,
                'property' => $lead->interested_property_id,
            ])));

            return;
        }

        // الخسارة تحتاج سبب ← نافذة
        if ($stage === 'lost') {
            $this->mountAction('changeStage', ['lead' => $leadId, 'stage' => $stage]);

            return;
        }

        $lead->changeStage($stage);

        Notification::make()
            ->title("{$lead->name} ← " . Lookup::label('lead_stage', $stage))
            ->success()
            ->send();
    }

    public function changeStageAction(): Action
    {
        return Action::make('changeStage')
            ->modalHeading('تغيير مرحلة العميل')
            ->modalSubmitActionLabel('حفظ')
            ->fillForm(fn (array $arguments): array => ['stage' => $arguments['stage'] ?? null])
            ->schema([
                Select::make('stage')
                    ->label('المرحلة')
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
            ->action(function (array $data, array $arguments): void {
                $lead = LeadResource::getEloquentQuery()->findOrFail($arguments['lead']);

                $lead->changeStage($data['stage'], $data['note'] ?? null, $data['lost_reason'] ?? null, $data['next_follow_up_at'] ?? null);

                Notification::make()->title('تم تحديث المرحلة')->success()->send();
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('list')
                ->label('عرض الجدول')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->url(LeadResource::getUrl('index', ['view' => 'open'])),

            Action::make('create')
                ->label('إضافة عميل')
                ->icon('heroicon-o-plus')
                ->url(LeadResource::getUrl('create')),
        ];
    }
}
