<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWritingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProtectedWorkflows() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'include_citations' => $this->boolean('include_citations'),
            'include_internal_links' => $this->boolean('include_internal_links'),
            'include_external_links' => $this->boolean('include_external_links'),
            'include_faq' => $this->boolean('include_faq'),
            'include_cta' => $this->boolean('include_cta'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'article_type_id' => ['required', 'integer', Rule::exists('article_types', 'id')->where('is_active', true)],
            'language' => ['required', Rule::in(['zh_CN'])],
            'country' => ['required', Rule::in(['CN'])],
            'tone' => ['required', Rule::in(['professional', 'neutral', 'friendly', 'authoritative', 'conversational'])],
            'perspective' => ['required', Rule::in(['auto', 'first_singular', 'first_plural', 'second', 'third'])],
            'formality' => ['required', Rule::in(['auto', 'formal', 'informal'])],
            'creativity' => ['required', 'integer', 'between:0,100'],
            'min_words' => ['required', 'integer', 'between:300,10000'],
            'max_words' => ['required', 'integer', 'between:300,10000', 'gte:min_words'],
            'min_headings' => ['required', 'integer', 'between:2,20'],
            'max_headings' => ['required', 'integer', 'between:2,20', 'gte:min_headings'],
            'include_citations' => ['required', 'boolean'],
            'include_internal_links' => ['required', 'boolean'],
            'include_external_links' => ['required', 'boolean'],
            'include_faq' => ['required', 'boolean'],
            'include_cta' => ['required', 'boolean'],
            'cta_text' => ['nullable', 'string', 'max:500', Rule::requiredIf($this->boolean('include_cta'))],
            'knowledge_base_ids' => ['nullable', 'array', 'max:5'],
            'knowledge_base_ids.*' => ['integer', 'distinct', 'exists:knowledge_bases,id'],
            'sensitive_word_ids' => ['nullable', 'array', 'max:100'],
            'sensitive_word_ids.*' => ['integer', 'distinct', 'exists:sensitive_words,id'],
            'brand_profile' => ['nullable', 'string', 'max:5000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'change_note' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->boolean('include_citations') && ! $this->boolean('include_external_links') && empty($this->input('knowledge_base_ids', []))) {
                    $validator->errors()->add('include_citations', '启用引用时，请同时启用外部链接或至少选择一个知识库。');
                }
                if ($this->boolean('include_cta') && trim((string) $this->input('cta_text')) === '') {
                    $validator->errors()->add('cta_text', '启用行动号召后必须填写行动号召内容。');
                }
            },
        ];
    }
}
