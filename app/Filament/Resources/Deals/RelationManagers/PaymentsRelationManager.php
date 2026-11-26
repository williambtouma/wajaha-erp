<?php

namespace App\Filament\Resources\Deals\RelationManagers;

use App\Filament\Resources\Deals\Actions\MarkPaidAction;
use App\Models\Deal;
use App\Models\DealPayment;
use App\Models\Lookup;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** دفعات الصفقة + تحصيل العمولة */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'الدفعات والعمولة';

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function deal(): Deal
    {
        return $this->getOwnerRecord();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('category')
                    ->label('النوع')
                    ->options(DealPayment::CATEGORIES)
                    ->default('deal')
                    ->required(),

                TextInput::make('title')
                    ->label('البيان')
                    ->placeholder('عربون / دفعة أولى / قسط 1 / عمولة المشتري')
                    ->required(),

                TextInput::make('amount')
                    ->label('المبلغ')
                    ->numeric()
                    ->required()
                    ->suffix(fn (): string => Lookup::code('currency', $this->deal()->currency) ?? $this->deal()->currency),

                DatePicker::make('due_date')
                    ->label('تاريخ الاستحقاق')
                    ->native(false),

                DatePicker::make('paid_at')
                    ->label('تاريخ الدفع (إذا دُفعت)')
                    ->native(false),

                Select::make('method')
                    ->label('طريقة الدفع')
                    ->options(fn (): array => Lookup::options('payment_method')),

                FileUpload::make('receipt')
                    ->label('الإيصال')
                    ->disk('local')
                    ->directory('payment-receipts')
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->maxSize(10240)
                    ->downloadable()
                    ->openable(),

                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        $deal = $this->deal();
        $money = fn ($amount) => Lookup::money($amount, $deal->currency);

        $dealPaid = $deal->paidTotal('deal');
        $commissionPaid = $deal->paidTotal('commission');

        return $table
            ->description(
                'الصفقة: مدفوع ' . $money($dealPaid) . ' من ' . $money($deal->price) . ' — المتبقي ' . $money(max(0, (float) $deal->price - $dealPaid))
                . '   |   العمولة: محصّل ' . $money($commissionPaid) . ' من ' . $money($deal->total_commission)
            )
            ->columns([
                TextColumn::make('category')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => DealPayment::CATEGORIES[$state] ?? $state)
                    ->color(fn (string $state): string => $state === 'commission' ? 'primary' : 'gray'),

                TextColumn::make('title')
                    ->label('البيان')
                    ->weight('bold'),

                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->formatStateUsing(fn ($state): string => $money($state)),

                TextColumn::make('due_date')
                    ->label('الاستحقاق')
                    ->date('Y/m/d')
                    ->placeholder('—')
                    ->color(fn (DealPayment $record): ?string => $record->status() === 'overdue' ? 'danger' : null),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->state(fn (DealPayment $record): string => $record->status())
                    ->formatStateUsing(fn (string $state): string => DealPayment::STATUS_LABELS[$state])
                    ->color(fn (string $state): string => DealPayment::STATUS_COLORS[$state])
                    ->description(fn (DealPayment $record): ?string => $record->paid_at
                        ? $record->paid_at->format('Y/m/d') . ($record->method ? ' · ' . Lookup::label('payment_method', $record->method) : '')
                        : null),
            ])
            ->filters([
                SelectFilter::make('category')->label('النوع')->options(DealPayment::CATEGORIES),
            ])
            ->headerActions([
                // توليد جدول أقساط بكبسة
                Action::make('generateSchedule')
                    ->label('توليد جدول دفعات')
                    ->icon('heroicon-o-calendar-days')
                    ->color('gray')
                    ->modalHeading('توليد جدول دفعات الصفقة')
                    ->modalDescription(fn (): string => 'قيمة الصفقة: ' . $money($deal->price) . ' — سيُقسَّم المتبقي بعد الدفعة الأولى على الأقساط بالتساوي')
                    ->schema([
                        TextInput::make('down_payment')
                            ->label('الدفعة الأولى / العربون')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        DatePicker::make('down_payment_date')
                            ->label('تاريخ الدفعة الأولى')
                            ->native(false)
                            ->default(now()),
                        TextInput::make('installments')
                            ->label('عدد الأقساط')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        DatePicker::make('first_installment_date')
                            ->label('تاريخ أول قسط')
                            ->native(false)
                            ->default(now()->addMonth())
                            ->required(),
                        Select::make('interval')
                            ->label('كل')
                            ->options([1 => 'شهر', 3 => '3 أشهر', 6 => '6 أشهر', 12 => 'سنة'])
                            ->default(1)
                            ->required(),
                    ])
                    ->action(function (array $data) use ($deal): void {
                        $down = (float) $data['down_payment'];
                        $count = max(1, (int) $data['installments']);
                        $remaining = max(0, (float) $deal->price - $down);
                        $each = floor($remaining / $count * 100) / 100;

                        if ($down > 0) {
                            $deal->payments()->create([
                                'category' => 'deal', 'title' => 'الدفعة الأولى', 'amount' => $down, 'due_date' => $data['down_payment_date'],
                            ]);
                        }

                        $date = \Illuminate\Support\Carbon::parse($data['first_installment_date']);
                        for ($i = 1; $i <= $count; $i++) {
                            $amount = $i === $count ? round($remaining - $each * ($count - 1), 2) : $each;
                            $deal->payments()->create([
                                'category' => 'deal', 'title' => "قسط {$i} من {$count}", 'amount' => $amount, 'due_date' => $date->copy(),
                            ]);
                            $date->addMonthsNoOverflow((int) $data['interval']);
                        }

                        Notification::make()->title('تم توليد جدول الدفعات')->success()->send();
                    }),

                // تسجيل العمولة المستحقة بكبسة
                Action::make('addCommission')
                    ->label('إضافة دفعات العمولة')
                    ->icon('heroicon-o-receipt-percent')
                    ->color('gray')
                    ->visible(fn (): bool => ! $deal->payments()->where('category', 'commission')->exists() && (float) $deal->total_commission > 0)
                    ->requiresConfirmation()
                    ->modalDescription(fn (): string => 'سيتم إضافة: عمولة الطرف الأول ' . $money($deal->buyer_commission_amount) . ' + عمولة الطرف الثاني ' . $money($deal->seller_commission_amount))
                    ->action(function () use ($deal): void {
                        $rent = $deal->transaction === 'rent';
                        foreach ([
                            [$rent ? 'عمولة المستأجر' : 'عمولة المشتري', $deal->buyer_commission_amount],
                            [$rent ? 'عمولة المؤجّر' : 'عمولة البائع', $deal->seller_commission_amount],
                        ] as [$title, $amount]) {
                            if ((float) $amount > 0) {
                                $deal->payments()->create(['category' => 'commission', 'title' => $title, 'amount' => $amount, 'due_date' => $deal->closed_at]);
                            }
                        }
                        Notification::make()->title('تمت إضافة دفعات العمولة')->success()->send();
                    }),

                CreateAction::make()->label('إضافة دفعة'),
            ])
            ->recordActions([
                MarkPaidAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
