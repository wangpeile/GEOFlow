<?php

namespace App\Http\Requests\Admin;

use App\Models\DistributionChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublishWordPressContentRequest extends FormRequest
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
            'distribution_channel_id' => [
                'required',
                'integer',
                Rule::exists('distribution_channels', 'id')->where(fn ($query) => $query
                    ->where('channel_type', 'wordpress_rest')
                    ->where('status', DistributionChannel::STATUS_ACTIVE)),
            ],
            'publication_mode' => ['required', Rule::in(['draft', 'immediate', 'scheduled'])],
            'scheduled_for' => ['nullable', 'required_if:publication_mode,scheduled', 'date', 'after:now'],
        ];
    }
}
