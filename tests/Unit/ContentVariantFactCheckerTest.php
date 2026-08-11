<?php

namespace Tests\Unit;

use App\Services\GeoFlow\ContentVariantFactChecker;
use Tests\TestCase;

class ContentVariantFactCheckerTest extends TestCase
{
    public function test_it_flags_hard_facts_not_present_in_source(): void
    {
        $report = app(ContentVariantFactChecker::class)->inspect(
            '源文章包含 2026 年数据，增长率为 30%。',
            '平台稿保留 2026 年和 30%，但新增 99%。',
        );

        $this->assertFalse($report['passed']);
        $this->assertContains('99%', $report['extra_hard_facts']);
    }
}
