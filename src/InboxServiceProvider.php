<?php

namespace SchmidtVentures\Inbox;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class InboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/inbox.php', 'inbox');

        $this->app->singleton(Inbox::class);

        $this->app->singleton(MessageStore::class, function ($app): MessageStore {
            return new MessageStore($app->make(Inbox::class)->storagePath());
        });
    }

    public function boot(): void
    {
        config([
            'mail.mailers.inbox' => array_merge(
                ['transport' => 'inbox'],
                config('mail.mailers.inbox', []),
            ),
        ]);

        Mail::extend('inbox', function (): InboxTransport {
            return new InboxTransport(
                $this->app->make(MessageStore::class),
                $this->app->make(Inbox::class),
            );
        });

        if (! $this->app->make(Inbox::class)->captures()) {
            return;
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'inbox');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }
}
