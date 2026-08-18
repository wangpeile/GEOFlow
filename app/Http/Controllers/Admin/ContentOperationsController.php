<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ViewContentMetricsRequest;
use App\Services\GeoFlow\ContentOperationsMetricsService;
use App\Support\AdminWeb;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ContentOperationsController extends Controller
{
    public function index(ViewContentMetricsRequest $request, ContentOperationsMetricsService $metrics): View
    {
        $input = $request->validated();

        return view('admin.content-productions.operations', [
            'pageTitle' => '内容生产运营看板',
            'activeMenu' => 'articles',
            'adminSiteName' => AdminWeb::siteName(),
            'metrics' => $metrics->summarize($input['from'] ?? null, $input['to'] ?? null),
        ]);
    }

    public function json(ViewContentMetricsRequest $request, ContentOperationsMetricsService $metrics): JsonResponse
    {
        $input = $request->validated();

        return response()->json(['ok' => true, 'data' => $metrics->summarize($input['from'] ?? null, $input['to'] ?? null)]);
    }
}
