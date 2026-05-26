<?php

namespace App\Listeners;

use App\Models\NotificationLog;
use Illuminate\Mail\Events\MessageSent;

class CaptureMailMessageId
{
    public function handle(MessageSent $event): void
    {
        $original = $event->message->getOriginalMessage();

        $rawId = $original->getHeaders()->get('Message-ID')?->getBodyAsString();
        if (! $rawId) {
            return;
        }
        $messageId = trim($rawId, '<>');

        $to = $original->getTo();
        if (empty($to)) {
            return;
        }
        $recipient = $to[0]->getAddress();

        NotificationLog::query()
            ->where('channel', 'email')
            ->where('recipient', $recipient)
            ->whereNull('message_id')
            ->where('status', 'sent')
            ->where('created_at', '>=', now()->subSeconds(60))
            ->latest()
            ->first()
            ?->update(['message_id' => $messageId]);
    }
}
