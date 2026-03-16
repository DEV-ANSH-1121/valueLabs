<?php

namespace App\Filament\Resources\OpeningHourResource\Pages;

use App\Filament\Resources\OpeningHourResource;
use App\Models\OpeningHour;
use Filament\Forms;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CreateOpeningHour extends CreateRecord
{
    protected static string $resource = OpeningHourResource::class;

    public function form(Schema $form): Schema
    {
        $days = [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];

        $components = [
            Forms\Components\Select::make('service_id')
                ->label('Service')
                ->options(\App\Models\Service::withoutGlobalScopes()->pluck('name', 'id'))
                ->required()
                ->searchable(),
        ];

        foreach ($days as $day => $label) {
            $components[] = Section::make($label)
                ->schema([
                    Forms\Components\TimePicker::make("days.$day.start")
                        ->label('Start')
                        ->seconds(false),
                    Forms\Components\TimePicker::make("days.$day.end")
                        ->label('End')
                        ->seconds(false),
                ])
                ->columns(2);
        }

        return $form->components($components);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $serviceId = $data['service_id'];
        $daysData = $data['days'] ?? [];

        $created = null;

        foreach ($daysData as $day => $times) {
            $start = $times['start'] ?? null;
            $end = $times['end'] ?? null;

            // If both times are empty, remove any existing opening hours for that day.
            if (! $start && ! $end) {
                OpeningHour::where('service_id', $serviceId)
                    ->where('day_of_week', $day)
                    ->delete();

                continue;
            }

            // Skip partially filled rows.
            if (! $start || ! $end) {
                continue;
            }

            // Ensure start is before end.
            if ($start >= $end) {
                continue;
            }

            $created = OpeningHour::updateOrCreate(
                [
                    'service_id' => $serviceId,
                    'day_of_week' => $day,
                ],
                [
                    'start_time' => $start,
                    'end_time' => $end,
                ],
            );
        }

        // Return a dummy model so Filament can redirect back to index.
        return $created ?? new OpeningHour();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
