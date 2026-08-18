<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContentVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->canManageProtectedWorkflows()
            && (bool) config('geoflow.content_production_pipeline_enabled', false);
    }

    protected function prepareForValidation(): void
    {
        foreach (['tags', 'image_requirements'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => preg_split('/[,，\r\n]+/u', $this->input($field), -1, PREG_SPLIT_NO_EMPTY) ?: []]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_version' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:500'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'content' => ['required', 'string', 'max:100000'],
            'tags' => ['array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
            'image_requirements' => ['array', 'max:10'],
            'image_requirements.*' => ['string', 'max:255'],
        ];
    }
}
