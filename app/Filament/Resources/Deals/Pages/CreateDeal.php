<?php

namespace App\Filament\Resources\Deals\Pages;

use App\Filament\Resources\Deals\DealResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Property;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateDeal extends CreateRecord
{
    protected static string $resource = DealResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'تسكير صفقة';
    }

    /** تعبئة تلقائية عند القدوم من صفحة عميل أو عقار: ?lead=5&property=12 */
    protected function afterFill(): void
    {
        $lead = Lead::find(request()->integer('lead'));
        $property = Property::find(request()->integer('property') ?: $lead?->interested_property_id);

        $data = [];

        if ($property) {
            $data += [
                'property_id' => $property->id,
                'transaction' => $property->transaction,
                'price' => (float) $property->price,
                'currency' => $property->currency,
                'seller_name' => $property->owner_name,
                'seller_phone' => $property->owner_phone,
            ];
        }

        if ($lead) {
            $data += [
                'lead_id' => $lead->id,
                'buyer_name' => $lead->name,
                'buyer_phone' => $lead->phone,
                'agent_id' => $lead->assigned_to,
            ];
        }

        if ($data) {
            $state = [...$this->form->getRawState(), ...$data];

            // معاينة العمولة
            $buyer = Deal::commissionAmount($state['buyer_commission_type'] ?? 'percent', $state['buyer_commission_value'] ?? 0, $state['price'] ?? 0);
            $seller = Deal::commissionAmount($state['seller_commission_type'] ?? 'percent', $state['seller_commission_value'] ?? 0, $state['price'] ?? 0);
            $state['buyer_commission_amount'] = $buyer;
            $state['seller_commission_amount'] = $seller;
            $state['total_commission'] = $buyer + $seller;
            $state['agent_commission'] = round(($buyer + $seller) * (float) ($state['agent_share_percent'] ?? 0) / 100, 2);

            $this->form->fill($state);
        }
    }

    protected function afterCreate(): void
    {
        /** @var Deal $deal */
        $deal = $this->getRecord();
        $others = $deal->applyClosing();

        Notification::make()
            ->title("تم تسكير الصفقة {$deal->reference} 🎉")
            ->body('العقار أصبح غير متاح' . ($deal->lead ? '، والعميل انتقل إلى «تمت الصفقة»' : '') . '. أضف جدول الدفعات والعمولة من الأسفل.')
            ->success()
            ->persistent()
            ->send();

        if ($others->isNotEmpty()) {
            Notification::make()
                ->title("انتبه: {$others->count()} عميل آخر مهتم بنفس العقار")
                ->body($others->map(fn (Lead $l) => "• {$l->name}" . ($l->assignee ? " (الوكيل: {$l->assignee->name})" : ''))->implode("\n") . "\nتمت إضافة ملاحظة في سجلهم لاقتراح بديل.")
                ->warning()
                ->persistent()
                ->actions($others->take(3)->map(fn (Lead $l) => Action::make("lead{$l->id}")
                    ->label($l->name)
                    ->url(LeadResource::getUrl('edit', ['record' => $l])))->all())
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
