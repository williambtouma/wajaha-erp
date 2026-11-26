<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Models\Lookup;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** متابعاتي: العملاء المسندون لي والمستحقون اليوم أو المتأخرون */
class MyFollowUps extends TableWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('viewAny', Lead::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('متابعاتي اليوم')
            ->description('العملاء المسندون إليك ومواعيدهم اليوم أو متأخرة')
            ->query(fn () => Lead::query()
                ->where('assigned_to', auth()->id())
                ->followUpDue()
                ->orderBy('next_follow_up_at'))
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('لا توجد متابعات اليوم 👌')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->recordUrl(fn (Lead $record): string => LeadResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label('العميل')
                    ->weight('bold')
                    ->description(fn (Lead $record): string => "\u{2066}{$record->phone}\u{2069}"),

                TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): ?string => Lookup::label('lead_type', $state))
                    ->color(fn (?string $state): string => Lookup::color('lead_type', $state)),

                TextColumn::make('stage')
                    ->label('المرحلة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): ?string => Lookup::label('lead_stage', $state))
                    ->color(fn (?string $state): string => Lookup::color('lead_stage', $state)),

                TextColumn::make('next_follow_up_at')
                    ->label('الموعد')
                    ->dateTime('H:i — Y/m/d')
                    ->color(fn (Lead $record): ?string => $record->next_follow_up_at?->isPast() ? 'danger' : null)
                    ->description(fn (Lead $record): ?string => $record->next_follow_up_at?->diffForHumans()),
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
            ]);
    }
}
