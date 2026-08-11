<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RetrieveContentEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProtectedWorkflows() === true
            && (bool) config('geoflow.content_production_pipeline_enabled', false);
    }

    public function rules(): array
    {
        return [
            'knowledge_base_id' => ['required', 'integer', 'exists:knowledge_bases,id'],
            'query' => ['required', 'string', 'max:500'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }
}
