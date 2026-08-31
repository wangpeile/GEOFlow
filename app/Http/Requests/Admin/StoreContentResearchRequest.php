<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreContentResearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProtectedWorkflows() === true
            && (bool) config('geoflow.content_production_pipeline_enabled', false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['url_import_job_ids' => array_values(array_unique(array_map(
            'intval',
            (array) $this->input('url_import_job_ids', []),
        )))]);
    }

    public function rules(): array
    {
        return [
            'keyword' => ['required', 'string', 'max:500'],
            'use_web_search' => ['nullable', 'boolean'],
            'url_import_job_ids' => ['array', 'max:10'],
            'url_import_job_ids.*' => ['integer', 'distinct', 'exists:url_import_jobs,id'],
        ];
    }
}
