<?php

namespace Tests\Feature;

use Tests\TestCase;

class ContentProductionFeatureFlagTest extends TestCase
{
    public function test_new_content_production_pipeline_is_disabled_by_default(): void
    {
        $this->assertFalse(config('geoflow.content_production_pipeline_enabled'));
    }

    public function test_environment_examples_keep_the_pipeline_disabled_for_safe_rollout(): void
    {
        foreach (['.env.example', '.env.prod.example'] as $file) {
            $contents = file_get_contents(base_path($file));

            $this->assertIsString($contents);
            $this->assertStringContainsString(
                'GEOFLOW_CONTENT_PRODUCTION_PIPELINE_ENABLED=false',
                $contents,
                $file,
            );
        }
    }
}
