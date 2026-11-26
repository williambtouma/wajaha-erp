<?php

namespace App\Filament\Resources\Deals\Schemas;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\Property;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class DealForm
{
    public static function configure(Schema $schema): Schema
    {
        // حساب العمولة مباشرة أثناء الكتابة
        $recalc = function (Get $get, Set $set): void {
            $price = (float) $get('price');
            $buyer = Deal::commissionAmount($get('buyer_commission_type'), $get('buyer_commission_value'), $price);
            $seller = Deal::commissionAmount($get('seller_commission_type'), $get('seller_commission_value'), $price);
            $total = $buyer + $seller;

            $set('buyer_commission_amount', $buyer);
            $set('seller_commission_amount', $seller);
            $set('total_commission', $total);
            $set('agent_commission', round($total * (float) $get('agent_share_percent') / 100, 2));
        };

        $isRent = fn (Get $get): bool => $get('transaction') === 'rent';

        return $schema
            ->columns(1)
            ->components([

                Section::make('العقار والأطراف')
                    ->columns(2)
                    ->schema([
                        Select::make('property_id')
                            ->label('العقار')
                            ->options(fn (?Deal $record): array => Property::query()
                                ->where(fn ($q) => $q->where('is_available', true)->when($record, fn ($q) => $q->orWhere('id', $record->property_id)))
                                ->latest()
                                ->get()
                                ->mapWithKeys(fn (Property $p) => [$p->id => "{$p->reference} — {$p->title}"])
                                ->all())
                            ->default(fn (): ?int => request()->integer('property') ?: null)
                            ->searchable()
                            ->required()
                            ->live()
                            ->disabledOn('edit')
                            ->afterStateUpdated(function (?string $state, Set $set, Get $get) use ($recalc): void {
                                if (! $property = Property::find($state)) {
                                    return;
                                }
                                $set('transaction', $property->transaction);
                                $set('price', (float) $property->price);
                                $set('currency', $property->currency);
                                $set('seller_name', $property->owner_name);
                                $set('seller_phone', $property->owner_phone);
                                $recalc($get, $set);
                            }),

                        Select::make('lead_id')
                            ->label('العميل (المشتري / المستأجر)')
                            ->options(fn (): array => Lead::query()
                                ->visibleTo(auth()->user())
                                ->where('type', Lead::BUYER)
                                ->latest()
                                ->limit(300)
                                ->get()
                                ->mapWithKeys(fn (Lead $l) => [$l->id => "{$l->reference} — {$l->name} ({$l->phone})"])
                                ->all())
                            ->default(fn (): ?int => request()->integer('lead') ?: null)
                            ->searchable()
                            ->live()
                            ->helperText('اختياري: إذا كان العميل مسجّلاً في النظام')
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (! $lead = Lead::find($state)) {
                                    return;
                                }
                                $set('buyer_name', $lead->name);
                                $set('buyer_phone', $lead->phone);
                                $set('agent_id', $lead->assigned_to);
                            }),

                        TextInput::make('buyer_name')
                            ->label(fn (Get $get): string => $get('transaction') === 'rent' ? 'اسم المستأجر' : 'اسم المشتري')
                            ->required(),

                        TextInput::make('buyer_phone')
                            ->label('هاتفه')
                            ->tel(),

                        TextInput::make('seller_name')
                            ->label(fn (Get $get): string => $get('transaction') === 'rent' ? 'اسم المؤجّر (المالك)' : 'اسم البائع (المالك)'),

                        TextInput::make('seller_phone')
                            ->label('هاتفه')
                            ->tel(),

                        Select::make('agent_id')
                            ->label('الوكيل')
                            ->options(fn (): array => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->default(fn (): ?int => auth()->id())
                            ->required(),

                        DatePicker::make('closed_at')
                            ->label('تاريخ التسكير')
                            ->native(false)
                            ->default(now())
                            ->required(),
                    ]),

                Section::make('السعر والعقد')
                    ->columns(3)
                    ->schema([
                        Select::make('transaction')
                            ->label('نوع الصفقة')
                            ->options(fn (): array => Lookup::options('transaction'))
                            ->required()
                            ->live(),

                        TextInput::make('price')
                            ->label(fn (Get $get): string => $get('transaction') === 'rent' ? 'قيمة العقد (الإيجار)' : 'سعر البيع النهائي')
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated($recalc),

                        Select::make('currency')
                            ->label('العملة')
                            ->options(fn (): array => Lookup::options('currency'))
                            ->default('USD')
                            ->required(),

                        DatePicker::make('contract_start')
                            ->label('بداية العقد')
                            ->native(false)
                            ->visible($isRent),

                        DatePicker::make('contract_end')
                            ->label('نهاية العقد')
                            ->native(false)
                            ->afterOrEqual('contract_start')
                            ->visible($isRent),
                    ]),

                Section::make('العمولة')
                    ->description('تُحسب تلقائياً — يمكن اختيار نسبة أو مبلغ ثابت لكل طرف')
                    ->columns(3)
                    ->schema([
                        Select::make('buyer_commission_type')
                            ->label(fn (Get $get): string => $get('transaction') === 'rent' ? 'عمولة المستأجر' : 'عمولة المشتري')
                            ->options(Deal::COMMISSION_TYPES)
                            ->default('percent')
                            ->required()
                            ->live()
                            ->afterStateUpdated($recalc),

                        TextInput::make('buyer_commission_value')
                            ->label(fn (Get $get): string => $get('buyer_commission_type') === 'fixed' ? 'المبلغ' : 'النسبة %')
                            ->numeric()
                            ->default(Deal::DEFAULT_BUYER_PERCENT)
                            ->live(onBlur: true)
                            ->afterStateUpdated($recalc),

                        TextInput::make('buyer_commission_amount')
                            ->label('= عمولة الطرف الأول')
                            ->disabled()
                            ->dehydrated(false)
                            ->numeric(),

                        Select::make('seller_commission_type')
                            ->label(fn (Get $get): string => $get('transaction') === 'rent' ? 'عمولة المؤجّر' : 'عمولة البائع')
                            ->options(Deal::COMMISSION_TYPES)
                            ->default('percent')
                            ->required()
                            ->live()
                            ->afterStateUpdated($recalc),

                        TextInput::make('seller_commission_value')
                            ->label(fn (Get $get): string => $get('seller_commission_type') === 'fixed' ? 'المبلغ' : 'النسبة %')
                            ->numeric()
                            ->default(Deal::DEFAULT_SELLER_PERCENT)
                            ->live(onBlur: true)
                            ->afterStateUpdated($recalc),

                        TextInput::make('seller_commission_amount')
                            ->label('= عمولة الطرف الثاني')
                            ->disabled()
                            ->dehydrated(false)
                            ->numeric(),

                        TextInput::make('total_commission')
                            ->label('إجمالي عمولة وجاهة')
                            ->disabled()
                            ->dehydrated(false)
                            ->numeric()
                            ->extraInputAttributes(['style' => 'font-weight:700;color:#043c36']),

                        TextInput::make('agent_share_percent')
                            ->label('حصة الوكيل %')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(Deal::DEFAULT_AGENT_SHARE)
                            ->live(onBlur: true)
                            ->afterStateUpdated($recalc),

                        TextInput::make('agent_commission')
                            ->label('= عمولة الوكيل')
                            ->disabled()
                            ->dehydrated(false)
                            ->numeric(),
                    ]),

                Section::make('العقد والمستندات')
                    ->collapsible()
                    ->schema([
                        Repeater::make('documents')
                            ->hiddenLabel()
                            ->defaultItems(0)
                            ->addActionLabel('إضافة مستند (العقد، الإيصالات...)')
                            ->collapsible()
                            ->columns(3)
                            ->itemLabel(fn (array $state): ?string => ($state['title'] ?? null) ?: Lookup::label('document_type', $state['type'] ?? null))
                            ->schema([
                                Select::make('type')
                                    ->label('نوع المستند')
                                    ->options(fn (): array => Lookup::options('document_type'))
                                    ->default('contract')
                                    ->required(),

                                TextInput::make('title')
                                    ->label('اسم المستند'),

                                FileUpload::make('file')
                                    ->label('الملف')
                                    ->required()
                                    ->disk('local')
                                    ->directory('deal-documents')
                                    ->visibility('private')
                                    ->acceptedFileTypes(['application/pdf', 'image/*', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                                    ->maxSize(20480)
                                    ->storeFileNamesIn('file_name')
                                    ->downloadable()
                                    ->openable(),
                            ]),
                    ]),

                Section::make('ملاحظات')
                    ->collapsible()
                    ->schema([
                        Textarea::make('notes')
                            ->hiddenLabel()
                            ->rows(3),
                    ]),
            ]);
    }
}
