<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentPlatformFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->canManageProtectedWorkflows()
            && (bool) config('geoflow.content_production_pipeline_enabled', false);
    }

    protected function prepareForValidation(): void
    {
        foreach (['reasons', 'manual_adjustments'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => preg_split('/[,，\r\n]+/u', $this->input($field), -1, PREG_SPLIT_NO_EMPTY) ?: []]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::in(['published', 'rejected', 'manual_adjusted'])],
            'reasons' => ['nullable', 'array', 'max:10'],
            'reasons.*' => ['string', 'max:200'],
            'manual_adjustments' => ['nullable', 'array', 'max:10'],
            'manual_adjustments.*' => ['string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
