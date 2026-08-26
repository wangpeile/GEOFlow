<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProtectedWorkflows() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'knowledge_base_ids' => array_values(array_filter((array) $this->input('knowledge_base_ids', []))),
            'reference_urls' => preg_split('/\r\n|\r|\n/', trim((string) $this->input('reference_urls', ''))) ?: [],
            'keyword_clusters' => preg_split('/\r\n|\r|\n/', trim((string) $this->input('keyword_clusters', ''))) ?: [],
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'], 'website' => ['nullable', 'string', 'max:500'],
            'audience' => ['nullable', 'string', 'max:500'], 'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'writing_rule_id' => ['nullable', 'integer', Rule::exists('writing_rules', 'id')->where('is_active', true)],
            'knowledge_base_ids' => ['nullable', 'array'], 'knowledge_base_ids.*' => ['integer', Rule::exists('knowledge_bases', 'id')],
            'reference_urls' => ['nullable', 'array'], 'reference_urls.*' => ['url', 'max:2000'],
            'keyword_clusters' => ['nullable', 'array'], 'keyword_clusters.*' => ['string', 'max:255'],
            'material_scope' => ['nullable', 'string', 'max:5000'], 'content_goal' => ['nullable', 'string', 'max:2000'],
            'publishing_cadence' => ['nullable', 'string', 'max:255'], 'is_active' => ['nullable', 'boolean'],
        ];
    }
}
