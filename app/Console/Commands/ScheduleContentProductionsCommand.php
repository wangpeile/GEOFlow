<?php

namespace App\Console\Commands;

use App\Services\GeoFlow\ContentProductionScheduleService;
use Illuminate\Console\Command;

class ScheduleContentProductionsCommand extends Command
{
    protected $signature = 'content:schedule-productions';

    protected $description = 'Create due standard content production occurrences';

    public function handle(ContentProductionScheduleService $scheduler): int
    {
        $result = $scheduler->dispatchDue();
        $this->info("Content scheduler done: queued={$result['queued']}, skipped={$result['skipped']}");

        return self::SUCCESS;
    }
}
