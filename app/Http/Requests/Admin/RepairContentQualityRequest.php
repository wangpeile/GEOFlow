<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RepairContentQualityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProtectedWorkflows() === true
            && (bool) config('geoflow.content_production_pipeline_enabled', false);
    }

    public function rules(): array
    {
        return [
            'issue_ids' => ['required', 'array', 'min:1', 'max:50'],
            'issue_ids.*' => ['required', 'string', 'size:20', 'distinct'],
        ];
    }
}
