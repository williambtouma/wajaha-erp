<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Filament\Resources\Leads\Actions\ChangeStageAction;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        $filter = fn (string $column, string $list, string $label) => SelectFilter::make($column)
            ->label($label)
            ->options(fn (): array => Lookup::options($list));

        $badge = fn (string $column, string $list, string $label) => TextColumn::make($column)
            ->label($label)
            ->badge()
            ->placeholder('—')
            ->formatStateUsing(fn (?string $state): ?string => Lookup::label($list, $state))
            ->color(fn (?string $state): string => Lookup::color($list, $state));

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('العميل')
                    ->searchable(['name', 'phone', 'phone2', 'reference'])
                    ->weight('bold')
                    ->description(fn (Lead $record): string => "{$record->reference} · \u{2066}{$record->phone}\u{2069}"),

                $badge('type', 'lead_type', 'النوع'),

                TextColumn::make('interest')
                    ->label('الطلب')
                    ->state(fn (Lead $record): ?string => $record->interestSummary())
                    ->placeholder('—')
                    ->wrap()
                    ->limit(60),

                $badge('stage', 'lead_stage', 'المرحلة')
                    ->description(fn (Lead $record): ?string => $record->stage === 'lost' ? Lookup::label('lost_reason', $record->lost_reason) : null),

                $badge('priority', 'lead_priority', 'الأولوية')
                    ->toggleable(),

                TextColumn::make('next_follow_up_at')
                    ->label('المتابعة القادمة')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->color(fn (Lead $record): ?string => ! $record->isClosed() && $record->next_follow_up_at?->isPast() ? 'danger' : null)
                    ->icon(fn (Lead $record): ?string => ! $record->isClosed() && $record->next_follow_up_at?->isPast() ? 'heroicon-m-exclamation-triangle' : null)
                    ->description(fn (Lead $record): ?string => $record->next_follow_up_at?->diffForHumans()),

                TextColumn::make('assignee.name')
                    ->label('الوكيل')
                    ->placeholder('—')
                    ->toggleable(),

                $badge('source', 'lead_source', 'المصدر')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('أضيف')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                $filter('type', 'lead_type', 'نوع العميل'),
                $filter('stage', 'lead_stage', 'المرحلة'),
                $filter('priority', 'lead_priority', 'الأولوية'),
                $filter('source', 'lead_source', 'المصدر'),
                SelectFilter::make('assigned_to')
                    ->label('الوكيل')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->visible(fn (): bool => (bool) auth()->user()?->canSeeAllLeads()),
                Filter::make('overdue')
                    ->label('متابعات متأخرة')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->open()->where('next_follow_up_at', '<', now())),
            ])
            ->recordActions([
                Action::make('whatsapp')
                    ->label('')
                    ->tooltip('واتساب')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->url(fn (Lead $record): ?string => $record->whatsappUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (Lead $record): bool => filled($record->whatsappUrl())),
                ChangeStageAction::make()->button(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
