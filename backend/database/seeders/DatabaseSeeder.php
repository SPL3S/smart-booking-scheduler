<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;
use App\Models\WorkingHour;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Services
        Service::create([
            'name' => 'Haircut',
            'duration_minutes' => 30,
            'price' => 25.00
        ]);

        Service::create([
            'name' => 'Hair Coloring',
            'duration_minutes' => 90,
            'price' => 75.00
        ]);

        Service::create([
            'name' => 'Beard Trim',
            'duration_minutes' => 15,
            'price' => 15.00
        ]);

        // Create Working Hours
        $workingDays = [
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00'], // Monday
            ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '17:00'], 
            ['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '17:00'], 
            ['day_of_week' => 4, 'start_time' => '09:00', 'end_time' => '17:00'], 
            ['day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '17:00'], // Friday
        ];

        foreach ($workingDays as $day) {
            WorkingHour::create($day);
        }
    }
}