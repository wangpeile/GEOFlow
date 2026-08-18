<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContentProductionOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProtectedWorkflows() === true
            && (bool) config('geoflow.content_production_pipeline_enabled', false);
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'author_id' => ['required', 'integer', Rule::exists('authors', 'id')],
        ];
    }
}
