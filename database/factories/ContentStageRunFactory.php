<?php

namespace Database\Factories;

use App\Enums\ContentProductionStage;
use App\Enums\ContentStageStatus;
use App\Models\ContentProduction;
use App\Models\ContentStageRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentStageRun>
 */
class ContentStageRunFactory extends Factory
{
    protected $model = ContentStageRun::class;

    public function definition(): array
    {
        return [
            'content_production_id' => ContentProduction::factory(),
            'stage' => ContentProductionStage::Initialize,
            'status' => ContentStageStatus::Pending,
            'sequence' => 1,
            'attempt' => 1,
            'contract_version' => 1,
        ];
    }
}
