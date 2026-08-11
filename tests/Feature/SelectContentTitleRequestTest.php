<?php

namespace Tests\Feature;

use App\Http\Requests\Admin\SelectContentTitleRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class SelectContentTitleRequestTest extends TestCase
{
    public function test_generated_title_candidate_can_be_selected(): void
    {
        $candidateId = (string) Str::uuid();

        $validator = $this->validator([
            'candidate_id' => $candidateId,
            'custom_title' => '',
        ]);

        $this->assertTrue($validator->passes());
        $this->assertSame($candidateId, $validator->validated()['candidate_id']);
    }

    public function test_custom_title_can_be_saved_without_a_candidate(): void
    {
        $validator = $this->validator(['custom_title' => '企业视频会议系统选型指南']);

        $this->assertTrue($validator->passes());
        $this->assertSame('企业视频会议系统选型指南', $validator->validated()['custom_title']);
    }

    public function test_candidate_and_custom_title_cannot_be_submitted_together(): void
    {
        $validator = $this->validator([
            'candidate_id' => (string) Str::uuid(),
            'custom_title' => '另一个标题',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('candidate_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('custom_title', $validator->errors()->toArray());
    }

    public function test_a_title_source_is_required(): void
    {
        $validator = $this->validator([
            'candidate_id' => null,
            'custom_title' => '',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('candidate_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('custom_title', $validator->errors()->toArray());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validator(array $data): \Illuminate\Validation\Validator
    {
        $request = SelectContentTitleRequest::create('/', 'POST', $data);

        return Validator::make($data, $request->rules());
    }
}
