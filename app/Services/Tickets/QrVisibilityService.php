<?php

namespace App\Services\Tickets;

use App\Models\Event;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Changes when customers may see (and the gate accepts) the QR codes of an event's digital tickets. Every change is
 * recorded with who made it and when; nothing here touches the tickets themselves, so a change shows on customers' pages
 * at once (or on their next refresh) without re-issuing anything.
 */
class QrVisibilityService
{
    public const MAX_LEAD_MINUTES = 60 * 24 * 60; // 60 days

    private const UNIT_MINUTES = ['minutes' => 1, 'hours' => 60, 'days' => 1440];

    /** Saves the mode (and, for "scheduled", how long before the event the QR is revealed). Keeps any manual override in place. */
    public function configure(Event $event, ?User $by, string $mode, ?float $amount = null, string $unit = 'hours', bool $clearOverride = false): Event
    {
        if (! in_array($mode, Event::QR_MODES, true)) {
            throw ValidationException::withMessages(['qr_visibility_mode' => 'Choose when the QR code is shown.']);
        }

        $minutes = null;
        if ($mode === Event::QR_SCHEDULED) {
            $minutes = (int) round(((float) $amount) * (self::UNIT_MINUTES[$unit] ?? 60));
            if ($minutes < 1 || $minutes > self::MAX_LEAD_MINUTES) {
                throw ValidationException::withMessages(['qr_reveal_amount' => 'Enter how long before the event the QR is shown (up to 60 days).']);
            }
        }

        $before = $this->snapshot($event);
        $event->forceFill([
            'qr_visibility_mode' => $mode,
            'qr_reveal_minutes_before' => $minutes,
            'qr_visibility_changed_by' => $by?->id,
            'qr_visibility_changed_at' => now(),
        ] + ($clearOverride ? ['qr_override' => null] : []))->save();

        $this->log($event, $by, 'Ticket QR visibility settings changed', $before);
        app(QrReleaseService::class)->onVisibilityChanged($event);   // marks + queues only (live mode); sends nothing here

        return $event;
    }

    /** Show / hide the QR of every ticket of the event right now ('show' | 'hide'), or null to go back to the automatic rule. */
    public function override(Event $event, ?User $by, ?string $override): Event
    {
        if ($override !== null && ! in_array($override, ['show', 'hide'], true)) {
            throw ValidationException::withMessages(['override' => 'Unknown action.']);
        }

        $before = $this->snapshot($event);
        $event->forceFill([
            'qr_override' => $override,
            'qr_visibility_changed_by' => $by?->id,
            'qr_visibility_changed_at' => now(),
        ])->save();

        $this->log($event, $by, match ($override) {
            'show' => 'Ticket QR codes shown manually',
            'hide' => 'Ticket QR codes hidden manually',
            default => 'Ticket QR manual override cleared (automatic rule applies)',
        }, $before);
        app(QrReleaseService::class)->onVisibilityChanged($event);   // Show queues the release e-mails (live mode); Hide queues nothing

        return $event;
    }

    private function snapshot(Event $event): array
    {
        return ['mode' => $event->qr_visibility_mode, 'minutes_before' => $event->qr_reveal_minutes_before, 'override' => $event->qr_override];
    }

    private function log(Event $event, ?User $by, string $message, array $before): void
    {
        activity('events')->performedOn($event)->causedBy($by)->withProperties([
            'qr_visibility' => ['before' => $before, 'after' => $this->snapshot($event)],
        ])->log($message);
    }
}
