<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminWeb;
use Illuminate\View\View;

class ContentAssetController extends Controller
{
    public function index(): View
    {
        abort_unless((bool) config('geoflow.content_production_pipeline_enabled', false), 404);

        return view('admin.content-assets.index', [
            'pageTitle' => '内容资产',
            'activeMenu' => 'content_assets',
            'adminSiteName' => AdminWeb::siteName(),
        ]);
    }
}
