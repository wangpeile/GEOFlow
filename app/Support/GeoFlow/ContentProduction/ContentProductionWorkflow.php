<?php

namespace App\Support\GeoFlow\ContentProduction;

use App\Enums\ContentProductionStage;
use InvalidArgumentException;

final class ContentProductionWorkflow
{
    /**
     * @return list<ContentStageDefinition>
     */
    public function definitions(): array
    {
        return [
            new ContentStageDefinition(ContentProductionStage::Initialize, ['topic', 'language'], ['production_context']),
            new ContentStageDefinition(ContentProductionStage::DuplicateCheck, ['production_context'], ['duplicate_report']),
            new ContentStageDefinition(ContentProductionStage::Research, ['production_context'], ['evidence']),
            new ContentStageDefinition(ContentProductionStage::Brief, ['production_context', 'evidence'], ['content_brief'], true),
            new ContentStageDefinition(ContentProductionStage::Title, ['content_brief'], ['title_candidates', 'selected_title'], true),
            new ContentStageDefinition(ContentProductionStage::Outline, ['content_brief', 'selected_title'], ['outline_candidates', 'selected_outline'], true),
            new ContentStageDefinition(ContentProductionStage::SectionWriting, ['content_brief', 'selected_outline', 'evidence'], ['article_sections']),
            new ContentStageDefinition(ContentProductionStage::Assembly, ['selected_title', 'article_sections'], ['article_candidate']),
            new ContentStageDefinition(ContentProductionStage::QualityGate, ['article_candidate', 'evidence'], ['quality_report']),
            new ContentStageDefinition(ContentProductionStage::TargetedRepair, ['article_candidate', 'quality_report'], ['article_candidate', 'repair_report']),
            new ContentStageDefinition(ContentProductionStage::Review, ['article_candidate'], ['review_decision'], true),
            new ContentStageDefinition(ContentProductionStage::WordPressPublish, ['article_candidate', 'review_decision'], ['publication_result']),
            new ContentStageDefinition(ContentProductionStage::PlatformRewrite, ['article_candidate'], ['platform_variants']),
        ];
    }

    public function definition(ContentProductionStage $stage): ContentStageDefinition
    {
        foreach ($this->definitions() as $definition) {
            if ($definition->stage === $stage) {
                return $definition;
            }
        }

        throw new InvalidArgumentException("Unknown content production stage [{$stage->value}].");
    }

    public function next(ContentProductionStage $stage): ?ContentProductionStage
    {
        $stages = array_map(
            static fn (ContentStageDefinition $definition): ContentProductionStage => $definition->stage,
            $this->definitions(),
        );
        $position = array_search($stage, $stages, true);

        if ($position === false || $position === array_key_last($stages)) {
            return null;
        }

        return $stages[$position + 1];
    }
}
