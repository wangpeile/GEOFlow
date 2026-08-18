<?php

namespace App\Http\Requests\Admin;

use App\Models\ContentGroup;
use App\Models\ContentVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExportContentVariantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->canManageProtectedWorkflows()
            && (bool) config('geoflow.content_production_pipeline_enabled', false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'variant_ids' => ['required', 'array', 'min:1', 'max:7'],
            'variant_ids.*' => ['required', 'integer', 'distinct', Rule::exists('content_variants', 'id')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $contentGroup = $this->route('contentGroup');
            if (! $contentGroup instanceof ContentGroup) {
                $validator->errors()->add('variant_ids', '内容组不存在。');

                return;
            }
            $requested = array_map('intval', $this->input('variant_ids', []));
            $validCount = ContentVariant::query()
                ->where('content_group_id', $contentGroup->id)
                ->where('platform', '!=', 'wordpress')
                ->whereIn('id', $requested)
                ->count();
            if ($validCount !== count($requested)) {
                $validator->errors()->add('variant_ids', '选择的平台版本不属于当前内容组。');
            }
        }];
    }
}
