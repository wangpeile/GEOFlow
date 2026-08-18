<?php

namespace App\Jobs;

use App\Services\GeoFlow\ContentWordPressPublicationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWordPressContentPublicationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(private readonly int $distributionId) {}

    public function handle(ContentWordPressPublicationService $service): void
    {
        $service->process($this->distributionId);
    }
}
