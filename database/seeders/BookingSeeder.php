<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $services = Service::withoutGlobalScopes()->get();

        foreach ($services as $service) {
            $interval = $service->duration_minutes + $service->cleanup_minutes;

            for ($i = 0; $i < 4; $i++) {
                $date = Carbon::today()->addDays($i);
                $dayOfWeek = $date->dayOfWeek;

                // Skip Sunday (no opening hours seeded)
                if ($dayOfWeek === 0) {
                    continue;
                }

                $hour = ($dayOfWeek === 6) ? 10 : 9;
                $slotStart = $date->copy()->setTime($hour, 0);
                $slotEnd = $slotStart->copy()->addMinutes($service->duration_minutes);

                Booking::create([
                    'service_id' => $service->id,
                    'name' => fake()->name(),
                    'email' => fake()->safeEmail(),
                    'slot_start' => $slotStart,
                    'slot_end' => $slotEnd,
                ]);
            }
        }
    }
}
