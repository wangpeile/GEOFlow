<?php

namespace App\Services\GeoFlow;

use App\Models\WritingRule;
use App\Models\WritingRuleVersion;
use Illuminate\Validation\ValidationException;

final class WritingRuleResolver
{
    /**
     * @return array{writing_rule_id:int,writing_rule_version_id:int,name:string,version:int,article_type:array<string,mixed>|null,settings:array<string,mixed>,settings_hash:string}
     */
    public function snapshot(WritingRule $rule): array
    {
        if (! $rule->is_active) {
            throw ValidationException::withMessages(['writing_rule_id' => '选择的写作规则已停用。']);
        }

        $version = $rule->currentVersionRecord();
        if (! $version instanceof WritingRuleVersion) {
            throw ValidationException::withMessages(['writing_rule_id' => '选择的写作规则缺少可用版本。']);
        }

        return $this->snapshotVersion($rule, $version);
    }

    /**
     * @return array{writing_rule_id:int,writing_rule_version_id:int,name:string,version:int,article_type:array<string,mixed>|null,settings:array<string,mixed>,settings_hash:string}
     */
    public function snapshotVersion(WritingRule $rule, WritingRuleVersion $version): array
    {
        if (! $rule->is_active || $version->writing_rule_id !== $rule->getKey()) {
            throw ValidationException::withMessages(['writing_rule_id' => '写作规则或指定版本不可用。']);
        }

        $version->loadMissing('articleType');
        $articleType = $version->articleType;

        return [
            'writing_rule_id' => $rule->getKey(),
            'writing_rule_version_id' => $version->getKey(),
            'name' => $rule->name,
            'version' => $version->version,
            'article_type' => $articleType ? [
                'id' => $articleType->getKey(),
                'code' => $articleType->code,
                'name' => $articleType->name,
                'default_settings' => $articleType->default_settings ?? [],
            ] : null,
            'settings' => array_replace($articleType?->default_settings ?? [], $version->settings ?? []),
            'settings_hash' => $version->settings_hash,
        ];
    }
}
