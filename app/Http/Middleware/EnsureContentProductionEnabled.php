<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 内容生产流水线处于关闭状态时，统一隐藏相关页面与操作端点。
 */
class EnsureContentProductionEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('geoflow.content_production_pipeline_enabled', false), 404);

        return $next($request);
    }
}
