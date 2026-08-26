<?php

namespace App\Http\Requests\Admin;

use App\Models\ContentTopicIdea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentTopicIdeaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProtectedWorkflows() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['keywords' => preg_split('/\r\n|\r|\n|,|，/', trim((string) $this->input('keywords', ''))) ?: []]);
    }

    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'max:500'], 'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', 'max:255'], 'angle' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(ContentTopicIdea::STATUSES)], 'scheduled_for' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
