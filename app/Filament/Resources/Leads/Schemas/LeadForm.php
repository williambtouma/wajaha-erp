<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Models\Lead;
use App\Models\Lookup;
use App\Models\Property;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        $type = fn (Get $get): ?string => $get('type');
        $is = fn (string ...$types) => fn (Get $get): bool => in_array($get('type'), $types);
        $canAssign = fn (): bool => (bool) auth()->user()?->canAssignLeads();

        return $schema
            ->columns(1)
            ->components([

                Section::make('بيانات العميل')
                    ->columns(3)
                    ->schema([
                        Select::make('type')
                            ->label('نوع العميل')
                            ->options(fn (): array => Lookup::options('lead_type'))
                            ->default(fn (): ?string => request()->query('type'))
                            ->required()
                            ->live(),

                        TextInput::make('name')
                            ->label('الاسم')
                            ->required(),

                        TextInput::make('phone')
                            ->label('الهاتف')
                            ->tel()
                            ->required(),

                        TextInput::make('phone2')
                            ->label('واتساب / رقم إضافي')
                            ->tel(),

                        TextInput::make('email')
                            ->label('البريد الإلكتروني')
                            ->email(),

                        Select::make('source')
                            ->label('المصدر')
                            ->options(fn (): array => Lookup::options('lead_source')),

                        Select::make('priority')
                            ->label('الأولوية')
                            ->options(fn (): array => Lookup::options('lead_priority'))
                            ->default('warm'),

                        Select::make('assigned_to')
                            ->label('الوكيل المسؤول')
                            ->options(fn (): array => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->default(fn (): ?int => auth()->id())
                            ->disabled(fn (): bool => ! $canAssign())
                            ->dehydrated()
                            ->required(),

                        Select::make('stage')
                            ->label('المرحلة')
                            ->options(fn (): array => Lookup::options('lead_stage'))
                            ->default('new')
                            ->disabledOn('edit')
                            ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'تُغيَّر من زر "المرحلة"' : null),
                    ]),

                // ===== مشتري / مستأجر =====
                Section::make(fn (Get $get): string => $get('type') === Lead::OWNER ? 'العقار المعروض' : 'العقار المطلوب')
                    ->columns(3)
                    ->visible($is(Lead::BUYER, Lead::OWNER))
                    ->schema([
                        Select::make('transaction')
                            ->label(fn (Get $get): string => $get('type') === Lead::OWNER ? 'يريد' : 'يبحث عن')
                            ->options(fn (): array => Lookup::options('transaction')),

                        Select::make('category')
                            ->label('نوع العقار')
                            ->options(fn (): array => Lookup::options('category'))
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('subtype', null)),

                        Select::make('subtype')
                            ->label('التصنيف الفرعي')
                            ->options(fn (Get $get): array => filled($get('category')) ? Lookup::options('subtype', $get('category')) : []),

                        Select::make('city')
                            ->label('المدينة')
                            ->options(fn (): array => Lookup::options('city')),

                        TextInput::make('area')
                            ->label('المنطقة'),

                        TextInput::make('bedrooms_min')
                            ->label(fn (Get $get): string => $get('type') === Lead::OWNER ? 'عدد الغرف' : 'أقل عدد غرف')
                            ->numeric()
                            ->minValue(0),

                        TextInput::make('budget_min')
                            ->label('الميزانية من')
                            ->numeric()
                            ->visible($is(Lead::BUYER)),

                        TextInput::make('budget_max')
                            ->label(fn (Get $get): string => $get('type') === Lead::OWNER ? 'السعر المطلوب' : 'الميزانية حتى')
                            ->numeric(),

                        Select::make('currency')
                            ->label('العملة')
                            ->options(fn (): array => Lookup::options('currency'))
                            ->default('USD'),

                        Select::make('interested_property_id')
                            ->label('مهتم بعقار محدد')
                            ->options(fn (): array => Property::query()->where('is_available', true)->latest()->limit(200)->get()
                                ->mapWithKeys(fn (Property $p) => [$p->id => "{$p->reference} — {$p->title}"])->all())
                            ->searchable()
                            ->visible($is(Lead::BUYER))
                            ->columnSpan(2),

                        Select::make('details.ownership_type')
                            ->label('صفة المعلن')
                            ->options(fn (): array => Lookup::options('ownership_type'))
                            ->visible($is(Lead::OWNER)),
                    ]),

                // ===== مستثمر =====
                Section::make('تفاصيل الاستثمار')
                    ->columns(3)
                    ->visible($is(Lead::INVESTOR))
                    ->schema([
                        Select::make('details.investment_type')
                            ->label('نوع الاستثمار')
                            ->options(fn (): array => Lookup::options('investment_type')),

                        Select::make('details.investment_objective')
                            ->label('الهدف الاستثماري')
                            ->options(fn (): array => Lookup::options('investment_objective')),

                        Select::make('details.investment_timeline')
                            ->label('الإطار الزمني')
                            ->options(fn (): array => Lookup::options('investment_timeline')),

                        TextInput::make('budget_max')
                            ->label('رأس المال المتاح')
                            ->numeric(),

                        Select::make('currency')
                            ->label('العملة')
                            ->options(fn (): array => Lookup::options('currency'))
                            ->default('USD'),

                        Select::make('city')
                            ->label('المدينة المفضلة')
                            ->options(fn (): array => Lookup::options('city')),
                    ]),

                // ===== مطوّر =====
                Section::make('تفاصيل المشروع')
                    ->columns(3)
                    ->visible($is(Lead::DEVELOPER))
                    ->schema([
                        TextInput::make('details.project_name')
                            ->label('اسم المشروع'),

                        Select::make('details.project_type')
                            ->label('نوع المشروع')
                            ->options(fn (): array => Lookup::options('project_type')),

                        Select::make('details.project_status')
                            ->label('حالة المشروع')
                            ->options(fn (): array => Lookup::options('project_status')),

                        TextInput::make('details.units_count')
                            ->label('عدد الوحدات')
                            ->numeric(),

                        Select::make('city')
                            ->label('المدينة')
                            ->options(fn (): array => Lookup::options('city')),

                        TextInput::make('area')
                            ->label('المنطقة'),
                    ]),

                Section::make('المتابعة')
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('next_follow_up_at')
                            ->label('موعد المتابعة القادمة')
                            ->native(false)
                            ->seconds(false),

                        Textarea::make('notes')
                            ->label('ملاحظات عامة')
                            ->rows(3),
                    ]),
            ]);
    }
}
