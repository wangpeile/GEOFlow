<?php

namespace App\Http\Requests\Admin;

use App\Models\WritingRuleVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTaskAutomationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'production_time' => $this->input('production_time', '00:05'),
            'production_output_policy' => $this->input('production_output_policy', 'wordpress_draft'),
        ]);
    }

    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->canManageProtectedWorkflows();
    }

    public function rules(): array
    {
        return [
            'writing_rule_id' => ['required', 'integer', Rule::exists('writing_rules', 'id')->where('is_active', true)],
            'writing_rule_version_id' => ['required', 'integer', 'exists:writing_rule_versions,id'],
            'content_topic_id' => ['nullable', 'integer', 'exists:content_topics,id'],
            'topics' => ['nullable', 'string', 'max:10000'],
            'automation_timezone' => ['required', 'timezone'],
            'production_time' => ['required', 'date_format:H:i'],
            'production_output_policy' => ['required', Rule::in(['wordpress_draft', 'wordpress_review'])],
            'create_publishing_package_after_review' => ['nullable', 'boolean'],
            'platforms_after_review' => ['nullable', 'array'],
            'platforms_after_review.*' => ['string', Rule::in(['baijiahao', 'zhihu', 'wechat', 'toutiao', 'sohu', 'netease'])],
            'daily_production_limit' => ['required', 'integer', 'between:1,30'],
            'max_production_concurrency' => ['required', 'integer', 'between:1,5'],
            'production_failure_policy' => ['required', Rule::in(['continue', 'pause'])],
            'daily_token_budget' => ['nullable', 'integer', 'between:1000,10000000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $version = WritingRuleVersion::query()->find($this->integer('writing_rule_version_id'));
            if ($version && $version->writing_rule_id !== $this->integer('writing_rule_id')) {
                $validator->errors()->add('writing_rule_version_id', '写作规则版本不属于所选规则。');
            }
            $topics = collect(preg_split('/\R/u', $this->string('topics')->toString()) ?: [])
                ->map(fn (string $topic): string => trim($topic))
                ->filter()
                ->unique();
            if (! $this->integer('content_topic_id') && $topics->isEmpty()) {
                $validator->errors()->add('topics', '请选择内容专题，或至少填写一个独立选题。');
            }
            if ($topics->count() > 100) {
                $validator->errors()->add('topics', '选题最多 100 条。');
            }
        }];
    }
}
