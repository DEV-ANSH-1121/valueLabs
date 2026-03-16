<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BreakTimeResource\Pages;
use App\Models\BreakTime;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class BreakTimeResource extends Resource
{
    protected static ?string $model = BreakTime::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-pause-circle';

    protected static string|UnitEnum|null $navigationGroup = 'Scheduling';

    protected static ?int $navigationSort = 3;

    private const DAY_OPTIONS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public static function form(Schema $form): Schema
    {
        return $form->components([
            Forms\Components\Select::make('service_id')
                ->label('Service')
                ->options(Service::withoutGlobalScopes()->pluck('name', 'id'))
                ->required()
                ->searchable(),
            Forms\Components\Select::make('day_of_week')
                ->options(self::DAY_OPTIONS)
                ->required(),
            Forms\Components\TimePicker::make('start_time')
                ->required()
                ->seconds(false),
            Forms\Components\TimePicker::make('end_time')
                ->required()
                ->seconds(false)
                ->after('start_time'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('service.name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('day_of_week')
                    ->formatStateUsing(fn (int $state) => self::DAY_OPTIONS[$state] ?? $state)
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time'),
                Tables\Columns\TextColumn::make('end_time'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('service_id')
                    ->label('Service')
                    ->relationship('service', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBreakTimes::route('/'),
            'create' => Pages\CreateBreakTime::route('/create'),
            'edit' => Pages\EditBreakTime::route('/{record}/edit'),
        ];
    }
}
