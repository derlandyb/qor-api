<?php

namespace QOR\App\Infrastructure\Notification;

use Illuminate\Mail\Mailable;

class NotificationMailable extends Mailable
{
    public function __construct(
        private readonly string $subjectLine,
        private readonly string $bodyText,
    ) {
    }

    public function build(): self
    {
        return $this->subject($this->subjectLine)->html(e($this->bodyText));
    }
}
