<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $service = Service::withoutGlobalScopes()->inRandomOrder()->first();
        $start = Carbon::now()->addDays(rand(0, 14))->setTime(rand(9, 15), fake()->randomElement([0, 30]), 0);
        $end = $start->copy()->addMinutes($service?->duration_minutes ?? 30);

        return [
            'service_id' => $service?->id,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'slot_start' => $start,
            'slot_end' => $end,
        ];
    }
}
