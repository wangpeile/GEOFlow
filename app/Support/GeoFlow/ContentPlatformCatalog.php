<?php

namespace App\Support\GeoFlow;

class ContentPlatformCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return (array) config('content_platforms', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $platform): array
    {
        return $this->all()[$platform] ?? [];
    }
}
