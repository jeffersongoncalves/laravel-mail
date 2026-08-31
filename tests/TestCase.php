<?php

namespace JeffersonGoncalves\LaravelMail\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JeffersonGoncalves\LaravelMail\LaravelMailServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * Same order as LaravelMailServiceProvider::hasMigrations().
     */
    private const MIGRATION_ORDER = [
        'create_mail_logs_table',
        'create_mail_templates_table',
        'create_mail_template_versions_table',
        'create_mail_tracking_events_table',
        'create_mail_suppressions_table',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'JeffersonGoncalves\\LaravelMail\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelMailServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $this->testing_connection());
    }

    /**
     * Defaults to an in-memory SQLite connection for local development; CI
     * (tests.yml) sets LARAVEL_MAIL_TEST_DB_* to run the same suite against
     * real MySQL and PostgreSQL instances too. Deliberately not the plain
     * DB_* names: Orchestra Testbench itself sets DB_CONNECTION=testing by
     * convention, which would collide with (and always win over) a driver
     * value read from the same variable here.
     *
     * @return array<string, mixed>
     */
    protected function testing_connection(): array
    {
        $driver = env('LARAVEL_MAIL_TEST_DB_DRIVER', 'sqlite');

        if ($driver === 'sqlite') {
            return ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];
        }

        return [
            'driver' => $driver,
            'host' => env('LARAVEL_MAIL_TEST_DB_HOST', '127.0.0.1'),
            'port' => env('LARAVEL_MAIL_TEST_DB_PORT'),
            'database' => env('LARAVEL_MAIL_TEST_DB_DATABASE', 'testing'),
            'username' => env('LARAVEL_MAIL_TEST_DB_USERNAME', 'root'),
            'password' => env('LARAVEL_MAIL_TEST_DB_PASSWORD', ''),
            'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
            'prefix' => '',
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $stubsPath = __DIR__.'/../database/migrations';
        $tempPath = sys_get_temp_dir().'/laravel-mail-migrations';

        if (! is_dir($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        foreach (self::MIGRATION_ORDER as $index => $name) {
            copy($stubsPath.'/'.$name.'.php.stub', $tempPath.'/'.sprintf('%03d_%s.php', $index, $name));
        }

        $this->loadMigrationsFrom($tempPath);
    }
}
