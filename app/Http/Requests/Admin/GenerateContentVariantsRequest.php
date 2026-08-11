<?php

namespace App\Http\Requests\Admin;

use App\Support\GeoFlow\ContentPlatformCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateContentVariantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->canManageProtectedWorkflows()
            && (bool) config('geoflow.content_production_pipeline_enabled', false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $platforms = array_values(array_diff(
            array_keys(app(ContentPlatformCatalog::class)->all()),
            ['wordpress'],
        ));

        return [
            'platforms' => ['required', 'array', 'size:1'],
            'platforms.*' => ['required', 'string', 'distinct', Rule::in($platforms)],
        ];
    }
}
