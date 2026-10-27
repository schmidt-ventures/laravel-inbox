<?php

namespace SchmidtVentures\Inbox;

use Illuminate\Support\Carbon;

class StoredMessage
{
    /**
     * @param  list<array{address: string, name: string}>  $from
     * @param  list<array{address: string, name: string}>  $to
     * @param  list<array{address: string, name: string}>  $cc
     * @param  list<array{address: string, name: string}>  $bcc
     * @param  list<array{address: string, name: string}>  $replyTo
     * @param  list<array{name: string, content_type: string, size: int, disposition: string, content_id: ?string, file: string}>  $attachments
     * @param  list<string>  $headers
     */
    public function __construct(
        public string $id,
        public string $createdAt,
        public array $from,
        public array $to,
        public array $cc,
        public array $bcc,
        public array $replyTo,
        public string $subject,
        public bool $hasHtml,
        public bool $hasText,
        public array $attachments,
        public ?string $html = null,
        public ?string $text = null,
        public ?string $raw = null,
        public array $headers = [],
    ) {
        //
    }

    public function subjectLine(): string
    {
        return $this->subject !== '' ? $this->subject : '(no subject)';
    }

    public function recipientLine(): string
    {
        return $this->addressLine($this->to) ?: 'No recipient';
    }

    public function sentAtLabel(): string
    {
        return Carbon::parse($this->createdAt)->format('M j, g:i A');
    }

    /**
     * @param  list<array{address: string, name: string}>  $addresses
     */
    public function addressLine(array $addresses): string
    {
        return implode(', ', array_map(function (array $address): string {
            if ($address['name'] !== '') {
                return $address['name'].' <'.$address['address'].'>';
            }

            return $address['address'];
        }, $addresses));
    }

    /**
     * @param  array{name: string, content_type: string, size: int, disposition: string, content_id: ?string, file: string}  $attachment
     */
    public function attachmentLabel(array $attachment): string
    {
        $size = $attachment['size'] < 1024
            ? $attachment['size'].' B'
            : number_format($attachment['size'] / 1024, 1).' KB';

        return $attachment['name'].' ('.$size.')';
    }
}
