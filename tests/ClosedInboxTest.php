<?php

namespace SchmidtVentures\Inbox\Tests;

use Illuminate\Support\Facades\Mail;
use SchmidtVentures\Inbox\InboxDisabledException;
use SchmidtVentures\Inbox\MessageStore;

class ClosedInboxTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('inbox.enabled', false);
    }

    public function test_sending_is_rejected_and_the_inbox_route_is_absent(): void
    {
        $this->get('/_inbox')->assertNotFound();

        try {
            Mail::raw('Do not store', function ($message): void {
                $message->to('patient@example.test')->subject('Nope');
            });
            $this->fail('The inbox mailer accepted a message while capture was off.');
        } catch (InboxDisabledException) {
            $this->assertSame([], app(MessageStore::class)->summaries());
        }
    }
}
