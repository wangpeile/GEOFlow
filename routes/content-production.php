<?php

/**
 * GEOFlow 内容生产扩展路由。
 *
 * 保持为独立文件，避免覆盖官方 v3 的后台、AI 工作台与文章质量路由。
 */

use App\Http\Controllers\Admin\ArticleEditorAssistantController;
use App\Http\Controllers\Admin\ArticleTypeController;
use App\Http\Controllers\Admin\ContentArticleController;
use App\Http\Controllers\Admin\ContentAssetController;
use App\Http\Controllers\Admin\ContentCenterController;
use App\Http\Controllers\Admin\ContentDirectionController;
use App\Http\Controllers\Admin\ContentEvidenceController;
use App\Http\Controllers\Admin\ContentGroupController;
use App\Http\Controllers\Admin\ContentOperationsController;
use App\Http\Controllers\Admin\ContentPlatformSpecificationController;
use App\Http\Controllers\Admin\ContentProductionController;
use App\Http\Controllers\Admin\ContentResearchController;
use App\Http\Controllers\Admin\ContentTopicController;
use App\Http\Controllers\Admin\ContentVariantController;
use App\Http\Controllers\Admin\TaskAutomationController;
use App\Http\Controllers\Admin\WordPressContentPublicationController;
use App\Http\Controllers\Admin\WritingRuleController;
use Illuminate\Support\Facades\Route;

$adminPrefix = trim((string) config('geoflow.admin_base_path', '/geo_admin'), '/');

Route::prefix($adminPrefix)->name('admin.')->middleware(['admin.locale'])->group(function () use ($adminPrefix): void {
    Route::middleware(['admin.auth', 'admin.activity', 'admin.recent'])->group(function (): void {
        Route::get('content-assets', [ContentAssetController::class, 'index'])
            ->middleware(['admin.super', 'content.production.enabled'])->name('content-assets.index');
        Route::get('content-center', [ContentCenterController::class, 'index'])
            ->middleware(['admin.super', 'content.production.enabled'])->name('content-center.index');

        Route::prefix('articles')->name('articles.')->group(function (): void {
            Route::post('{articleId}/editor/assist', ArticleEditorAssistantController::class)
                ->middleware(['admin.super', 'content.production.enabled', 'throttle:admin-sensitive'])
                ->name('editor.assist')->whereNumber('articleId');
        });

        Route::prefix('content-groups')->name('content-groups.')
            ->middleware(['admin.super', 'content.production.enabled'])
            ->group(function (): void {
                Route::get('/', [ContentGroupController::class, 'index'])->name('index');
                Route::post('articles/{article}', [ContentGroupController::class, 'store'])->name('store');
                Route::get('{contentGroup}', [ContentGroupController::class, 'show'])->name('show');
                Route::post('{contentGroup}/variants/generate', [ContentGroupController::class, 'generate'])->name('variants.generate');
                Route::post('{contentGroup}/variants/export', [ContentVariantController::class, 'export'])->name('variants.export');
                Route::get('{contentGroup}/variants/{contentVariant}/preview', [ContentVariantController::class, 'preview'])->name('variants.preview');
                Route::post('{contentGroup}/variants/{contentVariant}/feedback', [ContentVariantController::class, 'feedback'])->name('variants.feedback');
                Route::get('{contentGroup}/variants/{contentVariant}/edit', [ContentVariantController::class, 'edit'])->name('variants.edit');
                Route::put('{contentGroup}/variants/{contentVariant}', [ContentVariantController::class, 'update'])->name('variants.update');
                Route::post('{contentGroup}/variants/{contentVariant}/review', [ContentVariantController::class, 'review'])->name('variants.review');
                Route::post('{contentGroup}/variants/{contentVariant}/regenerate', [ContentGroupController::class, 'regenerate'])->name('variants.regenerate');
                Route::post('{contentGroup}/variants/{contentVariant}/wordpress-publications', [WordPressContentPublicationController::class, 'store'])->name('variants.wordpress.publish');
                Route::post('{contentGroup}/variants/{contentVariant}/wordpress-publications/{articleDistribution}/retry', [WordPressContentPublicationController::class, 'retry'])->name('variants.wordpress.retry');
            });

        Route::get('distribution-center', [ContentGroupController::class, 'index'])
            ->middleware(['admin.super', 'content.production.enabled'])->name('distribution-center.index');

        Route::prefix('content-platform-specifications')->name('content-platform-specifications.')
            ->middleware(['admin.super', 'content.production.enabled'])->group(function (): void {
                Route::get('/', [ContentPlatformSpecificationController::class, 'index'])->name('index');
                Route::get('{platform}/edit', [ContentPlatformSpecificationController::class, 'edit'])->name('edit');
                Route::put('{platform}', [ContentPlatformSpecificationController::class, 'update'])->name('update');
            });

        Route::middleware(['admin.super', 'content.production.enabled'])->group(function (): void {
            Route::prefix('writing-rules')->name('writing-rules.')->group(function (): void {
                Route::get('/', [WritingRuleController::class, 'index'])->name('index');
                Route::get('create', [WritingRuleController::class, 'create'])->name('create');
                Route::post('/', [WritingRuleController::class, 'store'])->name('store');
                Route::post('install-presets', [WritingRuleController::class, 'installPresets'])->name('install-presets');
                Route::get('{writingRule}', [WritingRuleController::class, 'show'])->name('show');
                Route::get('{writingRule}/edit', [WritingRuleController::class, 'edit'])->name('edit');
                Route::put('{writingRule}', [WritingRuleController::class, 'update'])->name('update');
            });
            Route::prefix('article-types')->name('article-types.')->group(function (): void {
                Route::get('/', [ArticleTypeController::class, 'index'])->name('index');
                Route::get('create', [ArticleTypeController::class, 'create'])->name('create');
                Route::post('/', [ArticleTypeController::class, 'store'])->name('store');
                Route::get('{articleType}/edit', [ArticleTypeController::class, 'edit'])->name('edit');
                Route::put('{articleType}', [ArticleTypeController::class, 'update'])->name('update');
            });
        });

        Route::prefix('content-productions')->name('content-productions.')
            ->middleware(['admin.super', 'content.production.enabled'])
            ->group(function (): void {
                Route::get('/', [ContentProductionController::class, 'index'])->name('index');
                Route::get('create', [ContentProductionController::class, 'create'])->name('create');
                Route::post('/', [ContentProductionController::class, 'store'])->name('store');
                Route::get('{contentProduction}', [ContentProductionController::class, 'show'])->name('show');
                Route::patch('{contentProduction}/ownership', [ContentProductionController::class, 'updateOwnership'])->name('ownership.update');
                Route::post('{contentProduction}/stages/{stageRun}/retry', [ContentProductionController::class, 'retry'])->middleware('admin.super')->name('stages.retry');
                Route::post('{contentProduction}/evidence/retrieve', [ContentEvidenceController::class, 'retrieve'])->middleware('admin.super')->name('evidence.retrieve');
                Route::post('{contentProduction}/evidence/url-import', [ContentEvidenceController::class, 'attachUrl'])->middleware('admin.super')->name('evidence.url-import');
                Route::post('{contentProduction}/evidence', [ContentEvidenceController::class, 'store'])->middleware('admin.super')->name('evidence.store');
                Route::post('{contentProduction}/research', [ContentResearchController::class, 'store'])->middleware('throttle:admin-sensitive')->name('research.store');
                Route::patch('{contentProduction}/evidence/{contentEvidence}', [ContentEvidenceController::class, 'update'])->middleware('admin.super')->name('evidence.update');
                Route::delete('{contentProduction}/evidence/{contentEvidence}', [ContentEvidenceController::class, 'destroy'])->middleware('admin.super')->name('evidence.destroy');
                Route::post('{contentProduction}/direction/brief/generate', [ContentDirectionController::class, 'generateBrief'])->middleware('admin.super')->name('direction.brief.generate');
                Route::post('{contentProduction}/direction/brief', [ContentDirectionController::class, 'saveBrief'])->middleware('admin.super')->name('direction.brief.save');
                Route::post('{contentProduction}/direction/titles/generate', [ContentDirectionController::class, 'generateTitles'])->middleware('admin.super')->name('direction.titles.generate');
                Route::post('{contentProduction}/direction/titles/select', [ContentDirectionController::class, 'selectTitle'])->middleware('admin.super')->name('direction.titles.select');
                Route::post('{contentProduction}/direction/outlines/generate', [ContentDirectionController::class, 'generateOutlines'])->middleware('admin.super')->name('direction.outlines.generate');
                Route::post('{contentProduction}/direction/outlines/update', [ContentDirectionController::class, 'updateOutline'])->middleware('admin.super')->name('direction.outlines.update');
                Route::post('{contentProduction}/direction/{kind}/confirm', [ContentDirectionController::class, 'confirm'])->middleware('admin.super')->whereIn('kind', ['brief', 'titles', 'outlines'])->name('direction.confirm');
                Route::post('{contentProduction}/article/sections/initialize', [ContentArticleController::class, 'initialize'])->middleware('admin.super')->name('article.sections.initialize');
                Route::post('{contentProduction}/article/sections/generate-all', [ContentArticleController::class, 'generateAll'])->middleware('admin.super')->name('article.sections.generate-all');
                Route::post('{contentProduction}/article/sections/{sectionKey}/generate', [ContentArticleController::class, 'generate'])->middleware('admin.super')->whereUuid('sectionKey')->name('article.sections.generate');
                Route::put('{contentProduction}/article/sections/{sectionKey}', [ContentArticleController::class, 'save'])->middleware('admin.super')->whereUuid('sectionKey')->name('article.sections.save');
                Route::post('{contentProduction}/article/assemble', [ContentArticleController::class, 'assemble'])->middleware('admin.super')->name('article.assemble');
                Route::post('{contentProduction}/article/promote', [ContentArticleController::class, 'promote'])->middleware('admin.super')->name('article.promote');
                Route::post('{contentProduction}/article/quality/inspect', [ContentArticleController::class, 'inspectQuality'])->middleware('admin.super')->name('article.quality.inspect');
                Route::post('{contentProduction}/article/quality/{qualityReport}/repair', [ContentArticleController::class, 'repairQuality'])->middleware('admin.super')->name('article.quality.repair');
                Route::post('{contentProduction}/article/revisions', [ContentArticleController::class, 'revise'])->middleware('admin.super')->name('article.revisions.store');
            });

        Route::get('content-projects', fn () => redirect()->route('admin.content-productions.index'))->name('content-projects.index');
        Route::prefix('content-topics')->name('content-topics.')
            ->middleware(['admin.super', 'content.production.enabled'])->group(function (): void {
                Route::get('/', [ContentTopicController::class, 'index'])->name('index');
                Route::get('create', [ContentTopicController::class, 'create'])->name('create');
                Route::post('/', [ContentTopicController::class, 'store'])->name('store');
                Route::get('{contentTopic}', [ContentTopicController::class, 'show'])->name('show');
                Route::get('{contentTopic}/edit', [ContentTopicController::class, 'edit'])->name('edit');
                Route::put('{contentTopic}', [ContentTopicController::class, 'update'])->name('update');
                Route::post('{contentTopic}/ideas', [ContentTopicController::class, 'storeIdea'])->name('ideas.store');
                Route::put('{contentTopic}/ideas/{contentTopicIdea}', [ContentTopicController::class, 'updateIdea'])->name('ideas.update');
            });
        Route::prefix('content-operations')->name('content-operations.')
            ->middleware(['admin.super', 'content.production.enabled'])->group(function (): void {
                Route::get('/', [ContentOperationsController::class, 'index'])->name('index');
                Route::get('metrics.json', [ContentOperationsController::class, 'json'])->name('json');
            });
        Route::prefix('content-automations')->name('content-automations.')
            ->middleware(['admin.super', 'content.production.enabled'])->group(function (): void {
                Route::get('/', [TaskAutomationController::class, 'index'])->name('index');
                Route::get('{task}/edit', [TaskAutomationController::class, 'edit'])->name('edit');
                Route::put('{task}', [TaskAutomationController::class, 'update'])->name('update');
                Route::post('{task}/pause', [TaskAutomationController::class, 'pause'])->name('pause');
                Route::post('{task}/resume', [TaskAutomationController::class, 'resume'])->name('resume');
                Route::post('{task}/run-now', [TaskAutomationController::class, 'runNow'])->name('run-now');
                Route::post('{task}/schedules/{taskSchedule}/retry', [TaskAutomationController::class, 'retry'])->name('retry');
                Route::post('{task}/fallback', [TaskAutomationController::class, 'fallback'])->name('fallback');
            });
        Route::get('daily-content-production', fn () => redirect()->route('admin.content-automations.index'))->name('daily-content-production.index');
    });
});
