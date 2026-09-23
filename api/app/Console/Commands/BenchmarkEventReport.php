<?php

namespace App\Console\Commands;

use App\Application\Reports\EventReportService;
use App\Models\Event;
use Illuminate\Console\Command;

class BenchmarkEventReport extends Command
{
    protected $signature = 'reports:benchmark {event} {--runs=5}';

    protected $description = 'Benchmark the event report query locally';

    public function handle(EventReportService $reports): int
    {
        $event = Event::query()->findOrFail((int) $this->argument('event'));
        $runs = max(1, (int) $this->option('runs'));
        $times = [];

        for ($i = 0; $i < $runs; $i++) {
            $result = $reports->generate($event);
            $times[] = $result['query_time_ms'];
        }

        $this->table(
            ['event_id', 'tickets', 'runs', 'min_ms', 'avg_ms', 'max_ms'],
            [[
                $event->id,
                $reports->generate($event)['tickets']['total'],
                $runs,
                min($times),
                round(array_sum($times) / count($times), 2),
                max($times),
            ]]
        );

        return self::SUCCESS;
    }
}
