<?php

namespace SchmidtVentures\Inbox\Tests;

use Illuminate\Support\Facades\Mail;
use SchmidtVentures\Inbox\MessageStore;

class CapturesMailTest extends TestCase
{
    public function test_captured_mail_is_listed_and_html_stays_out_of_the_parent_page(): void
    {
        Mail::html('<p>Hello</p><script>alert(1)</script>', function ($message): void {
            $message->to('patient@example.test')->subject('<script>alert(1)</script>');
        });

        $message = app(MessageStore::class)->summaries()[0];

        $page = $this->get(route('inbox.show', $message->id));

        $page->assertOk();
        $page->assertSee('<script>alert(1)</script>');
        $page->assertDontSee('<script>alert(1)</script>', false);
        $page->assertSee('patient@example.test');
        $page->assertSee(route('inbox.html', $message->id), false);

        $html = $this->get(route('inbox.html', $message->id));

        $html->assertOk();
        $html->assertHeader('content-security-policy');
        $html->assertSee('<script>alert(1)</script>', false);
    }

    public function test_bcc_text_and_a_traversing_attachment_name_stay_inside_the_inbox(): void
    {
        Mail::raw('Plain copy', function ($message): void {
            $message->to('patient@example.test')
                ->cc('copy@example.test')
                ->bcc('hidden@example.test')
                ->subject('Copies')
                ->attachData('not-a-virus', '../../secret.txt', ['mime' => 'text/plain']);
        });

        $message = app(MessageStore::class)->summaries()[0];
        $page = $this->get(route('inbox.show', ['message' => $message->id, 'tab' => 'text']));

        $page->assertOk();
        $page->assertSee('hidden@example.test');
        $page->assertSee('copy@example.test');
        $page->assertSee('Plain copy');
        $this->assertFileDoesNotExist($this->storagePath.'/secret.txt');

        $download = $this->get(route('inbox.attachment', ['message' => $message->id, 'index' => 0]));

        $download->assertOk();
        $path = $download->baseResponse->getFile()->getPathname();
        $this->assertStringStartsWith(realpath($this->storagePath).DIRECTORY_SEPARATOR, $path);
        $this->assertSame('not-a-virus', file_get_contents($path));
        $this->assertStringContainsString('attachment', (string) $download->headers->get('content-disposition'));
    }

    public function test_inline_images_are_rewritten_to_the_attachment_route(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        Mail::send([], [], function ($message) use ($png): void {
            $cid = $message->embedData($png, 'pixel.png', 'image/png');
            $message->to('patient@example.test')->subject('Pixel')->html('<img alt="pixel" src="'.$cid.'">');
        });

        $message = app(MessageStore::class)->summaries()[0];
        $html = $this->get(route('inbox.html', $message->id));
        $image = $this->get(route('inbox.attachment', ['message' => $message->id, 'index' => 0]));

        $html->assertOk();
        $html->assertSee(route('inbox.attachment', ['message' => $message->id, 'index' => 0]), false);
        $html->assertDontSee('cid:', false);
        $image->assertOk();
        $this->assertStringContainsString('inline', (string) $image->headers->get('content-disposition'));
    }

    public function test_delete_requires_the_local_token_and_removes_the_message(): void
    {
        Mail::raw('Temporary', function ($message): void {
            $message->to('patient@example.test')->subject('Temporary');
        });

        $message = app(MessageStore::class)->summaries()[0];

        $this->delete(route('inbox.destroy', $message->id))->assertStatus(419);
        $this->assertNotNull(app(MessageStore::class)->find($message->id));

        $this->delete(route('inbox.destroy', $message->id), [
            'inbox_token' => app(MessageStore::class)->token(),
        ])->assertRedirect(route('inbox.index'));

        $this->assertNull(app(MessageStore::class)->find($message->id));
        $this->get(route('inbox.index'))->assertSee('No messages yet');
    }

    public function test_a_message_id_outside_the_inbox_is_not_found(): void
    {
        $this->get('/_inbox/'.str_repeat('A', 26).'/../../.env')->assertNotFound();
        $this->get('/_inbox/not-a-message')->assertNotFound();
    }
}
