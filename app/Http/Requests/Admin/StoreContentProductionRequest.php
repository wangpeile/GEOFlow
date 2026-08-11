<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContentProductionMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProtectedWorkflows() === true
            && (bool) config('geoflow.content_production_pipeline_enabled', false);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('name') && $this->filled('topic')) {
            $this->merge(['name' => mb_substr(trim((string) $this->input('topic')), 0, 255)]);
        }
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'topic' => ['required', 'string', 'max:500'],
            'mode' => ['required', Rule::enum(ContentProductionMode::class)],
            'language' => ['required', Rule::in(['zh_CN'])],
            'target_platforms' => ['nullable', 'array', 'max:8'],
            'target_platforms.*' => ['string', Rule::in(['wordpress', 'baijiahao', 'qq', 'netease', 'sohu', 'toutiao', 'zhihu', 'wechat'])],
        ];
    }
}
