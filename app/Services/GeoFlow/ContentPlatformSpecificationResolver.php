<?php

namespace App\Services\GeoFlow;

use App\Models\ContentPlatformSpecification;
use App\Support\GeoFlow\ContentPlatformCatalog;

final class ContentPlatformSpecificationResolver
{
    public function __construct(private readonly ContentPlatformCatalog $catalog) {}

    /** @return array{rules: array<string, mixed>, specification: ?ContentPlatformSpecification, version: string} */
    public function resolve(string $platform): array
    {
        $canonical = $this->catalog->canonical($platform);
        $baseRules = $this->catalog->get($canonical);
        $specification = ContentPlatformSpecification::query()->where('platform', $canonical)->first();
        $rules = $specification
            ? array_replace_recursive($baseRules, $specification->rules ?? [])
            : $baseRules;

        return [
            'rules' => $rules,
            'specification' => $specification,
            'version' => $specification?->version ?? (string) ($rules['template_version'] ?? '1.0'),
        ];
    }

    /** @return array<string, mixed> */
    public function snapshot(string $platform, ?array $resolved = null): array
    {
        $resolved ??= $this->resolve($platform);

        return [
            'platform' => $this->catalog->canonical($platform),
            'version' => $resolved['version'],
            'specification_id' => $resolved['specification']?->id,
            'rules' => $resolved['rules'],
            'source_level' => $resolved['specification'] ? 'maintained_specification' : 'internal_default',
            'captured_at' => now()->toIso8601String(),
        ];
    }
}
