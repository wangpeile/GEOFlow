<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SelectContentTitleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProtectedWorkflows() === true
            && (bool) config('geoflow.content_production_pipeline_enabled', false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'candidate_id' => [
                'nullable',
                'uuid',
                'required_without:custom_title',
                Rule::prohibitedIf(fn (): bool => $this->filled('custom_title')),
            ],
            'custom_title' => [
                'nullable',
                'string',
                'max:180',
                'required_without:candidate_id',
                Rule::prohibitedIf(fn (): bool => $this->filled('candidate_id')),
            ],
        ];
    }
}
