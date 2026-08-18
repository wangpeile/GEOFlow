<?php

namespace App\Http\Requests\Admin;

use App\Models\ContentVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewContentVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->canManageProtectedWorkflows()
            && (bool) config('geoflow.content_production_pipeline_enabled', false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_version' => ['required', 'integer', 'min:1'],
            'decision' => ['required', Rule::in([ContentVariant::REVIEW_APPROVED, ContentVariant::REVIEW_REJECTED])],
            'note' => ['nullable', 'required_if:decision,'.ContentVariant::REVIEW_REJECTED, 'string', 'max:2000'],
        ];
    }
}
