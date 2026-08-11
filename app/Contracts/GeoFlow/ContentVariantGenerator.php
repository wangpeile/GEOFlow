<?php

namespace App\Contracts\GeoFlow;

use App\Models\Article;

interface ContentVariantGenerator
{
    /**
     * @param  array<string, mixed>  $platformRules
     * @return array{title: string, excerpt: string, content: string, tags: array<int, string>, image_requirements: array<int, string>, model?: string, source?: string}
     */
    public function generate(Article $article, string $platform, array $platformRules): array;
}
