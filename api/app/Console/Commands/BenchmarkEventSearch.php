<?php

namespace App\Console\Commands;

use App\Contracts\EventSearch;
use Illuminate\Console\Command;

class BenchmarkEventSearch extends Command
{
    protected $signature = 'search:benchmark
                            {--runs=5 : Number of measured runs}
                            {--name=Search Seed Event : Name prefix}
                            {--location=Campo : Location prefix}';

    protected $description = 'Benchmark the indexed event search locally';

    public function handle(EventSearch $search): int
    {
        $runs = max(1, (int) $this->option('runs'));
        $filters = [
            'name' => (string) $this->option('name'),
            'location' => (string) $this->option('location'),
        ];

        $times = [];
        $total = 0;

        for ($i = 0; $i < $runs; $i++) {
            $started = hrtime(true);
            $results = $search->search($filters, 50);
            $times[] = round((hrtime(true) - $started) / 1_000_000, 2);
            $total = $results->total();
        }

        $this->table(
            ['name_prefix', 'location_prefix', 'matches', 'runs', 'min_ms', 'avg_ms', 'max_ms'],
            [[
                $filters['name'],
                $filters['location'],
                $total,
                $runs,
                min($times),
                round(array_sum($times) / count($times), 2),
                max($times),
            ]]
        );

        return self::SUCCESS;
    }
}
