<?php

namespace App\Support\GeoFlow\ContentProduction;

use App\Enums\ContentProductionStage;

final readonly class ContentStageDefinition
{
    /**
     * @param  list<string>  $requiredInputKeys
     * @param  list<string>  $outputKeys
     */
    public function __construct(
        public ContentProductionStage $stage,
        public array $requiredInputKeys,
        public array $outputKeys,
        public bool $confirmationRequiredInGuidedMode = false,
        public int $contractVersion = 1,
    ) {}
}
