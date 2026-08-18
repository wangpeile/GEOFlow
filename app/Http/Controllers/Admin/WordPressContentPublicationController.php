<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PublishWordPressContentRequest;
use App\Models\ArticleDistribution;
use App\Models\ContentGroup;
use App\Models\ContentVariant;
use App\Models\DistributionChannel;
use App\Services\GeoFlow\ContentWordPressPublicationService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class WordPressContentPublicationController extends Controller
{
    public function __construct(private readonly ContentWordPressPublicationService $service) {}

    public function store(PublishWordPressContentRequest $request, ContentGroup $contentGroup, ContentVariant $contentVariant): RedirectResponse
    {
        $this->assertNested($contentGroup, $contentVariant);
        $channel = DistributionChannel::query()->findOrFail((int) $request->validated('distribution_channel_id'));
        try {
            $this->service->queue(
                $contentGroup,
                $contentVariant,
                $channel,
                $request->user('admin'),
                (string) $request->validated('publication_mode'),
                $request->validated('scheduled_for'),
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors([$exception->getMessage()]);
        }

        return back()->with('message', 'WordPress 发布任务已进入队列。');
    }

    public function retry(ContentGroup $contentGroup, ContentVariant $contentVariant, ArticleDistribution $articleDistribution): RedirectResponse
    {
        $this->assertNested($contentGroup, $contentVariant);
        abort_unless($articleDistribution->content_variant_id === $contentVariant->id, 404);
        try {
            $this->service->retry($articleDistribution);
        } catch (RuntimeException $exception) {
            return back()->withErrors([$exception->getMessage()]);
        }

        return back()->with('message', 'WordPress 发布任务已重新进入队列。');
    }

    private function assertNested(ContentGroup $contentGroup, ContentVariant $contentVariant): void
    {
        abort_unless($contentVariant->content_group_id === $contentGroup->id && $contentVariant->platform === 'wordpress', 404);
    }
}
