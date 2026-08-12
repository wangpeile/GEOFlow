<?php

namespace Tests\Unit;

use App\Services\GeoFlow\ChineseContentGuard;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChineseContentGuardTest extends TestCase
{
    public function test_it_allows_urls_and_necessary_technical_terms(): void
    {
        (new ChineseContentGuard)->validateField(
            'content',
            '团队通过 OpenAI API 与 SEO 工具完成中文内容分析，并记录核验过程和最终结论，资料见 https://example.com/docs。',
        );

        $this->addToAssertionCount(1);
    }

    public function test_it_rejects_untagged_model_reasoning(): void
    {
        try {
            (new ChineseContentGuard)->validateField(
                'content',
                'The user wants me to write a Chinese content block about private meeting minutes. 这是正文。',
            );
            $this->fail('英文推理内容应当被拒绝。');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('content', $exception->errors());
        }
    }

    public function test_it_rejects_long_english_explanations_without_known_markers(): void
    {
        try {
            (new ChineseContentGuard)->validateField(
                'content',
                'Private meeting minutes provide a secure way to record every important business decision. 中文正文。',
            );
            $this->fail('连续英文说明应当被拒绝。');
        } catch (ValidationException $exception) {
            $this->assertSame('内容包含连续的英文说明，请改为简体中文。', $exception->errors()['content'][0]);
        }
    }
}
