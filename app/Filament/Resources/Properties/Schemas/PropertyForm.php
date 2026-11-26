<?php

namespace App\Filament\Resources\Properties\Schemas;

use App\Models\Property;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PropertyForm
{
    public static function configure(Schema $schema): Schema
    {
        $isLand = fn (Get $get): bool => in_array($get('subtype'), ['land', 'farm']);
        $isForSale = fn (Get $get): bool => $get('transaction') === 'sale';
        $isUnderConstruction = fn (Get $get): bool => $get('transaction') === 'sale' && $get('readiness') === 'under_construction';

        return $schema
            ->columns(1)
            ->components([

                Section::make('معلومات العقار')
                    ->columns(2)
                    ->schema([
                        TextInput::make('reference')
                            ->label('رقم العقار')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('يتولّد تلقائياً بعد الحفظ'),

                        Toggle::make('is_featured')
                            ->label('عقار مميز')
                            ->inline(false),

                        TextInput::make('title')
                            ->label('عنوان الإعلان')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('category')
                            ->label('نوع العقار')
                            ->options(Property::CATEGORIES)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('subtype', null)),

                        Select::make('subtype')
                            ->label('التصنيف الفرعي')
                            ->options(fn (Get $get): array => Property::SUBTYPES[$get('category')] ?? [])
                            ->required()
                            ->live()
                            ->disabled(fn (Get $get): bool => blank($get('category')))
                            ->helperText(fn (Get $get): ?string => blank($get('category')) ? 'اختر نوع العقار أولاً' : null),

                        Select::make('transaction')
                            ->label('نوع المعاملة')
                            ->options(Property::TRANSACTIONS)
                            ->required()
                            ->live(),

                        Select::make('readiness')
                            ->label('جاهزية العقار')
                            ->options(Property::READINESS)
                            ->required($isForSale)
                            ->visible($isForSale)
                            ->live(),

                        DatePicker::make('expected_delivery')
                            ->label('تاريخ التسليم المتوقع')
                            ->native(false)
                            ->displayFormat('m/Y')
                            ->visible($isUnderConstruction),

                        Select::make('status')
                            ->label('حالة التوفر')
                            ->options(Property::STATUSES)
                            ->default('available')
                            ->required(),
                    ]),

                Section::make('الموقع والسعر')
                    ->columns(2)
                    ->schema([
                        Select::make('city')
                            ->label('المدينة')
                            ->options(Property::CITIES)
                            ->required(),

                        TextInput::make('area')
                            ->label('المنطقة')
                            ->placeholder('مثال: ناعور، الجاردنز، دابوق'),

                        TextInput::make('price')
                            ->label('السعر')
                            ->numeric()
                            ->minValue(0)
                            ->required(),

                        Select::make('currency')
                            ->label('العملة')
                            ->options(Property::CURRENCIES)
                            ->default('USD')
                            ->required(),
                    ]),

                Section::make('تفاصيل العقار')
                    ->columns(3)
                    ->schema([
                        TextInput::make('built_area')
                            ->label('مساحة العقار (م²)')
                            ->numeric()
                            ->hidden($isLand),

                        TextInput::make('land_area')
                            ->label('مساحة الأرض (م²)')
                            ->numeric(),

                        TextInput::make('year_built')
                            ->label('سنة البناء')
                            ->numeric()
                            ->minValue(1900)
                            ->maxValue(date('Y') + 5)
                            ->hidden($isLand),

                        TextInput::make('bedrooms')
                            ->label('غرف النوم')
                            ->numeric()
                            ->minValue(0)
                            ->hidden($isLand),

                        TextInput::make('bathrooms')
                            ->label('الحمامات')
                            ->numeric()
                            ->minValue(0)
                            ->hidden($isLand),

                        Select::make('condition')
                            ->label('حالة العقار')
                            ->options(Property::CONDITIONS)
                            ->hidden(fn (Get $get): bool => $isLand($get) || $isUnderConstruction($get)),

                        Select::make('furnishing')
                            ->label('التأثيث')
                            ->options(Property::FURNISHINGS)
                            ->hidden($isLand),
                    ]),

                Section::make('الوصف والصور')
                    ->schema([
                        Textarea::make('description')
                            ->label('وصف العقار')
                            ->rows(6),

                        FileUpload::make('images')
                            ->label('صور العقار')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->maxFiles(20)
                            ->disk('public')
                            ->directory('properties')
                            ->panelLayout('grid'),
                    ]),

                Section::make('بيانات المالك')
                    ->description('معلومات داخلية، لا تظهر على الموقع')
                    ->columns(3)
                    ->collapsible()
                    ->schema([
                        TextInput::make('owner_name')
                            ->label('اسم المالك'),

                        TextInput::make('owner_phone')
                            ->label('رقم الهاتف')
                            ->tel(),

                        Select::make('ownership_type')
                            ->label('صفة المعلن')
                            ->options(Property::OWNERSHIP_TYPES),
                    ]),
            ]);
    }
}
