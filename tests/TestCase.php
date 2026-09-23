<?php

namespace A2ZWeb\Affiliate\Tests;

use A2ZWeb\Affiliate\AffiliateServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AffiliateServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('affiliate.user_model', User::class);
        $app['config']->set('affiliate.admin_notification_email', 'admin@example.com');
        $app['config']->set('affiliate.terms.general_url', 'https://example.com/terms');
        $app['config']->set('affiliate.terms.affiliate_url', 'https://example.com/affiliate-terms');
        $app['config']->set('affiliate.layout', 'affiliate-test::layout');
        $app['config']->set('affiliate_statements.issuing_entity.code', 'test_ltd');
        $app['config']->set('affiliate_statements.issuing_entity.legal_name', 'Test Entity Ltd.');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Minimal host layout honouring the package's layout contract.
        $this->app['view']->addNamespace('affiliate-test', __DIR__.'/views');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
