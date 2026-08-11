<?php

namespace Database\Factories;

use App\Enums\ContentEvidenceSourceType;
use App\Enums\ContentEvidenceUsage;
use App\Models\ContentEvidence;
use App\Models\ContentProduction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContentEvidence>
 */
class ContentEvidenceFactory extends Factory
{
    protected $model = ContentEvidence::class;

    public function definition(): array
    {
        $content = fake()->paragraphs(3, true);

        return [
            'content_production_id' => ContentProduction::factory(),
            'source_type' => ContentEvidenceSourceType::Manual,
            'usage' => ContentEvidenceUsage::ReferenceOnly,
            'source_key' => 'manual:'.Str::uuid(),
            'source_title' => fake()->sentence(),
            'content_snapshot' => $content,
            'excerpt' => Str::limit($content, 500, ''),
            'collected_at' => now(),
        ];
    }
}
