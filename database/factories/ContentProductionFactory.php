<?php

namespace Database\Factories;

use App\Enums\ContentProductionMode;
use App\Enums\ContentProductionStage;
use App\Enums\ContentProductionStatus;
use App\Models\ContentProduction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContentProduction>
 */
class ContentProductionFactory extends Factory
{
    protected $model = ContentProduction::class;

    public function definition(): array
    {
        $topic = fake()->sentence();

        return [
            'uuid' => (string) Str::uuid(),
            'name' => mb_substr($topic, 0, 255),
            'topic' => $topic,
            'mode' => ContentProductionMode::Guided,
            'status' => ContentProductionStatus::Draft,
            'current_stage' => ContentProductionStage::Initialize,
            'language' => 'zh_CN',
            'target_platforms' => ['wordpress'],
            'context' => ['topic' => $topic, 'language' => 'zh_CN'],
        ];
    }
}
