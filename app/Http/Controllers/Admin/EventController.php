<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::latest()->paginate(12);

        return view('admin.events.index', compact('events'));
    }

    public function create()
    {
        return view('admin.events.create');
    }

    public function store(Request $request)
    {
        $validated = $this->normalizeEventPayload($this->validateEvent($request));

        DB::transaction(function () use ($request, $validated) {
            $coverImage = $request->file('cover_image') ? $this->storePublicImage($request->file('cover_image'), 'uploads/events') : null;
            $event = Event::create(array_merge($validated, ['cover_image' => $coverImage]));

            $this->syncTickets($event, $validated['tickets'] ?? []);
            $this->syncFees($event, $validated['fees'] ?? []);
            $this->syncImages($event, $request);

            activity('events')->performedOn($event)->causedBy(auth()->user())->log('Event created');
        });

        return redirect()->route('admin.events.index')->with('success', 'Event created successfully.');
    }

    public function show(Event $event)
    {
        $event->load(['tickets', 'fees', 'images']);

        return view('admin.events.show', compact('event'));
    }

    public function edit(Event $event)
    {
        $event->load(['tickets', 'fees', 'images']);

        return view('admin.events.edit', compact('event'));
    }

    public function update(Request $request, Event $event)
    {
        $validated = $this->normalizeEventPayload($this->validateEvent($request, $event));

        DB::transaction(function () use ($request, $validated, $event) {
            if ($request->hasFile('cover_image')) {
                $validated['cover_image'] = $this->storePublicImage($request->file('cover_image'), 'uploads/events');
            }

            $event->update($validated);
            $this->syncTickets($event, $validated['tickets'] ?? [], true);
            $this->syncFees($event, $validated['fees'] ?? [], true);

            if ($request->boolean('replace_gallery')) {
                $event->images()->delete();
            }
            $this->syncImages($event, $request);

            activity('events')->performedOn($event)->causedBy(auth()->user())->log('Event updated');
        });

        return redirect()->route('admin.events.index')->with('success', 'Event updated successfully.');
    }

    public function destroy(Event $event)
    {
        $eventName = $event->name;
        $event->delete();
        activity('events')->causedBy(auth()->user())->log('Event deleted: '.$eventName);

        return back()->with('success', 'Event deleted successfully.');
    }


    private function normalizeEventPayload(array $validated): array
    {
        $validated['description'] = $validated['description'] ?? '';
        $validated['slug'] = Str::slug((string) ($validated['slug'] ?? ''));

        if ($validated['slug'] === '') {
            $validated['slug'] = null;
        }

        return $validated;
    }

    private function validateEvent(Request $request, ?Event $event = null): array
    {
        $coverRule = ['nullable', 'image', 'max:2048'];

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('events', 'slug')->ignore($event?->id)],
            'event_date' => ['required', 'date'],
            'event_time' => ['required'],
            'location' => ['required', 'string', 'max:255'],
            'map_url' => ['nullable', 'url'],
            'description' => ['nullable', 'string'],
            'house_rules' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive,draft,sold_out'],
            'requires_booking_approval' => ['required', 'boolean'],
            'cover_image' => $coverRule,
            'gallery_images.*' => ['nullable', 'image', 'max:2048'],
            'tickets' => ['nullable', 'array'],
            'tickets.*.name' => ['required_with:tickets', 'string', 'max:255'],
            'tickets.*.price' => ['required_with:tickets', 'numeric', 'min:0'],
            'tickets.*.status' => ['required_with:tickets', 'in:active,inactive,sold_out'],
            'tickets.*.color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tickets.*.label' => ['nullable', 'string', 'max:255'],
            'tickets.*.description' => ['nullable', 'string'],
            'fees' => ['nullable', 'array'],
            'fees.*.name' => ['required_with:fees', 'string', 'max:255'],
            'fees.*.fee_type' => ['required_with:fees', 'in:percentage,fixed'],
            'fees.*.value' => ['required_with:fees', 'numeric', 'min:0'],
            'fees.*.description' => ['nullable', 'string'],
        ]);
    }

    private function syncTickets(Event $event, array $tickets, bool $replace = false): void
    {
        if ($replace) {
            $event->tickets()->delete();
        }

        foreach ($tickets as $ticket) {
            if (! empty($ticket['name'])) {
                $ticket['color'] = $ticket['color'] ?? '#0d6efd';
                $event->tickets()->create($ticket);
            }
        }
    }

    private function syncFees(Event $event, array $fees, bool $replace = false): void
    {
        if ($replace) {
            $event->fees()->delete();
        }

        foreach ($fees as $fee) {
            if (! empty($fee['name'])) {
                $event->fees()->create($fee);
            }
        }
    }

    private function syncImages(Event $event, Request $request): void
    {
        if (! $request->hasFile('gallery_images')) {
            return;
        }

        foreach ($request->file('gallery_images') as $image) {
            $event->images()->create(['path' => $this->storePublicImage($image, 'uploads/events/gallery')]);
        }
    }

    private function storePublicImage(UploadedFile $file, string $directory): string
    {
        $targetDirectory = public_path($directory);
        File::ensureDirectoryExists($targetDirectory);

        $filename = $file->hashName();
        $file->move($targetDirectory, $filename);

        return trim($directory, '/').'/'.$filename;
    }
}
