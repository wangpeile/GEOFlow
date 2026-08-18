<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\GeoFlow\ContentOperationsMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentMetricsController extends BaseApiController
{
    public function index(Request $request, ContentOperationsMetricsService $metrics): JsonResponse
    {
        $validated = validator($request->query(), [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ])->validate();

        return $this->success($request, $metrics->summarize($validated['from'] ?? null, $validated['to'] ?? null));
    }
}
