<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OpeningHourResource\Pages;
use App\Models\Service;
use App\Models\OpeningHour;
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
use Illuminate\Validation\Rule;
use UnitEnum;

class OpeningHourResource extends Resource
{
    protected static ?string $model = OpeningHour::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|UnitEnum|null $navigationGroup = 'Scheduling';

    protected static ?int $navigationSort = 2;

    private const DAY_OPTIONS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    // The resource's form is overridden at the page level (CreateOpeningHour)
    // to present a 7-day schedule per service.

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
            ->defaultSort('day_of_week')
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
            'index' => Pages\ListOpeningHours::route('/'),
            'create' => Pages\CreateOpeningHour::route('/create'),
            'edit' => Pages\EditOpeningHour::route('/{record}/edit'),
        ];
    }
}
