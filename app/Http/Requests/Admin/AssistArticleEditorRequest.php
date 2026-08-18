<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssistArticleEditorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProtectedWorkflows() === true
            && (bool) config('geoflow.content_production_pipeline_enabled', false);
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['expand', 'rewrite', 'title', 'description', 'paragraph', 'faq', 'outline'])],
            'selection' => ['required', 'string', 'min:1', 'max:12000'],
            'instruction' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
