<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContentEvidenceUsage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContentEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProtectedWorkflows() === true
            && (bool) config('geoflow.content_production_pipeline_enabled', false);
    }

    public function rules(): array
    {
        return ['usage' => ['required', Rule::enum(ContentEvidenceUsage::class)]];
    }
}
