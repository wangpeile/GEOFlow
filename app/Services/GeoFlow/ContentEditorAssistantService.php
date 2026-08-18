<?php

namespace App\Services\GeoFlow;

use App\Contracts\GeoFlow\ContentEditorAssistant;
use App\Models\Admin;
use App\Models\Article;
use App\Models\ContentEditorAssist;

final class ContentEditorAssistantService
{
    public function __construct(private readonly ContentEditorAssistant $assistant) {}

    /** @return array{text:string,model:string,source:string,assist_id:int} */
    public function assist(Admin $admin, Article $article, array $input): array
    {
        $result = $this->assistant->assist(
            $article,
            $input['action'],
            $input['selection'],
            $input['instruction'] ?? null,
        );
        $record = ContentEditorAssist::query()->create([
            'article_id' => $article->getKey(),
            'created_by_admin_id' => $admin->getKey(),
            'action' => $input['action'],
            'selection_hash' => hash('sha256', $input['selection']),
            'source_text' => $input['selection'],
            'instruction' => $input['instruction'] ?? null,
            'result_text' => $result['text'],
            'model' => $result['model'],
            'metadata' => ['source' => $result['source']],
        ]);

        return [...$result, 'assist_id' => (int) $record->getKey()];
    }
}
