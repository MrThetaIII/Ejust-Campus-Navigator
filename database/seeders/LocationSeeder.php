<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Location;

class LocationSeeder extends Seeder
{
    public function run()
    {
        $locations = [
            [
                'code' => 'main-campus',
                'name' => 'Main Campus',
                'description' => 'Primary campus with academic buildings, library, and administration',
                'active' => true
            ],
            [
                'code' => 'northern-dorms',
                'name' => 'Northern Dormitories',
                'description' => 'Northern residential area with student dormitories and facilities',
                'active' => true
            ],
            [
                'code' => 'southern-dorms',
                'name' => 'Southern Dormitories',
                'description' => 'Southern residential area with student housing',
                'active' => true
            ],
            [
                'code' => 'western-dorms',
                'name' => 'Western Dormitories',
                'description' => 'Western residential complex with dining facilities',
                'active' => true
            ]
        ];

        foreach ($locations as $location) {
            Location::updateOrCreate(
                ['code' => $location['code']],
                $location
            );
        }
    }
}
