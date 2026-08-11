<?php

namespace App\Support\GeoFlow\ContentProduction;

use App\Enums\ContentProductionStage;
use InvalidArgumentException;
use ValueError;

final readonly class ContentStagePayload
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public ContentProductionStage $stage,
        public array $data,
        public int $schemaVersion = 1,
    ) {
        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('Content stage payload schema version must be at least 1.');
        }
    }

    /**
     * @return array{schema_version: int, stage: string, data: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'stage' => $this->stage->value,
            'data' => $this->data,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        if (! is_int($payload['schema_version'] ?? null)
            || ! is_string($payload['stage'] ?? null)
            || ! is_array($payload['data'] ?? null)) {
            throw new InvalidArgumentException('Invalid content stage payload envelope.');
        }

        try {
            $stage = ContentProductionStage::from($payload['stage']);
        } catch (ValueError $exception) {
            throw new InvalidArgumentException('Invalid content production stage.', previous: $exception);
        }

        return new self($stage, $payload['data'], $payload['schema_version']);
    }
}
