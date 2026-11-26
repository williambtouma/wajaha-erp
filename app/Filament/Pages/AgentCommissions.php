<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Finance\Transactions\FinanceTransactionResource;
use App\Models\Deal;
use App\Models\ExchangeRate;
use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Models\Lookup;
use App\Models\User;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

/** عمولات الوكلاء: المستحق من الصفقات، المصروف، المتبقي (بالدولار) */
class AgentCommissions extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'المالية';

    protected static ?string $navigationLabel = 'عمولات الوكلاء';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.agent-commissions';

    public function getTitle(): string
    {
        return 'عمولات الوكلاء';
    }

    /** المستحق = حصة الوكيل من الصفقات غير الملغاة (محوّلة للدولار) */
    public static function earnedUsd(User $user): float
    {
        return (float) $user->deals()
            ->where('status', '!=', 'cancelled')
            ->get(['agent_commission', 'currency'])
            ->sum(fn (Deal $d) => ExchangeRate::toUsd($d->agent_commission, $d->currency));
    }

    public static function paidUsd(User $user): float
    {
        return (float) FinanceTransaction::query()
            ->where('user_id', $user->id)
            ->where('direction', 'out')
            ->where('category', 'agent_commission')
            ->sum('amount_usd');
    }

    public function table(Table $table): Table
    {
        $usd = fn ($v) => Lookup::money($v, 'USD');

        return $table
            ->query(fn () => User::query()
                ->whereHas('deals', fn ($q) => $q->where('status', '!=', 'cancelled'))
                ->withCount(['deals as deals_count' => fn ($q) => $q->where('status', '!=', 'cancelled')]))
            ->emptyStateHeading('لا توجد صفقات مسجّلة للوكلاء بعد')
            ->columns([
                TextColumn::make('name')
                    ->label('الوكيل')
                    ->weight('bold')
                    ->description(fn (User $record): string => "{$record->deals_count} صفقة"),

                TextColumn::make('earned')
                    ->label('المستحق')
                    ->state(fn (User $record): float => static::earnedUsd($record))
                    ->formatStateUsing(fn ($state): string => $usd($state)),

                TextColumn::make('paid')
                    ->label('المصروف')
                    ->color('success')
                    ->state(fn (User $record): float => static::paidUsd($record))
                    ->formatStateUsing(fn ($state): string => $usd($state)),

                TextColumn::make('remaining')
                    ->label('المتبقي')
                    ->weight('bold')
                    ->state(fn (User $record): float => round(static::earnedUsd($record) - static::paidUsd($record), 2))
                    ->formatStateUsing(fn ($state): string => $usd($state))
                    ->color(fn ($state): string => $state > 0 ? 'danger' : 'gray'),
            ])
            ->recordActions([
                Action::make('pay')
                    ->label('صرف عمولة')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->button()
                    ->modalHeading(fn (User $record): string => "صرف عمولة: {$record->name}")
                    ->modalDescription(fn (User $record): string => 'المتبقي له: ' . $usd(static::earnedUsd($record) - static::paidUsd($record)))
                    ->schema([
                        Select::make('account_id')
                            ->label('من حساب')
                            ->options(fn (): array => FinanceAccount::options())
                            ->default(fn () => array_key_first(FinanceAccount::options('USD')) ?? array_key_first(FinanceAccount::options()))
                            ->required()
                            ->live(),
                        TextInput::make('amount')
                            ->label('المبلغ (بعملة الحساب)')
                            ->numeric()
                            ->minValue(0.01)
                            ->default(fn (User $record) => max(0, round(static::earnedUsd($record) - static::paidUsd($record), 2)))
                            ->required(),
                        TextInput::make('exchange_rate')
                            ->label('سعر الصرف')
                            ->numeric()
                            ->default(fn () => ExchangeRate::current())
                            ->visible(fn (Get $get): bool => (FinanceAccount::find($get('account_id'))?->currency ?? 'USD') !== 'USD')
                            ->required(fn (Get $get): bool => (FinanceAccount::find($get('account_id'))?->currency ?? 'USD') !== 'USD'),
                        DatePicker::make('date')
                            ->label('التاريخ')
                            ->native(false)
                            ->default(now())
                            ->required(),
                        TextInput::make('description')
                            ->label('البيان')
                            ->default('دفعة من عمولات الصفقات'),
                    ])
                    ->action(function (User $record, array $data): void {
                        FinanceTransaction::create([
                            'date' => $data['date'],
                            'account_id' => $data['account_id'],
                            'direction' => 'out',
                            'category' => 'agent_commission',
                            'amount' => $data['amount'],
                            'exchange_rate' => $data['exchange_rate'] ?? 1,
                            'user_id' => $record->id,
                            'description' => $data['description'] ?? null,
                        ]);

                        Notification::make()->title("تم صرف العمولة لـ {$record->name}")->success()->send();
                    }),

                Action::make('history')
                    ->label('السجل')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->url(fn (User $record): string => FinanceTransactionResource::getUrl('index', ['employee' => $record->id])),
            ]);
    }
}
