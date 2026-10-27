<?php

namespace SchmidtVentures\Inbox\Tests;

use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as Orchestra;
use SchmidtVentures\Inbox\InboxServiceProvider;

abstract class TestCase extends Orchestra
{
    protected string $storagePath;

    protected function getPackageProviders($app): array
    {
        return [InboxServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->storagePath = sys_get_temp_dir().'/schmidt-inbox-'.bin2hex(random_bytes(8));

        $app['config']->set('inbox.enabled', true);
        $app['config']->set('inbox.storage', $this->storagePath);
        $app['config']->set('mail.default', 'inbox');
        $app['config']->set('mail.from.address', 'hello@example.test');
        $app['config']->set('mail.from.name', 'Inbox');
    }

    protected function tearDown(): void
    {
        if (isset($this->storagePath) && is_dir($this->storagePath)) {
            (new Filesystem)->deleteDirectory($this->storagePath);
        }

        parent::tearDown();
    }
}
