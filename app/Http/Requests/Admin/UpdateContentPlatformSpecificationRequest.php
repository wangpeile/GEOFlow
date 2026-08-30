<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use JsonException;

class UpdateContentPlatformSpecificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->canManageProtectedWorkflows()
            && (bool) config('geoflow.content_production_pipeline_enabled', false);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('rules_json')) && trim($this->input('rules_json')) !== '') {
            try {
                $rules = json_decode($this->input('rules_json'), true, 512, JSON_THROW_ON_ERROR);
                $this->merge(['rules' => is_array($rules) ? $rules : null]);
            } catch (JsonException) {
                $this->merge(['rules_json_invalid' => true]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:100'],
            'version' => ['required', 'string', 'max:40'],
            'status' => ['required', Rule::in(['active', 'review_needed'])],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'source_summary' => ['nullable', 'string', 'max:4000'],
            'rules_json' => ['nullable', 'string', 'max:30000'],
            'rules_json_invalid' => ['prohibited'],
            'rules' => ['nullable', 'array'],
            'verified_at' => ['nullable', 'date'],
            'next_review_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'rules.array' => '规则 JSON 须是对象格式。',
            'rules_json_invalid.prohibited' => '规则 JSON 格式不正确。',
        ];
    }
}
