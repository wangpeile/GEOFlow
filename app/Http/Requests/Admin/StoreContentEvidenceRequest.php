<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContentEvidenceUsage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProtectedWorkflows() === true
            && (bool) config('geoflow.content_production_pipeline_enabled', false);
    }

    public function rules(): array
    {
        return [
            'source_title' => ['required', 'string', 'max:255'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'content_snapshot' => ['required', 'string', 'max:100000'],
            'usage' => ['required', Rule::enum(ContentEvidenceUsage::class)],
        ];
    }
}
