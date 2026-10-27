<?php

namespace SchmidtVentures\Inbox;

use Illuminate\Contracts\Foundation\Application;

class Inbox
{
    public function __construct(private Application $app)
    {
        //
    }

    public function captures(): bool
    {
        $value = $this->app['config']->get('inbox.enabled');

        if ($value === null || $value === '') {
            return $this->app->environment('local');
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function storagePath(): string
    {
        $path = $this->app['config']->get('inbox.storage');

        if (is_string($path) && $path !== '') {
            return $path;
        }

        return $this->app->storagePath('framework/mail-inbox');
    }

    public function routePrefix(): string
    {
        $path = $this->app['config']->get('inbox.path', '_inbox');
        $path = is_string($path) && $path !== '' ? $path : '_inbox';

        return trim($path, '/');
    }
}
