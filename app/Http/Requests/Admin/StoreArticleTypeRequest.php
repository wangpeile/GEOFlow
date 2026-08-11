<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArticleTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProtectedWorkflows() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        $articleTypeId = $this->route('articleType')?->getKey();

        return [
            'code' => ['required', 'alpha_dash:ascii', 'max:80', Rule::unique('article_types', 'code')->ignore($articleTypeId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'default_min_words' => ['required', 'integer', 'between:300,10000'],
            'default_max_words' => ['required', 'integer', 'between:300,10000', 'gte:default_min_words'],
            'default_min_headings' => ['required', 'integer', 'between:2,20'],
            'default_max_headings' => ['required', 'integer', 'between:2,20', 'gte:default_min_headings'],
            'structure_notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
