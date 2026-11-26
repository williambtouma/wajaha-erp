<?php

namespace App\Filament\Resources\Finance\Transactions\Pages;

use App\Filament\Resources\Finance\Transactions\FinanceTransactionResource;
use App\Models\ExchangeRate;
use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Models\Lookup;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

class ManageFinanceTransactions extends ManageRecords
{
    protected static string $resource = FinanceTransactionResource::class;

    #[Url]
    public ?string $direction = null;

    #[Url]
    public ?string $account = null;

    #[Url(as: 'employee')]
    public ?string $employee = null;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $q) => $q
                ->when($this->direction, fn ($q, $v) => $q->where('direction', $v)->where('category', '!=', FinanceTransaction::TRANSFER))
                ->when($this->account, fn ($q, $v) => $q->where('account_id', $v))
                ->when($this->employee, fn ($q, $v) => $q->where('user_id', $v)));
    }

    public function getTitle(): string
    {
        $title = match ($this->direction) {
            'in' => 'الإيرادات',
            'out' => 'المصاريف',
            default => 'الحركات المالية',
        };

        if ($this->account && $account = FinanceAccount::find($this->account)) {
            $title = 'كشف حساب: ' . $account->name;
        }

        return $title;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(match ($this->direction) {
                    'in' => 'إضافة إيراد',
                    'out' => 'إضافة مصروف',
                    default => 'إضافة حركة',
                })
                ->icon('heroicon-o-plus'),

            Action::make('transfer')
                ->label('تحويل بين الحسابات')
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->modalHeading('تحويل بين الحسابات')
                ->schema([
                    Select::make('from')
                        ->label('من حساب')
                        ->options(fn (): array => FinanceAccount::options())
                        ->required()
                        ->live(),
                    Select::make('to')
                        ->label('إلى حساب')
                        ->options(fn (): array => FinanceAccount::options())
                        ->different('from')
                        ->required()
                        ->live(),
                    TextInput::make('amount')
                        ->label('المبلغ المحوَّل (بعملة الحساب المرسل)')
                        ->numeric()
                        ->required(),
                    TextInput::make('to_amount')
                        ->label('المبلغ المستلَم (بعملة الحساب المستقبِل)')
                        ->numeric()
                        ->helperText('مثال: صرف دولار إلى ليرة')
                        ->visible(fn (Get $get): bool => $this->currenciesDiffer($get('from'), $get('to')))
                        ->required(fn (Get $get): bool => $this->currenciesDiffer($get('from'), $get('to'))),
                    DatePicker::make('date')
                        ->label('التاريخ')
                        ->native(false)
                        ->default(now())
                        ->required(),
                    TextInput::make('description')
                        ->label('البيان'),
                ])
                ->action(function (array $data): void {
                    $from = FinanceAccount::findOrFail($data['from']);
                    $to = FinanceAccount::findOrFail($data['to']);
                    $toAmount = $from->currency === $to->currency ? $data['amount'] : $data['to_amount'];
                    $group = (string) Str::uuid();

                    // سعر الصرف الفعلي للتحويل
                    $rate = match (true) {
                        $from->currency === 'USD' && $to->currency !== 'USD' => (float) $toAmount / max(0.0001, (float) $data['amount']),
                        $from->currency !== 'USD' && $to->currency === 'USD' => (float) $data['amount'] / max(0.0001, (float) $toAmount),
                        default => null,
                    };

                    DB::transaction(function () use ($data, $from, $to, $toAmount, $group, $rate) {
                        $base = ['date' => $data['date'], 'category' => FinanceTransaction::TRANSFER, 'transfer_group' => $group];
                        $note = $data['description'] ?: "تحويل من {$from->name} إلى {$to->name}";

                        FinanceTransaction::create($base + ['account_id' => $from->id, 'direction' => 'out', 'amount' => $data['amount'],
                            'exchange_rate' => $from->currency === 'USD' ? 1 : ($rate ?? ExchangeRate::current($from->currency)), 'description' => $note]);
                        FinanceTransaction::create($base + ['account_id' => $to->id, 'direction' => 'in', 'amount' => $toAmount,
                            'exchange_rate' => $to->currency === 'USD' ? 1 : ($rate ?? ExchangeRate::current($to->currency)), 'description' => $note]);
                    });

                    Notification::make()->title('تم التحويل')->success()->send();
                }),
        ];
    }

    protected function currenciesDiffer(?string $from, ?string $to): bool
    {
        if (! $from || ! $to) {
            return false;
        }

        return FinanceAccount::find($from)?->currency !== FinanceAccount::find($to)?->currency;
    }
}
