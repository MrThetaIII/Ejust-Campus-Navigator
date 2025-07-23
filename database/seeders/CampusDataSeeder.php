<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Marker;
use App\Models\Road;
use App\Models\CampusBoundary;

class CampusDataSeeder extends Seeder
{
    public function run()
    {
        // Sample campus boundary (adjust to your campus)
        CampusBoundary::create([
            'north' => 51.5100,
            'south' => 51.5000,
            'east' => -0.0800,
            'west' => -0.1000,
        ]);

        // Sample markers
        $markers = [
            ['name' => 'Library', 'description' => 'Main campus library', 'latitude' => 51.5050, 'longitude' => -0.0900],
            ['name' => 'Student Union', 'description' => 'Student activities center', 'latitude' => 51.5060, 'longitude' => -0.0890],
            ['name' => 'Science Building', 'description' => 'Science and Engineering', 'latitude' => 51.5040, 'longitude' => -0.0910],
        ];

        foreach ($markers as $marker) {
            Marker::create($marker);
        }

        // Sample road
        Road::create([
            'name' => 'Main Campus Path',
            'path' => [
                [51.5050, -0.0900],
                [51.5055, -0.0895],
                [51.5060, -0.0890],
            ],
            'width' => 5,
            'color' => '#0066cc',
        ]);
    }
}
