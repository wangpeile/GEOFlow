<?php

namespace App\Services\GeoFlow;

use App\Models\Admin;
use App\Models\WritingRule;
use App\Models\WritingRuleVersion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class WritingRuleVersionService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Admin $admin, array $attributes, bool $isPreset = false): WritingRule
    {
        return DB::transaction(function () use ($admin, $attributes, $isPreset): WritingRule {
            $rule = WritingRule::query()->create([
                'article_type_id' => $attributes['article_type_id'],
                'created_by_admin_id' => $admin->getKey(),
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'is_preset' => $isPreset,
                'is_active' => $attributes['is_active'],
                'current_version' => 1,
            ]);

            $this->createVersion($rule, $admin, 1, $attributes);

            return $rule->load(['articleType', 'versions']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Admin $admin, WritingRule $rule, array $attributes): WritingRule
    {
        return DB::transaction(function () use ($admin, $rule, $attributes): WritingRule {
            $locked = WritingRule::query()->lockForUpdate()->findOrFail($rule->getKey());
            $version = $locked->current_version + 1;

            $locked->update([
                'article_type_id' => $attributes['article_type_id'],
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'is_active' => $attributes['is_active'],
                'current_version' => $version,
            ]);
            $this->createVersion($locked, $admin, $version, $attributes);

            return $locked->load(['articleType', 'versions']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createVersion(WritingRule $rule, Admin $admin, int $version, array $attributes): WritingRuleVersion
    {
        $settings = Arr::only($attributes, [
            'language', 'country', 'tone', 'perspective', 'publisher_identity', 'brand_name',
            'official_site_url', 'formality', 'creativity',
            'min_words', 'max_words', 'min_headings', 'max_headings', 'include_citations',
            'include_internal_links', 'internal_links', 'include_external_links', 'include_faq', 'include_cta',
            'cta_text', 'knowledge_base_ids', 'sensitive_word_ids', 'brand_profile', 'instructions',
        ]);
        $settings['knowledge_base_ids'] = array_values(array_unique(array_map('intval', $settings['knowledge_base_ids'] ?? [])));
        $settings['sensitive_word_ids'] = array_values(array_unique(array_map('intval', $settings['sensitive_word_ids'] ?? [])));
        sort($settings['knowledge_base_ids']);
        sort($settings['sensitive_word_ids']);
        ksort($settings);

        return $rule->versions()->create([
            'article_type_id' => $attributes['article_type_id'],
            'created_by_admin_id' => $admin->getKey(),
            'version' => $version,
            'settings' => $settings,
            'settings_hash' => hash('sha256', json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'change_note' => $attributes['change_note'] ?? null,
        ]);
    }
}
