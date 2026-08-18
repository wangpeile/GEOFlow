<?php

namespace App\Contracts\GeoFlow;

use App\Models\Article;

interface ContentEditorAssistant
{
    /** @return array{text:string,model:string,source:string} */
    public function assist(Article $article, string $action, string $selection, ?string $instruction = null): array;
}
