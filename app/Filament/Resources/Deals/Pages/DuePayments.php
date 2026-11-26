<?php

namespace App\Filament\Resources\Deals\Pages;

use App\Filament\Resources\Deals\Actions\MarkPaidAction;
use App\Filament\Resources\Deals\DealResource;
use App\Models\DealPayment;
use App\Models\Lookup;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/** كل الدفعات غير المسددة عبر كل الصفقات (الأقرب استحقاقاً أولاً) */
class DuePayments extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = DealResource::class;

    protected string $view = 'filament.deals.due-payments';

    public function getTitle(): string|Htmlable
    {
        return 'الدفعات المستحقة';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => DealPayment::query()
                ->with('deal.property')
                ->whereNull('paid_at')
                ->whereHas('deal', fn ($q) => $q->whereIn('id', DealResource::getEloquentQuery()->select('id'))->where('status', '!=', 'cancelled'))
                ->orderByRaw('due_date is null')
                ->orderBy('due_date'))
            ->recordUrl(fn (DealPayment $record): string => DealResource::getUrl('edit', ['record' => $record->deal_id]))
            ->columns([
                TextColumn::make('deal.reference')
                    ->label('الصفقة')
                    ->weight('bold')
                    ->description(fn (DealPayment $record): ?string => $record->deal?->buyer_name),

                TextColumn::make('deal.property.title')
                    ->label('العقار')
                    ->limit(30),

                TextColumn::make('category')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => DealPayment::CATEGORIES[$state] ?? $state)
                    ->color(fn (string $state): string => $state === 'commission' ? 'primary' : 'gray'),

                TextColumn::make('title')
                    ->label('البيان'),

                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->formatStateUsing(fn (DealPayment $record): string => Lookup::money($record->amount, $record->deal?->currency)),

                TextColumn::make('due_date')
                    ->label('الاستحقاق')
                    ->date('Y/m/d')
                    ->placeholder('بدون تاريخ')
                    ->color(fn (DealPayment $record): ?string => $record->status() === 'overdue' ? 'danger' : null)
                    ->description(fn (DealPayment $record): ?string => $record->due_date?->diffForHumans()),
            ])
            ->filters([
                SelectFilter::make('category')->label('النوع')->options(DealPayment::CATEGORIES),
                TernaryFilter::make('overdue')
                    ->label('الاستحقاق')
                    ->placeholder('الكل')
                    ->trueLabel('المتأخرة فقط')
                    ->falseLabel('القادمة فقط')
                    ->queries(
                        true: fn (Builder $q) => $q->whereDate('due_date', '<', today()),
                        false: fn (Builder $q) => $q->whereDate('due_date', '>=', today()),
                    ),
            ])
            ->recordActions([
                MarkPaidAction::make(),
            ]);
    }
}
