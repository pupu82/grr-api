<?php

namespace Database\Seeders;

use App\Models\Plant;
use Illuminate\Database\Seeder;

class PlantSeeder extends Seeder
{
    /**
     * Seed the initial plants (buildings). Admins can add more from the app.
     */
    public function run(): void
    {
        $plants = [
            ['code' => 'BCP', 'name' => 'BCP'],
            ['code' => 'UCP', 'name' => 'UCP'],
            ['code' => 'PLANT-A', 'name' => 'Plant A'],
            ['code' => 'PLANT-B', 'name' => 'Plant B'],
            ['code' => 'PLANT-C', 'name' => 'Plant C'],
            ['code' => 'PLANT-D', 'name' => 'Plant D'],
            ['code' => 'PLANT-E', 'name' => 'Plant E'],
            ['code' => 'PLANT-F', 'name' => 'Plant F'],
            ['code' => 'PLANT-G', 'name' => 'Plant G'],
            ['code' => 'WAREHOUSE-A', 'name' => 'Warehouse A'],
            ['code' => 'WAREHOUSE-B', 'name' => 'Warehouse B'],
            ['code' => 'WAREHOUSE-C', 'name' => 'Warehouse C'],
            ['code' => 'WAREHOUSE-D', 'name' => 'Warehouse D'],
            ['code' => 'WAREHOUSE-E', 'name' => 'Warehouse E'],
            ['code' => 'WAREHOUSE-F', 'name' => 'Warehouse F'],
            ['code' => 'WAREHOUSE-G', 'name' => 'Warehouse G'],
        ];

        foreach ($plants as $plant) {
            Plant::firstOrCreate(['code' => $plant['code']], $plant);
        }
    }
}
