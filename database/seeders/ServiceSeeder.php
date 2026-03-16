<?php

namespace Database\Seeders;

use App\Models\BreakTime;
use App\Models\OpeningHour;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'name' => 'Haircut',
                'description' => 'Professional haircut and styling session.',
                'duration_minutes' => 30,
                'price' => 35.00,
                'cleanup_minutes' => 10,
                'max_capacity' => 2,
            ],
            [
                'name' => 'Deep Tissue Massage',
                'description' => 'Therapeutic deep tissue massage for muscle relief.',
                'duration_minutes' => 60,
                'price' => 90.00,
                'cleanup_minutes' => 15,
                'max_capacity' => 1,
            ],
            [
                'name' => 'Business Consultation',
                'description' => 'One-on-one business strategy consultation.',
                'duration_minutes' => 45,
                'price' => 150.00,
                'cleanup_minutes' => 5,
                'max_capacity' => 1,
            ],
        ];

        foreach ($services as $data) {
            $service = Service::withoutGlobalScopes()->create($data);
            $this->seedOpeningHours($service);
            $this->seedBreakTimes($service);
        }
    }

    private function seedOpeningHours(Service $service): void
    {
        // Monday (1) through Friday (5), 09:00 – 17:00
        foreach (range(1, 5) as $day) {
            OpeningHour::create([
                'service_id' => $service->id,
                'day_of_week' => $day,
                'start_time' => '09:00',
                'end_time' => '17:00',
            ]);
        }

        // Saturday (6), 10:00 – 14:00
        OpeningHour::create([
            'service_id' => $service->id,
            'day_of_week' => 6,
            'start_time' => '10:00',
            'end_time' => '14:00',
        ]);
    }

    private function seedBreakTimes(Service $service): void
    {
        // Lunch break Mon-Fri
        foreach (range(1, 5) as $day) {
            BreakTime::create([
                'service_id' => $service->id,
                'day_of_week' => $day,
                'start_time' => '12:00',
                'end_time' => '13:00',
            ]);
        }
    }
}
