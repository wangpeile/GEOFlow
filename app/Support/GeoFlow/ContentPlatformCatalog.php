<?php

namespace App\Support\GeoFlow;

class ContentPlatformCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return array_filter((array) config('content_platforms', []), static fn (array $rules): bool => ! ($rules['deprecated'] ?? false));
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $platform): array
    {
        $platform = $this->canonical($platform);

        return ((array) config('content_platforms', []))[$platform] ?? [];
    }

    public function canonical(string $platform): string
    {
        $rules = ((array) config('content_platforms', []))[$platform] ?? [];

        return (string) ($rules['canonical_platform'] ?? $platform);
    }
}
