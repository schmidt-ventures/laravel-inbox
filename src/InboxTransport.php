<?php

namespace SchmidtVentures\Inbox;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class InboxTransport extends AbstractTransport
{
    public function __construct(
        private MessageStore $messages,
        private Inbox $inbox,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if (! $this->inbox->captures()) {
            throw new InboxDisabledException(
                'The inbox mailer only captures mail for local development. Leave MAIL_MAILER unset in other environments, or set INBOX_ENABLED=true for an explicit local override.',
            );
        }

        $this->messages->store($message);
    }

    public function __toString(): string
    {
        return 'inbox';
    }
}
