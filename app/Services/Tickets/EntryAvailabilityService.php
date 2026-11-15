<?php

namespace App\Services\Tickets;

use App\Models\Event;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Entry Availability: when the scanner lets ticket holders in. Independent of QR visibility; every change records who and when. */
class EntryAvailabilityService
{
    private const UNIT_MINUTES = ['minutes' => 1, 'hours' => 60, 'days' => 1440];

    public function configure(Event $event, ?User $by, string $mode, ?float $amount = null, string $unit = 'hours'): Event
    {
        if (! in_array($mode, Event::ENTRY_MODES, true)) {
            throw ValidationException::withMessages(['entry_mode' => 'Choose when entry opens.']);
        }

        $minutes = null;
        if ($mode === Event::ENTRY_SCHEDULED) {
            $minutes = (int) round(((float) $amount) * (self::UNIT_MINUTES[$unit] ?? 60));
            if ($amount === null || $minutes < 1 || $minutes > QrVisibilityService::MAX_LEAD_MINUTES) {
                throw ValidationException::withMessages(['entry_opens_amount' => 'Enter how long before the event the gates open (up to 60 days).']);
            }
        }

        $before = $this->snapshot($event);
        $event->forceFill(['entry_mode' => $mode, 'entry_opens_minutes_before' => $minutes, 'entry_changed_by' => $by?->id, 'entry_changed_at' => now()])->save();
        $this->log($event, $by, 'Entry availability settings changed', $before);

        return $event;
    }

    /** 'open' | 'close' right now, or null to return to the automatic rule. */
    public function override(Event $event, ?User $by, ?string $override): Event
    {
        if ($override !== null && ! in_array($override, ['open', 'close'], true)) {
            throw ValidationException::withMessages(['override' => 'Unknown action.']);
        }

        $before = $this->snapshot($event);
        $event->forceFill(['entry_override' => $override, 'entry_changed_by' => $by?->id, 'entry_changed_at' => now()])->save();
        $this->log($event, $by, match ($override) {
            'open' => 'Entry opened manually',
            'close' => 'Entry closed manually',
            default => 'Entry manual override cleared (automatic rule applies)',
        }, $before);

        return $event;
    }

    private function snapshot(Event $event): array
    {
        return ['mode' => $event->entry_mode, 'minutes_before' => $event->entry_opens_minutes_before, 'override' => $event->entry_override];
    }

    private function log(Event $event, ?User $by, string $message, array $before): void
    {
        activity('events')->performedOn($event)->causedBy($by)->withProperties(['entry' => ['before' => $before, 'after' => $this->snapshot($event)]])->log($message);
    }
}
