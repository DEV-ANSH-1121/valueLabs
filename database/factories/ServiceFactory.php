<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Haircut', 'Deep Tissue Massage', 'Business Consultation', 'Facial Treatment', 'Personal Training']),
            'description' => fake()->sentence(10),
            'duration_minutes' => fake()->randomElement([30, 45, 60]),
            'price' => fake()->randomFloat(2, 20, 200),
            'cleanup_minutes' => fake()->randomElement([0, 5, 10, 15]),
            'max_capacity' => fake()->numberBetween(1, 3),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
