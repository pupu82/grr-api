<?php

namespace Database\Seeders;

use App\Models\PartType;
use Illuminate\Database\Seeder;

class PartTypeSeeder extends Seeder
{
    /**
     * Seed the initial part types. Admins can add more from the app.
     */
    public function run(): void
    {
        foreach (['Part Type A', 'Part Type B', 'Part Type C', 'Part Type D'] as $name) {
            PartType::firstOrCreate(['name' => $name]);
        }
    }
}
