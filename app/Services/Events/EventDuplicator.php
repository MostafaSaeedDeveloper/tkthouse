<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Copies the SETUP of an event (details, ticket types, fees, line-up, gallery) into a new draft event.
 *
 * Nothing that belongs to the original's history is copied: no orders, issued tickets, scans, promo codes or payments. The copy always
 * starts as a draft (off sale, hidden from the public site) with a new slug, so it can never sell by accident before it has been reviewed.
 * Uploaded images are referenced, not copied: the application never deletes event image files, so both events can safely point at them.
 */
final class EventDuplicator
{
    public function duplicate(Event $source, ?User $by = null): Event
    {
        return DB::transaction(function () use ($source, $by) {
            $source->load(['tickets', 'fees', 'images', 'lineups']);

            $copy = Event::create(['name' => $this->copyName($source->name), 'slug' => null, 'status' => 'draft'] + $source->only($source->getFillable()));

            foreach ($source->tickets as $i => $ticket) {
                $copy->tickets()->create(collect($ticket->only($ticket->getFillable()))->except('event_id')->all() + ['sort_order' => $ticket->sort_order ?? $i]);
            }
            foreach ($source->fees as $fee) {
                $copy->fees()->create(collect($fee->only($fee->getFillable()))->except('event_id')->all());
            }
            foreach ($source->lineups as $lineup) {
                $copy->lineups()->create(collect($lineup->only($lineup->getFillable()))->except('event_id')->all());
            }
            foreach ($source->images as $image) {
                $copy->images()->create(['path' => $image->path]);
            }

            activity('events')->performedOn($copy)->causedBy($by)->withProperties(['duplicated_from' => $source->id])->log('Event duplicated from "'.$source->name.'"');

            return $copy->fresh();
        });
    }

    private function copyName(string $name): string
    {
        $base = rtrim(preg_replace('/\s*\(Copy(?: \d+)?\)$/', '', $name));
        $candidate = $base.' (Copy)';
        $n = 2;
        while (Event::query()->where('name', $candidate)->exists()) {
            $candidate = $base.' (Copy '.$n++.')';
        }

        return mb_substr($candidate, 0, 255);
    }
}
