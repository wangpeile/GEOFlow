<?php

namespace App\Contracts\GeoFlow;

use App\Models\ContentProduction;
use App\Models\ContentSectionVersion;

interface ContentSectionGenerator
{
    /**
     * @return array{content:string, model:?string, source:string}
     */
    public function generate(ContentProduction $production, ContentSectionVersion $section): array;
}
