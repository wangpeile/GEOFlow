<?php

namespace Tests\Unit;

use App\Contracts\Outbound\HostResolver;
use App\Services\GeoFlow\UrlImportProcessingService;
use InvalidArgumentException;
use Tests\TestCase;

class UrlImportProcessingServiceTest extends TestCase
{
    private UrlImportProcessingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(UrlImportProcessingService::class);
    }

    public function test_it_accepts_valid_public_url(): void
    {
        $result = $this->service->normalizeInputUrl('https://www.example.com');

        $this->assertSame('https://www.example.com', $result['url']);
        $this->assertSame('www.example.com', $result['host']);
    }

    public function test_it_rejects_localhost(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->normalizeInputUrl('http://localhost');
    }

    public function test_it_rejects_loopback_ip(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->normalizeInputUrl('http://127.0.0.1');
    }

    public function test_it_rejects_zero_ip(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->normalizeInputUrl('http://0.0.0.0');
    }

    public function test_it_rejects_local_hostname(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->normalizeInputUrl('http://mycomputer.local');
    }

    public function test_it_rejects_empty_url(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->normalizeInputUrl('');
    }

    public function test_it_accepts_url_without_scheme(): void
    {
        $result = $this->service->normalizeInputUrl('example.com/path');

        $this->assertSame('https://example.com/path', $result['url']);
        $this->assertSame('example.com', $result['host']);
    }

    public function test_it_accepts_valid_url_with_path(): void
    {
        $result = $this->service->normalizeInputUrl('https://www.example.com/some/path');

        $this->assertSame('https://www.example.com/some/path', $result['url']);
        $this->assertSame('www.example.com', $result['host']);
    }

    public function test_it_preserves_http_scheme(): void
    {
        $result = $this->service->normalizeInputUrl('http://www.example.com');

        $this->assertSame('http://www.example.com', $result['url']);
    }

    public function test_it_turns_dns_lookup_failures_into_a_validation_error(): void
    {
        $this->app->instance(HostResolver::class, new class implements HostResolver
        {
            public function resolve(string $host): array
            {
                throw new \RuntimeException('temporary DNS server failure');
            }
        });

        $service = $this->app->make(UrlImportProcessingService::class);

        $this->expectException(InvalidArgumentException::class);

        $service->normalizeInputUrl('https://unavailable.example/path');
    }
}
