<?php

namespace App\Filament\Resources\Deals\Tables;

use App\Models\Deal;
use App\Models\Lookup;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DealsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('closed_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['property', 'agent'])
                ->withSum(['payments as paid_amount' => fn ($q) => $q->where('category', 'deal')->whereNotNull('paid_at')], 'amount')
                ->withSum(['payments as scheduled_amount' => fn ($q) => $q->where('category', 'deal')], 'amount')
                ->withSum(['payments as commission_collected' => fn ($q) => $q->where('category', 'commission')->whereNotNull('paid_at')], 'amount'))
            ->columns([
                TextColumn::make('reference')
                    ->label('الصفقة')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Deal $record): string => $record->closed_at->format('Y/m/d')),

                TextColumn::make('property.title')
                    ->label('العقار')
                    ->limit(35)
                    ->searchable()
                    ->description(fn (Deal $record): ?string => $record->property?->reference),

                TextColumn::make('transaction')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): ?string => Lookup::label('transaction', $state))
                    ->color(fn (?string $state): string => Lookup::color('transaction', $state)),

                TextColumn::make('buyer_name')
                    ->label('المشتري / المستأجر')
                    ->searchable()
                    ->description(fn (Deal $record): ?string => $record->seller_name ? 'البائع: ' . $record->seller_name : null),

                TextColumn::make('price')
                    ->label('القيمة')
                    ->sortable()
                    ->formatStateUsing(fn (Deal $record): string => $record->money($record->price))
                    ->description(function (Deal $record): ?string {
                        if (! (float) $record->scheduled_amount) {
                            return null;
                        }
                        $percent = round((float) $record->paid_amount / max(1, (float) $record->price) * 100);

                        return "مدفوع {$percent}%";
                    }),

                TextColumn::make('total_commission')
                    ->label('العمولة')
                    ->sortable()
                    ->formatStateUsing(fn (Deal $record): string => $record->money($record->total_commission))
                    ->description(fn (Deal $record): string => 'محصّل: ' . $record->money($record->commission_collected ?? 0))
                    ->color('primary'),

                TextColumn::make('agent.name')
                    ->label('الوكيل')
                    ->description(fn (Deal $record): string => 'عمولته: ' . $record->money($record->agent_commission))
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Deal::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => Deal::STATUS_COLORS[$state] ?? 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options(Deal::STATUSES),
                SelectFilter::make('transaction')->label('النوع')->options(fn (): array => Lookup::options('transaction')),
                SelectFilter::make('agent_id')
                    ->label('الوكيل')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                EditAction::make()->label('فتح'),
            ]);
    }
}
