<?php

namespace App\Filament\Resources\OpeningHourResource\Pages;

use App\Filament\Resources\OpeningHourResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOpeningHours extends ListRecords
{
    protected static string $resource = OpeningHourResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
