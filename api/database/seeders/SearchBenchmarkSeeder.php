<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SearchBenchmarkSeeder extends Seeder
{
    public function run(): void
    {
        $organizer = User::query()->firstOrCreate(
            ['email' => 'benchmark-organizer@atlas.test'],
            ['name' => 'Benchmark Organizer', 'role' => UserRole::ORGANIZER->value, 'password' => Hash::make('Password123!')]
        );

        DB::table('events')->where('name', 'like', 'Search Seed Event %')->delete();

        $locations = ['Campo Grande - MS', 'Sao Paulo - SP', 'Recife - PE', 'Curitiba - PR', 'Rio de Janeiro - RJ'];
        $now = now();

        foreach (array_chunk(range(1, 10000), 1000) as $chunk) {
            $rows = [];
            foreach ($chunk as $number) {
                $starts = $now->copy()->addDays(($number % 365) + 1)->setTime(20, 0);
                $rows[] = [
                    'organizer_id' => $organizer->id,
                    'name' => sprintf('Search Seed Event %05d', $number),
                    'location' => $locations[$number % count($locations)],
                    'starts_at' => $starts,
                    'sales_start_at' => $now->copy()->subDay(),
                    'sales_end_at' => $starts->copy()->subHour(),
                    'status' => EventStatus::PUBLISHED->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('events')->insert($rows);
        }

        $this->command?->info('Seeded 10,000 searchable events.');
    }
}
