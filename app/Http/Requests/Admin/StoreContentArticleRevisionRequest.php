<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreContentArticleRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProtectedWorkflows() === true
            && (bool) config('geoflow.content_production_pipeline_enabled', false);
    }

    public function rules(): array
    {
        return [
            'feedback' => ['required', 'string', 'min:3', 'max:5000'],
        ];
    }
}
