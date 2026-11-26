<?php

namespace App\Filament\Resources\Leads\Widgets;

use App\Filament\Resources\Properties\PropertyResource;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\Property;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * العقارات المتاحة المطابقة لطلب المشتري:
 * نفس المعاملة + نوع العقار، ثم التصنيف والمدينة والميزانية والغرف إن وُجدت.
 */
class MatchingProperties extends TableWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        /** @var Lead $lead */
        $lead = $this->record;

        return $table
            ->heading('عقارات مطابقة لطلب العميل')
            ->description('العقارات المتاحة حسب المعاملة، والنوع، والتصنيف، والمدينة، والميزانية، والغرف')
            ->query(fn (): Builder => static::matchQuery($lead))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('لا توجد عقارات مطابقة حالياً')
            ->emptyStateDescription('جرّب توسيع الميزانية أو ترك التصنيف/المدينة فارغة في طلب العميل.')
            ->recordUrl(fn (Property $record): string => PropertyResource::getUrl('edit', ['record' => $record]))
            ->columns([
                ImageColumn::make('images')
                    ->label('')
                    ->disk('public')
                    ->visibility('public')
                    ->limit(1)
                    ->square(),

                TextColumn::make('title')
                    ->label('العقار')
                    ->limit(45)
                    ->description(fn (Property $record): string => $record->reference . ' · ' . trim($record->city . ' - ' . $record->area, ' -')),

                TextColumn::make('subtype')
                    ->label('التصنيف')
                    ->formatStateUsing(fn (Property $record): ?string => $record->subtypeLabel()),

                TextColumn::make('price')
                    ->label('السعر')
                    ->formatStateUsing(fn (Property $record): string => $record->formattedPrice()),

                TextColumn::make('bedrooms')
                    ->label('غرف')
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('sendWhatsapp')
                    ->label('إرسال')
                    ->tooltip('إرسال تفاصيل العقار للعميل عبر واتساب')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->url(fn (Property $record): ?string => static::whatsappShareUrl($lead, $record))
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => filled($lead->whatsappUrl())),

                Action::make('markInterested')
                    ->label('مهتم')
                    ->icon('heroicon-o-heart')
                    ->color(fn (Property $record): string => $lead->interested_property_id === $record->id ? 'danger' : 'gray')
                    ->action(function (Property $record) use ($lead): void {
                        $lead->update(['interested_property_id' => $record->id]);
                        $lead->activities()->create(['type' => 'note', 'body' => "مهتم بالعقار {$record->reference}"]);

                        Notification::make()->title('تم ربط العقار بالعميل')->success()->send();
                    }),
            ]);
    }

    public static function matchQuery(Lead $lead): Builder
    {
        return Property::query()
            ->where('is_available', true)
            ->when($lead->transaction, fn ($q, $v) => $q->where('transaction', $v))
            ->when($lead->category, fn ($q, $v) => $q->where('category', $v))
            ->when($lead->subtype, fn ($q, $v) => $q->where('subtype', $v))
            ->when($lead->city, fn ($q, $v) => $q->where('city', $v))
            ->when($lead->bedrooms_min, fn ($q, $v) => $q->where(fn ($q) => $q->whereNull('bedrooms')->orWhere('bedrooms', '>=', $v)))
            ->when($lead->budget_max || $lead->budget_min, fn ($q) => $q
                ->where('currency', $lead->currency ?? 'USD')
                ->when($lead->budget_min, fn ($q, $v) => $q->where('price', '>=', $v))
                // هامش 10% فوق الميزانية
                ->when($lead->budget_max, fn ($q, $v) => $q->where('price', '<=', $v * 1.1)))
            ->orderByDesc('is_featured')
            ->latest();
    }

    public static function whatsappShareUrl(Lead $lead, Property $property): ?string
    {
        $base = $lead->whatsappUrl();
        if (! $base) {
            return null;
        }

        $lines = array_filter([
            "مرحباً {$lead->name}،",
            'نرشّح لك هذا العقار من وجاهة للتسويق العقاري:',
            '',
            "🏠 {$property->title}",
            '📍 ' . trim($property->city . ' - ' . $property->area, ' -'),
            '💰 ' . $property->formattedPrice(),
            $property->built_area ? '📐 ' . number_format((float) $property->built_area) . ' م²' : null,
            $property->bedrooms ? "🛏 {$property->bedrooms} غرف" : null,
            "🔖 رقم العقار: {$property->reference}",
        ], fn ($line) => $line !== null);

        return $base . '?text=' . rawurlencode(implode("\n", $lines));
    }
}
