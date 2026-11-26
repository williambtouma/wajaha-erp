<?php

namespace App\Filament\Resources\Deals\Actions;

use App\Models\DealPayment;
use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Models\Lookup;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use App\Models\ExchangeRate;

/** زر "تسجيل الدفع" لدفعة */
class MarkPaidAction
{
    public static function make(): Action
    {
        return Action::make('markPaid')
            ->label('تسجيل الدفع')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->button()
            ->visible(fn (DealPayment $record): bool => $record->paid_at === null)
            ->modalHeading(fn (DealPayment $record): string => "تسجيل دفع: {$record->title}")
            ->modalDescription(fn (DealPayment $record): string => 'المبلغ: ' . Lookup::money($record->amount, $record->deal->currency))
            ->modalSubmitActionLabel('تأكيد الدفع')
            ->schema([
                DatePicker::make('paid_at')
                    ->label('تاريخ الدفع')
                    ->native(false)
                    ->default(now())
                    ->required(),

                Select::make('method')
                    ->label('طريقة الدفع')
                    ->options(fn (): array => Lookup::options('payment_method'))
                    ->default('cash'),

                // تحصيل العمولة يدخل الصندوق تلقائياً
                Select::make('account_id')
                    ->label('أُودِعت في حساب')
                    ->options(fn (DealPayment $record): array => FinanceAccount::options($record->deal->currency))
                    ->default(fn (DealPayment $record) => array_key_first(FinanceAccount::options($record->deal->currency)))
                    ->visible(fn (DealPayment $record): bool => $record->category === 'commission')
                    ->required(fn (DealPayment $record): bool => $record->category === 'commission' && count(FinanceAccount::options($record->deal->currency)) > 0)
                    ->helperText(fn (DealPayment $record): ?string => count(FinanceAccount::options($record->deal->currency))
                        ? 'تُسجَّل تلقائياً كإيراد "عمولات صفقات" في المالية'
                        : 'لا يوجد حساب بعملة الصفقة — أضفه من المالية ← الصناديق والحسابات'),

                TextInput::make('exchange_rate')
                    ->label('سعر الصرف')
                    ->numeric()
                    ->default(fn () => ExchangeRate::current())
                    ->visible(fn (DealPayment $record): bool => $record->category === 'commission' && $record->deal->currency !== 'USD'),

                FileUpload::make('receipt')
                    ->label('صورة الإيصال (اختياري)')
                    ->disk('local')
                    ->directory('payment-receipts')
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->maxSize(10240),
            ])
            ->action(function (DealPayment $record, array $data): void {
                $accountId = $data['account_id'] ?? null;
                $rate = $data['exchange_rate'] ?? null;
                unset($data['account_id'], $data['exchange_rate']);

                $record->update($data);

                if ($record->category === 'commission' && $accountId) {
                    FinanceTransaction::create([
                        'date' => $record->paid_at,
                        'account_id' => $accountId,
                        'direction' => 'in',
                        'category' => 'commission',
                        'amount' => $record->amount,
                        'exchange_rate' => $rate ?: 1,
                        'deal_id' => $record->deal_id,
                        'deal_payment_id' => $record->id,
                        'attachment' => $record->receipt,
                        'description' => "{$record->title} — صفقة {$record->deal->reference}",
                    ]);
                }

                Notification::make()->title('تم تسجيل الدفعة')->success()->send();
            });
    }
}
