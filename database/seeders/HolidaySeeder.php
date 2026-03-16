<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            ['date' => Carbon::now()->startOfYear(), 'name' => 'New Year\'s Day'],
            ['date' => Carbon::parse('2025-12-25'), 'name' => 'Christmas Day'],
            ['date' => Carbon::parse('2025-11-27'), 'name' => 'Thanksgiving'],
        ];

        foreach ($holidays as $h) {
            Holiday::firstOrCreate(
                ['date' => $h['date']],
                ['name' => $h['name']],
            );
        }
    }
}

