<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContentOutlineRequest extends FormRequest
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
            'candidate_id' => ['required', 'uuid'],
            'action' => ['required', Rule::in(['select', 'edit', 'delete', 'up', 'down', 'regenerate'])],
            'node_id' => ['nullable', 'required_unless:action,select', 'uuid'],
            'heading' => ['nullable', 'required_if:action,edit', 'string', 'max:180'],
            'level' => ['nullable', 'required_if:action,edit', Rule::in(['h2', 'h3'])],
        ];
    }
}
