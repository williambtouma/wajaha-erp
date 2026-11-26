<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Models\LeadActivity;
use App\Models\Lookup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** سجل المتابعة: اتصالات، واتساب، اجتماعات، معاينات، ملاحظات */
class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'سجل المتابعة';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('type')
                    ->label('النوع')
                    ->options(fn (): array => Lookup::options('activity_type'))
                    ->default('call')
                    ->required()
                    ->live(),

                DateTimePicker::make('scheduled_at')
                    ->label('الموعد')
                    ->helperText('يصبح موعد المتابعة القادم للعميل')
                    ->native(false)
                    ->seconds(false)
                    ->visible(fn (Get $get): bool => in_array($get('type'), ['meeting', 'viewing'])),

                Textarea::make('body')
                    ->label('التفاصيل')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('Y/m/d H:i')
                    ->description(fn (LeadActivity $record): string => $record->created_at->diffForHumans()),

                TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'stage_change' ? 'تغيير مرحلة' : Lookup::label('activity_type', $state))
                    ->color(fn (string $state): string => $state === 'stage_change' ? 'gray' : Lookup::color('activity_type', $state)),

                TextColumn::make('body')
                    ->label('التفاصيل')
                    ->wrap()
                    ->formatStateUsing(fn (?string $state): string => nl2br(e($state)))
                    ->html(),

                TextColumn::make('scheduled_at')
                    ->label('الموعد')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('—'),

                TextColumn::make('user.name')
                    ->label('بواسطة')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('إضافة متابعة')
                    ->modalHeading('إضافة متابعة'),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->visible(fn (LeadActivity $record): bool => $record->type !== 'stage_change'
                        && ($record->user_id === auth()->id() || auth()->user()?->isAdmin())),
            ]);
    }
}
