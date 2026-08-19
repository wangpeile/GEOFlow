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
            'internal_links' => is_array($this->input('internal_links'))
                ? $this->input('internal_links')
                : $this->normalizeInternalLinks((string) $this->input('internal_links', '')),
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
            'publisher_identity' => ['required', Rule::in(['official_brand', 'independent_editorial'])],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'official_site_url' => [
                'nullable',
                'url:http,https',
                'max:2048',
                Rule::requiredIf($this->boolean('include_internal_links')),
            ],
            'formality' => ['required', Rule::in(['auto', 'formal', 'informal'])],
            'creativity' => ['required', 'integer', 'between:0,100'],
            'min_words' => ['required', 'integer', 'between:300,10000'],
            'max_words' => ['required', 'integer', 'between:300,10000', 'gte:min_words'],
            'min_headings' => ['required', 'integer', 'between:2,20'],
            'max_headings' => ['required', 'integer', 'between:2,20', 'gte:min_headings'],
            'include_citations' => ['required', 'boolean'],
            'include_internal_links' => ['required', 'boolean'],
            'internal_links' => ['nullable', 'array', 'max:50'],
            'internal_links.*.anchor' => ['required', 'string', 'max:100'],
            'internal_links.*.url' => ['required', 'url:http,https', 'max:2048'],
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
                if ($this->input('publisher_identity') === 'official_brand' && trim((string) $this->input('brand_name')) === '') {
                    $validator->errors()->add('brand_name', '厂商官网视角必须填写品牌或厂商名称。');
                }
                if ($this->boolean('include_internal_links') && empty($this->input('internal_links', []))) {
                    $validator->errors()->add('internal_links', '启用内部链接后，至少填写一条“锚文本|URL”。');
                }
                $officialHost = mb_strtolower((string) parse_url((string) $this->input('official_site_url'), PHP_URL_HOST));
                foreach ((array) $this->input('internal_links', []) as $index => $link) {
                    $linkHost = mb_strtolower((string) parse_url((string) data_get($link, 'url'), PHP_URL_HOST));
                    if ($officialHost !== '' && $linkHost !== '' && $linkHost !== $officialHost && ! str_ends_with($linkHost, '.'.$officialHost)) {
                        $validator->errors()->add("internal_links.{$index}.url", '内部链接必须属于官方网站域名或其子域名。');
                    }
                }
            },
        ];
    }

    /** @return list<array{anchor:string,url:string}> */
    private function normalizeInternalLinks(string $value): array
    {
        return collect(preg_split('/\R/u', $value) ?: [])
            ->map(function (string $line): ?array {
                [$anchor, $url] = array_pad(explode('|', trim($line), 2), 2, '');
                $anchor = trim($anchor);
                $url = trim($url);

                return $anchor !== '' || $url !== '' ? ['anchor' => $anchor, 'url' => $url] : null;
            })
            ->filter()
            ->values()
            ->all();
    }
}
