<?php

namespace Tests;

use App\Contracts\Outbound\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\FakeHostResolver;

/**
 * 测试基类：Feature 测试如需数据库可在用例中 use {@see RefreshDatabase}。
 */
abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        // 外部 PostgreSQL 仅替换测试数据库，不应把 PHPUnit 带入 local/production
        // 运行态；否则 Web CSRF 等测试环境豁免不会生效。
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';
        putenv('APP_ENV=testing');

        $externalDatabase = ($_ENV['TEST_DB_CONNECTION'] ?? $_SERVER['TEST_DB_CONNECTION'] ?? getenv('TEST_DB_CONNECTION')) === 'pgsql';
        if (! $externalDatabase) {
            $this->forceTestingDatabaseEnvironment();
        }

        $app = parent::createApplication();

        $app['config']->set('database.default', $externalDatabase ? 'pgsql' : 'sqlite');
        if (! $externalDatabase) {
            $app['config']->set('database.connections.sqlite.database', ':memory:');
        } else {
            $app['config']->set('database.connections.pgsql.database', (string) ($_ENV['TEST_DB_DATABASE'] ?? $_SERVER['TEST_DB_DATABASE'] ?? getenv('TEST_DB_DATABASE')));
        }
        $app['config']->set('database.connections.pgsql.url', null);
        $app->singleton(HostResolver::class, FakeHostResolver::class);

        return $app;
    }

    private function forceTestingDatabaseEnvironment(): void
    {
        $variables = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
        ];

        foreach ($variables as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
    }
}
