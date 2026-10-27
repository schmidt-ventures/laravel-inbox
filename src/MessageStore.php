<?php

namespace SchmidtVentures\Inbox;

use Illuminate\Support\Str;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

class MessageStore
{
    public const ID = '[0-9A-HJKMNP-TV-Z]{26}';

    public function __construct(private string $root)
    {
        //
    }

    public function store(SentMessage $sent): StoredMessage
    {
        $this->ensureRoot();

        $email = MessageConverter::toEmail($sent->getOriginalMessage());
        $id = (string) Str::ulid();
        $directory = $this->root.DIRECTORY_SEPARATOR.$id;

        if (! mkdir($directory, 0700) && ! is_dir($directory)) {
            throw new \RuntimeException('Unable to create a directory for the captured message.');
        }

        $attachments = $this->writeAttachments($directory, $email->getAttachments());
        $html = $this->stringBody($email->getHtmlBody());
        $text = $this->stringBody($email->getTextBody());
        $hasHtml = $html !== null && $html !== '';
        $hasText = $text !== null && $text !== '';

        $summary = [
            'id' => $id,
            'created_at' => now()->toIso8601String(),
            'from' => $this->addresses($email->getFrom()),
            'to' => $this->addresses($email->getTo()),
            'cc' => $this->addresses($email->getCc()),
            'bcc' => $this->bcc($email->getBcc(), $sent),
            'reply_to' => $this->addresses($email->getReplyTo()),
            'subject' => (string) $email->getSubject(),
            'has_html' => $hasHtml,
            'has_text' => $hasText,
            'attachments' => $attachments,
            'headers' => $this->headers($email),
        ];

        $this->write($directory.DIRECTORY_SEPARATOR.'message.json', json_encode(
            $summary,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        if ($hasHtml) {
            $this->write($directory.DIRECTORY_SEPARATOR.'body.html', $html);
        }

        if ($hasText) {
            $this->write($directory.DIRECTORY_SEPARATOR.'body.txt', $text);
        }

        $this->write($directory.DIRECTORY_SEPARATOR.'raw.eml', $sent->getOriginalMessage()->toString());

        return $this->find($id) ?? throw new \RuntimeException('The captured message could not be read back.');
    }

    /**
     * @return list<StoredMessage>
     */
    public function summaries(): array
    {
        if (! is_dir($this->root)) {
            return [];
        }

        $messages = [];

        foreach (scandir($this->root) ?: [] as $entry) {
            if (in_array($entry, ['.', '..'], true) || ! $this->validId($entry)) {
                continue;
            }

            $message = $this->read($this->root.DIRECTORY_SEPARATOR.$entry, false);

            if ($message instanceof StoredMessage) {
                $messages[] = $message;
            }
        }

        usort($messages, function (StoredMessage $left, StoredMessage $right): int {
            return [$right->createdAt, $right->id] <=> [$left->createdAt, $left->id];
        });

        return $messages;
    }

    public function find(string $id): ?StoredMessage
    {
        $directory = $this->directory($id);

        if ($directory === null) {
            return null;
        }

        return $this->read($directory, true);
    }

    public function delete(string $id): void
    {
        $directory = $this->directory($id);

        if ($directory === null) {
            return;
        }

        $this->deleteDirectory($directory);
    }

    public function deleteAll(): void
    {
        foreach ($this->summaries() as $message) {
            $this->delete($message->id);
        }
    }

    public function attachmentPath(StoredMessage $message, int $index): ?string
    {
        $attachment = $message->attachments[$index] ?? null;
        $directory = $this->directory($message->id);

        if ($attachment === null || $directory === null) {
            return null;
        }

        $file = $attachment['file'];

        if ($file !== basename($file)) {
            return null;
        }

        $path = $directory.DIRECTORY_SEPARATOR.'attachments'.DIRECTORY_SEPARATOR.$file;
        $attachmentsRoot = realpath($directory.DIRECTORY_SEPARATOR.'attachments');
        $real = realpath($path);

        if ($attachmentsRoot === false || $real === false || ! str_starts_with($real, $attachmentsRoot.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $real;
    }

    public function token(): string
    {
        $this->ensureRoot();
        $path = $this->root.DIRECTORY_SEPARATOR.'.token';

        if (! is_file($path)) {
            $this->write($path, bin2hex(random_bytes(32)));
        }

        $token = file_get_contents($path);

        if ($token === false || trim($token) === '') {
            throw new \RuntimeException('Unable to read the inbox token.');
        }

        return trim($token);
    }

    public function tokenMatches(?string $token): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($this->token(), $token);
    }

    /**
     * @param  callable(int): string  $urlForIndex
     */
    public function previewHtml(StoredMessage $message, callable $urlForIndex): string
    {
        $html = $message->html ?? '';

        if ($html === '') {
            return '<!DOCTYPE html><meta charset="utf-8"><p>This message has no HTML part.</p>';
        }

        foreach ($message->attachments as $index => $attachment) {
            $contentId = $attachment['content_id'];

            if ($contentId === null || $contentId === '') {
                continue;
            }

            $url = $urlForIndex($index);
            $html = str_replace(
                ['cid:'.$contentId, 'cid:'.rawurlencode($contentId)],
                $url,
                $html,
            );
        }

        return $html;
    }

    private function read(string $directory, bool $withBodies): ?StoredMessage
    {
        $payload = $this->readJson($directory.DIRECTORY_SEPARATOR.'message.json');

        if ($payload === null) {
            return null;
        }

        $html = null;
        $text = null;
        $raw = null;

        if ($withBodies) {
            $html = $this->readOptional($directory.DIRECTORY_SEPARATOR.'body.html');
            $text = $this->readOptional($directory.DIRECTORY_SEPARATOR.'body.txt');
            $raw = $this->readOptional($directory.DIRECTORY_SEPARATOR.'raw.eml');
        }

        return new StoredMessage(
            id: $payload['id'],
            createdAt: $payload['created_at'],
            from: $payload['from'],
            to: $payload['to'],
            cc: $payload['cc'],
            bcc: $payload['bcc'],
            replyTo: $payload['reply_to'],
            subject: $payload['subject'],
            hasHtml: $payload['has_html'],
            hasText: $payload['has_text'],
            attachments: $payload['attachments'],
            html: $html,
            text: $text,
            raw: $raw,
            headers: $payload['headers'],
        );
    }

    /**
     * @return array{id: string, created_at: string, from: list<array{address: string, name: string}>, to: list<array{address: string, name: string}>, cc: list<array{address: string, name: string}>, bcc: list<array{address: string, name: string}>, reply_to: list<array{address: string, name: string}>, subject: string, has_html: bool, has_text: bool, attachments: list<array{name: string, content_type: string, size: int, disposition: string, content_id: ?string, file: string}>, headers: list<string>}|null
     */
    private function readJson(string $path): ?array
    {
        $contents = $this->readOptional($path);

        if ($contents === null) {
            return null;
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($payload) || ! isset($payload['id']) || ! $this->validId((string) $payload['id'])) {
            return null;
        }

        return $payload;
    }

    /**
     * @param  array<int, DataPart>  $parts
     * @return list<array{name: string, content_type: string, size: int, disposition: string, content_id: ?string, file: string}>
     */
    private function writeAttachments(string $directory, array $parts): array
    {
        if ($parts === []) {
            return [];
        }

        $attachmentsDirectory = $directory.DIRECTORY_SEPARATOR.'attachments';

        if (! mkdir($attachmentsDirectory, 0700) && ! is_dir($attachmentsDirectory)) {
            throw new \RuntimeException('Unable to create the attachment directory.');
        }

        $stored = [];

        foreach (array_values($parts) as $index => $part) {
            $name = $this->downloadName($part->getFilename());
            $file = $index.'-'.$name;
            $body = $part->getBody();
            $this->write($attachmentsDirectory.DIRECTORY_SEPARATOR.$file, $body);

            $stored[] = [
                'name' => $name,
                'content_type' => $this->contentType($part->getContentType()),
                'size' => strlen($body),
                'disposition' => $part->getDisposition() ?: 'attachment',
                'content_id' => $part->hasContentId() ? trim($part->getContentId(), "<> \t") : null,
                'file' => $file,
            ];
        }

        return $stored;
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return list<array{address: string, name: string}>
     */
    private function addresses(array $addresses): array
    {
        return array_map(fn (Address $address): array => [
            'address' => $address->getAddress(),
            'name' => $address->getName(),
        ], $addresses);
    }

    /**
     * @param  array<int, Address>  $headerBcc
     * @return list<array{address: string, name: string}>
     */
    private function bcc(array $headerBcc, SentMessage $sent): array
    {
        $email = MessageConverter::toEmail($sent->getOriginalMessage());
        $visible = [];

        foreach ([...$email->getTo(), ...$email->getCc(), ...$headerBcc] as $address) {
            $visible[strtolower($address->getAddress())] = true;
        }

        $bcc = $this->addresses($headerBcc);

        foreach ($sent->getEnvelope()->getRecipients() as $address) {
            $key = strtolower($address->getAddress());

            if (isset($visible[$key])) {
                continue;
            }

            $visible[$key] = true;
            $bcc[] = [
                'address' => $address->getAddress(),
                'name' => $address->getName(),
            ];
        }

        return $bcc;
    }

    /**
     * @return list<string>
     */
    private function headers(Email $email): array
    {
        $headers = [];

        foreach ($email->getHeaders()->all() as $header) {
            $line = trim($header->toString());

            if ($line === '' || strlen($line) > 4000) {
                continue;
            }

            $headers[] = $line;

            if (count($headers) >= 80) {
                break;
            }
        }

        return $headers;
    }

    private function stringBody(mixed $body): ?string
    {
        if ($body === null) {
            return null;
        }

        if (is_resource($body)) {
            $contents = stream_get_contents($body);

            return $contents === false ? null : $contents;
        }

        return (string) $body;
    }

    private function downloadName(?string $filename): string
    {
        $base = $filename === null || $filename === '' ? 'attachment' : basename(str_replace('\\', '/', $filename));
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base) ?: 'attachment';
        $base = trim($base, '._');

        return $base !== '' ? $base : 'attachment';
    }

    private function contentType(string $contentType): string
    {
        if (preg_match('~\A[A-Za-z0-9!#$&^_.+-]+/[A-Za-z0-9!#$&^_.+-]+\z~', $contentType) !== 1) {
            return 'application/octet-stream';
        }

        return strtolower($contentType);
    }

    private function directory(string $id): ?string
    {
        if (! $this->validId($id) || ! is_dir($this->root)) {
            return null;
        }

        $root = realpath($this->root);
        $path = realpath($this->root.DIRECTORY_SEPARATOR.$id);

        if ($root === false || $path === false || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $path;
    }

    private function validId(string $id): bool
    {
        return preg_match('/\A'.self::ID.'\z/', $id) === 1;
    }

    private function ensureRoot(): void
    {
        if (is_dir($this->root)) {
            return;
        }

        if (! mkdir($this->root, 0700, true) && ! is_dir($this->root)) {
            throw new \RuntimeException('Unable to create the inbox directory.');
        }
    }

    private function write(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException('Unable to write a captured mail file.');
        }

        chmod($path, 0600);
    }

    private function readOptional(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    private function deleteDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
