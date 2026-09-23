<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class Phase5Seeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            SearchBenchmarkSeeder::class,
            ReportBenchmarkSeeder::class,
        ]);
    }
}
