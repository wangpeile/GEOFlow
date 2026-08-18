<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssistArticleEditorRequest;
use App\Models\Article;
use App\Services\GeoFlow\ContentEditorAssistantService;
use Illuminate\Http\JsonResponse;

class ArticleEditorAssistantController extends Controller
{
    public function __invoke(
        AssistArticleEditorRequest $request,
        int $articleId,
        ContentEditorAssistantService $service,
    ): JsonResponse {
        $article = Article::query()->whereKey($articleId)->firstOrFail();
        $result = $service->assist($request->user('admin'), $article, $request->validated());

        return response()->json(['ok' => true, 'data' => $result]);
    }
}
