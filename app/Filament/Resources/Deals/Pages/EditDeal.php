<?php

namespace App\Filament\Resources\Deals\Pages;

use App\Filament\Resources\Deals\DealResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Properties\PropertyResource;
use App\Models\Deal;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditDeal extends EditRecord
{
    protected static string $resource = DealResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'الصفقة ' . $this->getRecord()->reference;
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var Deal $deal */
        $deal = $this->getRecord();

        return collect([
            Deal::STATUSES[$deal->status] ?? null,
            $deal->property?->title,
            'القيمة ' . $deal->money($deal->price),
            'العمولة ' . $deal->money($deal->total_commission),
        ])->filter()->implode(' — ');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openProperty')
                ->label('العقار')
                ->icon('heroicon-o-home-modern')
                ->color('gray')
                ->url(fn (Deal $record): string => PropertyResource::getUrl('edit', ['record' => $record->property_id])),

            Action::make('openLead')
                ->label('العميل')
                ->icon('heroicon-o-user')
                ->color('gray')
                ->visible(fn (Deal $record): bool => (bool) $record->lead_id)
                ->url(fn (Deal $record): string => LeadResource::getUrl('edit', ['record' => $record->lead_id])),

            Action::make('complete')
                ->label('اعتبارها مكتملة')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (Deal $record): bool => $record->status === 'in_progress')
                ->requiresConfirmation()
                ->action(function (Deal $record): void {
                    $record->update(['status' => 'completed']);
                    $this->refreshFormData(['status']);
                    Notification::make()->title('الصفقة مكتملة')->success()->send();
                }),

            Action::make('cancel')
                ->label('إلغاء الصفقة')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Deal $record): bool => $record->status !== 'cancelled')
                ->modalHeading('إلغاء الصفقة')
                ->schema([
                    Textarea::make('note')
                        ->label('سبب الإلغاء')
                        ->required()
                        ->rows(3),
                    Toggle::make('reopen')
                        ->label('إعادة إتاحة العقار')
                        ->default(true),
                ])
                ->action(function (Deal $record, array $data): void {
                    $record->cancel($data['note'], (bool) $data['reopen']);
                    Notification::make()->title('تم إلغاء الصفقة')->warning()->send();
                    $this->redirect(DealResource::getUrl('edit', ['record' => $record]));
                }),

            DeleteAction::make(),
        ];
    }
}
