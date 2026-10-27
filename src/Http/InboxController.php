<?php

namespace SchmidtVentures\Inbox\Http;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use SchmidtVentures\Inbox\MessageStore;
use SchmidtVentures\Inbox\StoredMessage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class InboxController
{
    public function __construct(private MessageStore $messages)
    {
        //
    }

    public function index(Request $request): View
    {
        return $this->browse($request, null);
    }

    public function show(Request $request, string $message): View
    {
        return $this->browse($request, $this->messages->find($message) ?? abort(404));
    }

    public function html(string $message): Response
    {
        $stored = $this->messages->find($message) ?? abort(404);

        return response($this->messages->previewHtml($stored, function (int $index) use ($stored): string {
            return route('inbox.attachment', ['message' => $stored->id, 'index' => $index]);
        }), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "default-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'; img-src data: blob: https: http:; style-src 'unsafe-inline' data:; font-src data:; media-src data: blob: https: http:",
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function attachment(string $message, int $index): BinaryFileResponse
    {
        $stored = $this->messages->find($message) ?? abort(404);
        $attachment = $stored->attachments[$index] ?? abort(404);
        $path = $this->messages->attachmentPath($stored, $index) ?? abort(404);
        $inline = $attachment['disposition'] === 'inline' && in_array($attachment['content_type'], [
            'image/gif',
            'image/jpeg',
            'image/png',
            'image/webp',
        ], true);

        return response()->file($path, [
            'Content-Type' => $attachment['content_type'],
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
                $attachment['name'],
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(string $message): RedirectResponse
    {
        $this->messages->delete($message);

        return redirect()->route('inbox.index');
    }

    public function destroyAll(): RedirectResponse
    {
        $this->messages->deleteAll();

        return redirect()->route('inbox.index');
    }

    private function browse(Request $request, ?StoredMessage $current): View
    {
        return view('inbox::browse', [
            'messages' => $this->messages->summaries(),
            'current' => $current,
            'token' => $this->messages->token(),
            'tab' => $this->tab($request, $current),
        ]);
    }

    private function tab(Request $request, ?StoredMessage $current): string
    {
        $tab = $request->query('tab');

        if (is_string($tab) && in_array($tab, ['html', 'text', 'source'], true)) {
            return $tab;
        }

        if ($current?->hasHtml) {
            return 'html';
        }

        if ($current?->hasText) {
            return 'text';
        }

        return 'source';
    }
}
