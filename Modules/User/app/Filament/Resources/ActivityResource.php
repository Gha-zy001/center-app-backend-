<?php

namespace Modules\User\app\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\ViewAction;
use Modules\User\app\Filament\Resources\ActivityResource\Pages\ListActivities;
use Modules\User\app\Filament\Resources\ActivityResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\KeyValueEntry;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    public static function getNavigationLabel(): string { return __('admin.activity_log'); }
    public static function getModelLabel(): string { return __('admin.activity'); }
    public static function getPluralModelLabel(): string { return __('admin.activity_log'); }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.activity'))
                    ->schema([
                        TextEntry::make('description')
                            ->label(__('admin.keys.action'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => __("admin.status.{$state}"))
                            ->color(fn (string $state): string => match ($state) {
                                'created' => 'success',
                                'updated' => 'warning',
                                'deleted' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('causer.name')
                            ->label(__('admin.keys.user')),
                        TextEntry::make('subject_type')
                            ->label(__('admin.keys.module'))
                            ->formatStateUsing(fn ($state) => str($state)->afterLast('\\')),
                        TextEntry::make('created_at')
                            ->label(__('admin.keys.created_at'))
                            ->dateTime(),
                    ])->columns(2),

                Section::make(__('admin.keys.details'))
                    ->schema([
                        KeyValueEntry::make('properties.attributes')
                            ->label(__('admin.status.updated')),
                        KeyValueEntry::make('properties.old')
                            ->label(__('admin.status.rejected')),
                    ])->visible(fn ($record) => $record->description === 'updated'),

                Section::make(__('admin.keys.details'))
                    ->schema([
                        KeyValueEntry::make('properties.attributes')
                            ->label(__('admin.keys.details')),
                    ])->visible(fn ($record) => $record->description !== 'updated'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label(__('admin.keys.created_at'))->dateTime()->sortable(),
                TextColumn::make('causer.name')->label(__('admin.keys.user'))->searchable(),
                TextColumn::make('description')
                    ->label(__('admin.keys.action'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("admin.status.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('subject_type')
                    ->label(__('admin.keys.module'))
                    ->formatStateUsing(fn ($state) => str($state)->afterLast('\\')),
                TextColumn::make('subject_id')->label(__('admin.keys.id')),
            ])
            ->filters([
                SelectFilter::make('subject_type')
                    ->label(__('admin.filters.module'))
                    ->options(fn () => Activity::groupBy('subject_type')->pluck('subject_type', 'subject_type')
                        ->mapWithKeys(fn ($item) => [$item => str($item)->afterLast('\\')])->toArray()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label(__('admin.actions.view')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }
}