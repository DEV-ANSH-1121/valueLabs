<?php

namespace App\Filament\Resources\OpeningHourResource\Pages;

use App\Filament\Resources\OpeningHourResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOpeningHour extends EditRecord
{
    protected static string $resource = OpeningHourResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
