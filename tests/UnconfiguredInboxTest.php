<?php

namespace SchmidtVentures\Inbox\Tests;

use Illuminate\Support\Facades\Mail;
use SchmidtVentures\Inbox\InboxDisabledException;

class UnconfiguredInboxTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('inbox.enabled', null);
    }

    public function test_capture_stays_off_when_the_app_is_not_local(): void
    {
        $this->get('/_inbox')->assertNotFound();

        $this->expectException(InboxDisabledException::class);

        Mail::raw('Do not store', function ($message): void {
            $message->to('patient@example.test')->subject('Nope');
        });
    }
}
