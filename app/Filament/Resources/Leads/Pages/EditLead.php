<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Deals\DealResource;
use App\Filament\Resources\Leads\Actions\ChangeStageAction;
use App\Models\Deal;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Leads\Widgets\MatchingProperties;
use App\Filament\Resources\Properties\PropertyResource;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\Property;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditLead extends EditRecord
{
    protected static string $resource = LeadResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        /** @var Lead $lead */
        $lead = $this->getRecord();

        return collect([
            $lead->reference,
            Lookup::label('lead_type', $lead->type),
            'المرحلة: ' . Lookup::label('lead_stage', $lead->stage),
            $lead->assignee?->name ? 'الوكيل: ' . $lead->assignee->name : null,
        ])->filter()->implode(' — ');
    }

    /** المشتري: عقارات مطابقة أسفل الصفحة */
    protected function getFooterWidgets(): array
    {
        return $this->getRecord()->type === Lead::BUYER ? [MatchingProperties::class] : [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('whatsapp')
                ->label('واتساب')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('success')
                ->url(fn (Lead $record): ?string => $record->whatsappUrl())
                ->openUrlInNewTab()
                ->visible(fn (Lead $record): bool => filled($record->whatsappUrl())),

            ChangeStageAction::make()
                ->label('تغيير المرحلة')
                ->after(fn () => $this->refreshFormData(['stage', 'next_follow_up_at'])),

            // تسكير صفقة للمشتري / المستأجر
            Action::make('closeDeal')
                ->label('تسكير صفقة')
                ->icon('heroicon-o-document-check')
                ->color('success')
                ->visible(fn (Lead $record): bool => $record->type === Lead::BUYER && ! $record->isClosed()
                    && (bool) auth()->user()?->can('create', Deal::class))
                ->url(fn (Lead $record): string => DealResource::getUrl('create', array_filter([
                    'lead' => $record->id,
                    'property' => $record->interested_property_id,
                ]))),

            // مالك ← عقار جديد بكبسة
            Action::make('convertToProperty')
                ->label('تحويل إلى عقار')
                ->icon('heroicon-o-home-modern')
                ->color('warning')
                ->visible(fn (Lead $record): bool => $record->type === Lead::OWNER && ! $record->property_id
                    && (bool) auth()->user()?->can('create', Property::class))
                ->requiresConfirmation()
                ->modalDescription('سيُنشأ عقار جديد ببيانات المالك، ثم تُكمل باقي التفاصيل والصور.')
                ->action(function (Lead $record) {
                    if (! $record->category || ! $record->subtype || ! $record->transaction || ! $record->city) {
                        Notification::make()
                            ->title('أكمل بيانات العقار المعروض أولاً')
                            ->body('نوع العقار، التصنيف الفرعي، نوع المعاملة والمدينة مطلوبة.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $title = collect([
                        Lookup::label('subtype', $record->subtype, $record->category),
                        Lookup::label('transaction', $record->transaction),
                        'في ' . ($record->area ?: $record->city),
                    ])->implode(' ');

                    $property = Property::create([
                        'title' => $title,
                        'category' => $record->category,
                        'subtype' => $record->subtype,
                        'transaction' => $record->transaction,
                        'city' => $record->city,
                        'area' => $record->area,
                        'price' => $record->budget_max ?? 0,
                        'currency' => $record->currency ?? 'USD',
                        'bedrooms' => $record->bedrooms_min,
                        'owner_name' => $record->name,
                        'owner_phone' => $record->phone,
                        'ownership_type' => $record->details['ownership_type'] ?? null,
                        'description' => $record->notes,
                    ]);

                    $record->update(['property_id' => $property->id]);
                    $record->activities()->create(['type' => 'note', 'body' => "تم تحويله إلى عقار {$property->reference}"]);

                    Notification::make()->title("تم إنشاء العقار {$property->reference}")->success()->send();

                    $this->redirect(PropertyResource::getUrl('edit', ['record' => $property]));
                }),

            Action::make('openProperty')
                ->label('عرض العقار')
                ->icon('heroicon-o-home-modern')
                ->color('gray')
                ->visible(fn (Lead $record): bool => (bool) $record->property_id)
                ->url(fn (Lead $record): string => PropertyResource::getUrl('edit', ['record' => $record->property_id])),

            DeleteAction::make(),
        ];
    }
}
