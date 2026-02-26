<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventRequest;
use App\Models\Event;
use App\Models\FeesPolicy;
use App\Models\TicketTemplate;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        return view('admin.events.index', [
            'events' => Event::with(['venue', 'ticketTemplate'])->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.events.create', [
            'venues' => Venue::query()->orderBy('name')->get(),
            'templates' => TicketTemplate::query()->where('is_active', true)->orderBy('name')->get(),
            'policies' => FeesPolicy::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        Event::create($request->validated() + [
            'slug' => Str::slug($request->string('title')).'-'.Str::lower(Str::random(6)),
        ]);

        return redirect()->route('admin.events.index')->with('status', 'Event created.');
    }

    public function edit(Event $event): View
    {
        return view('admin.events.edit', [
            'event' => $event,
            'venues' => Venue::query()->orderBy('name')->get(),
            'templates' => TicketTemplate::query()->where('is_active', true)->orderBy('name')->get(),
            'policies' => FeesPolicy::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(StoreEventRequest $request, Event $event): RedirectResponse
    {
        $event->update($request->validated());

        return back()->with('status', 'Event updated.');
    }
}
