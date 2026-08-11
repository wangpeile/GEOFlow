<?php

namespace Tests\Unit;

use App\Enums\ContentProductionMode;
use App\Enums\ContentProductionStage;
use App\Enums\ContentProductionStatus;
use App\Enums\ContentStageFailureType;
use App\Enums\ContentStageStatus;
use App\Support\GeoFlow\ContentProduction\ContentProductionStateTransitions;
use App\Support\GeoFlow\ContentProduction\ContentProductionWorkflow;
use App\Support\GeoFlow\ContentProduction\ContentStagePayload;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ContentProductionContractTest extends TestCase
{
    public function test_modes_and_failure_types_have_stable_serialized_values(): void
    {
        $this->assertSame(
            ['guided', 'standard', 'quick', 'batch', 'editor_assistant'],
            array_column(ContentProductionMode::cases(), 'value'),
        );
        $this->assertSame(
            [
                'validation',
                'data_unavailable',
                'provider_unavailable',
                'timeout',
                'rate_limited',
                'unsafe_content',
                'quality_gate',
                'publication',
                'cancelled',
                'unexpected',
            ],
            array_column(ContentStageFailureType::cases(), 'value'),
        );
    }

    public function test_workflow_order_and_stage_payload_contracts_are_stable(): void
    {
        $workflow = new ContentProductionWorkflow;
        $definitions = $workflow->definitions();

        $this->assertSame(
            [
                'initialize',
                'duplicate_check',
                'research',
                'brief',
                'title',
                'outline',
                'section_writing',
                'assembly',
                'quality_gate',
                'targeted_repair',
                'review',
                'wordpress_publish',
                'platform_rewrite',
            ],
            array_map(static fn ($definition): string => $definition->stage->value, $definitions),
        );
        $this->assertSame(['topic', 'language'], $workflow->definition(ContentProductionStage::Initialize)->requiredInputKeys);
        $this->assertSame(['evidence'], $workflow->definition(ContentProductionStage::Research)->outputKeys);
        $this->assertTrue($workflow->definition(ContentProductionStage::Outline)->confirmationRequiredInGuidedMode);
        $this->assertSame(1, $workflow->definition(ContentProductionStage::Outline)->contractVersion);
        $this->assertSame(ContentProductionStage::DuplicateCheck, $workflow->next(ContentProductionStage::Initialize));
        $this->assertNull($workflow->next(ContentProductionStage::PlatformRewrite));
    }

    public function test_every_stage_input_is_available_from_initial_context_or_an_earlier_stage(): void
    {
        $availableKeys = ['topic', 'language'];

        foreach ((new ContentProductionWorkflow)->definitions() as $definition) {
            foreach ($definition->requiredInputKeys as $requiredInputKey) {
                $this->assertContains($requiredInputKey, $availableKeys, $definition->stage->value);
            }

            $this->assertNotEmpty($definition->outputKeys, $definition->stage->value);
            $this->assertSame($definition->outputKeys, array_values(array_unique($definition->outputKeys)));
            $availableKeys = array_values(array_unique([...$availableKeys, ...$definition->outputKeys]));
        }
    }

    public function test_stage_payload_uses_a_versioned_serializable_envelope(): void
    {
        $payload = new ContentStagePayload(
            ContentProductionStage::Research,
            ['evidence' => [['source' => 'knowledge_base']]],
        );

        $serialized = $payload->toArray();
        $restored = ContentStagePayload::fromArray($serialized);

        $this->assertSame(1, $serialized['schema_version']);
        $this->assertSame('research', $serialized['stage']);
        $this->assertSame($payload->stage, $restored->stage);
        $this->assertSame($payload->data, $restored->data);
    }

    public function test_stage_payload_rejects_an_invalid_envelope(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ContentStagePayload::fromArray([
            'schema_version' => 1,
            'stage' => 'not_a_stage',
            'data' => [],
        ]);
    }

    public function test_project_state_transitions_are_explicit_and_terminal_states_are_closed(): void
    {
        $transitions = new ContentProductionStateTransitions;

        $this->assertTrue($transitions->canTransitionProject(ContentProductionStatus::Draft, ContentProductionStatus::Queued));
        $this->assertTrue($transitions->canTransitionProject(ContentProductionStatus::Failed, ContentProductionStatus::Queued));
        $this->assertFalse($transitions->canTransitionProject(ContentProductionStatus::Draft, ContentProductionStatus::Completed));
        $this->assertFalse($transitions->canTransitionProject(ContentProductionStatus::Completed, ContentProductionStatus::Running));
        $this->assertFalse($transitions->canTransitionProject(ContentProductionStatus::Cancelled, ContentProductionStatus::Queued));

        foreach (ContentProductionStatus::cases() as $status) {
            $this->assertFalse($transitions->canTransitionProject($status, $status));
        }
    }

    public function test_stage_state_transitions_support_retry_without_reopening_success(): void
    {
        $transitions = new ContentProductionStateTransitions;

        $this->assertTrue($transitions->canTransitionStage(ContentStageStatus::Pending, ContentStageStatus::Running));
        $this->assertTrue($transitions->canTransitionStage(ContentStageStatus::Failed, ContentStageStatus::Pending));
        $this->assertFalse($transitions->canTransitionStage(ContentStageStatus::Succeeded, ContentStageStatus::Running));
        $this->assertFalse($transitions->canTransitionStage(ContentStageStatus::Skipped, ContentStageStatus::Pending));

        foreach (ContentStageStatus::cases() as $status) {
            $this->assertFalse($transitions->canTransitionStage($status, $status));
        }
    }
}
