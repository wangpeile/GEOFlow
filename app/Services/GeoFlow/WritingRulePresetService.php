<?php

namespace App\Services\GeoFlow;

use App\Models\Admin;
use App\Models\ArticleType;
use App\Models\WritingRule;

final class WritingRulePresetService
{
    public function __construct(private readonly WritingRuleVersionService $versions) {}

    public function install(Admin $admin): int
    {
        $created = 0;

        foreach ($this->presets() as $preset) {
            $type = ArticleType::query()->firstOrCreate(
                ['code' => $preset['code']],
                [
                    'name' => $preset['type_name'],
                    'description' => $preset['type_description'],
                    'default_settings' => $preset['type_defaults'],
                    'is_system' => true,
                    'is_active' => true,
                    'created_by_admin_id' => $admin->getKey(),
                ],
            );

            if (WritingRule::query()->where('name', $preset['rule_name'])->where('is_preset', true)->exists()) {
                continue;
            }

            $this->versions->create($admin, [
                'article_type_id' => $type->getKey(),
                'name' => $preset['rule_name'],
                'description' => $preset['rule_description'],
                'language' => 'zh_CN',
                'country' => 'CN',
                'tone' => $preset['tone'],
                'perspective' => 'auto',
                'formality' => 'auto',
                'creativity' => 35,
                'min_words' => $preset['type_defaults']['min_words'],
                'max_words' => $preset['type_defaults']['max_words'],
                'min_headings' => $preset['type_defaults']['min_headings'],
                'max_headings' => $preset['type_defaults']['max_headings'],
                'include_citations' => true,
                'include_internal_links' => true,
                'include_external_links' => true,
                'include_faq' => true,
                'include_cta' => false,
                'cta_text' => null,
                'knowledge_base_ids' => [],
                'sensitive_word_ids' => [],
                'brand_profile' => null,
                'instructions' => $preset['instructions'],
                'change_note' => '系统预设初始版本',
                'is_active' => true,
            ], true);
            $created++;
        }

        return $created;
    }

    /** @return list<array<string, mixed>> */
    private function presets(): array
    {
        return [
            ['code' => 'knowledge', 'type_name' => '知识科普', 'type_description' => '解释概念、术语和基础知识。', 'rule_name' => '中文知识科普', 'rule_description' => '面向普通读者的准确、易懂科普内容。', 'tone' => 'professional', 'instructions' => '先给出一句话定义，再解释原理、适用场景和常见误区；关键事实必须有依据。', 'type_defaults' => ['min_words' => 1000, 'max_words' => 2000, 'min_headings' => 5, 'max_headings' => 7]],
            ['code' => 'how_to', 'type_name' => '操作指南', 'type_description' => '分步骤指导读者完成任务。', 'rule_name' => '中文操作指南', 'rule_description' => '强调步骤、注意事项和检查结果。', 'tone' => 'friendly', 'instructions' => '使用明确编号步骤，每一步说明目的和验收方法。', 'type_defaults' => ['min_words' => 1000, 'max_words' => 2000, 'min_headings' => 5, 'max_headings' => 8]],
            ['code' => 'product_solution', 'type_name' => '产品与解决方案', 'type_description' => '介绍产品能力、解决方案、价值和适用场景。', 'rule_name' => '中文产品解决方案', 'rule_description' => '基于品牌和产品资料生成可信的解决方案文章。', 'tone' => 'professional', 'instructions' => '围绕用户问题、方案能力、实施路径和可验证收益展开；不得编造功能、客户或数据。', 'type_defaults' => ['min_words' => 1200, 'max_words' => 2400, 'min_headings' => 5, 'max_headings' => 8]],
            ['code' => 'comparison', 'type_name' => '比较文章', 'type_description' => '比较多个产品、方案或工具。', 'rule_name' => '中文产品比较', 'rule_description' => '以统一标准对比多个方案。', 'tone' => 'neutral', 'instructions' => '明确比较标准、适用场景和选择建议，避免无依据排名。', 'type_defaults' => ['min_words' => 1500, 'max_words' => 2800, 'min_headings' => 6, 'max_headings' => 9]],
            ['code' => 'listicle', 'type_name' => '清单文章', 'type_description' => '以列表组织推荐、检查项或要点。', 'rule_name' => '中文清单文章', 'rule_description' => '适合盘点和 Top 清单。', 'tone' => 'conversational', 'instructions' => '每个清单项采用一致结构，并说明入选理由。', 'type_defaults' => ['min_words' => 1000, 'max_words' => 2200, 'min_headings' => 5, 'max_headings' => 10]],
            ['code' => 'case_study', 'type_name' => '案例文章', 'type_description' => '呈现问题、实施过程和可验证结果。', 'rule_name' => '中文案例文章', 'rule_description' => '用于客户案例、项目复盘和实践总结。', 'tone' => 'authoritative', 'instructions' => '按背景、挑战、方案、实施和结果组织；缺少来源的客户名称、数据和效果不得推测。', 'type_defaults' => ['min_words' => 1200, 'max_words' => 2400, 'min_headings' => 5, 'max_headings' => 8]],
            ['code' => 'news', 'type_name' => '新闻资讯', 'type_description' => '时效性行业新闻、事件报道和动态更新。', 'rule_name' => '中文新闻资讯', 'rule_description' => '强调时间、来源和事实核验。', 'tone' => 'neutral', 'instructions' => '开头交代时间、主体和事件；区分事实、引用和推断，引用最新可靠来源。', 'type_defaults' => ['min_words' => 800, 'max_words' => 1600, 'min_headings' => 4, 'max_headings' => 6]],
            ['code' => 'blog', 'type_name' => '常规博客', 'type_description' => '观点、行业洞察和经验总结。', 'rule_name' => '中文专业博客', 'rule_description' => '适合 WordPress 的中文专业博客文章。', 'tone' => 'professional', 'instructions' => '开篇直接回答主题，正文提供可执行建议，结尾总结主要结论。', 'type_defaults' => ['min_words' => 1200, 'max_words' => 2200, 'min_headings' => 5, 'max_headings' => 7]],
        ];
    }
}
