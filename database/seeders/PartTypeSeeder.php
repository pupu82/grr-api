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
        foreach (['Upper', 'Bottom', 'Bottom Stockfit', 'Sepatu'] as $name) {
            PartType::firstOrCreate(['name' => $name]);
        }
    }
}
