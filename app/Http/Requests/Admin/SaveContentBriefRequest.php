<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveContentBriefRequest extends FormRequest
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
            'article_type' => ['required', 'string', 'max:100'],
            'target_audience' => ['required', 'string', 'max:500'],
            'search_intent' => ['required', 'string', 'max:500'],
            'content_angle' => ['required', 'string', 'max:1000'],
            'must_cover' => ['nullable', 'string', 'max:5000'],
            'avoid_topics' => ['nullable', 'string', 'max:5000'],
            'evidence_ids' => ['nullable', 'array', 'max:100'],
            'evidence_ids.*' => [
                'integer',
                Rule::exists('content_evidences', 'id')->where(
                    'content_production_id',
                    $this->route('contentProduction')?->getKey(),
                ),
            ],
        ];
    }
}
