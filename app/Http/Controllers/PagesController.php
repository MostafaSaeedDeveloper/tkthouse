<?php

namespace App\Http\Controllers;

use App\Models\Event;

class PagesController extends Controller
{
    public function home()
    {
        $upcomingEvents = Event::query()
            ->where('status', 'active')
            ->whereDate('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->take(6)
            ->get();

        $featuredEvents = Event::query()
            ->where('status', 'active')
            ->whereDate('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->take(3)
            ->get();

        $previousEvents = Event::query()
            ->where('status', 'active')
            ->whereDate('event_date', '<', now()->toDateString())
            ->orderByDesc('event_date')
            ->orderByDesc('event_time')
            ->take(6)
            ->get();

        return view('front.index', compact('upcomingEvents', 'featuredEvents', 'previousEvents'));
    }

    public function about()
    {
        return view('front.about');
    }

    public function events()
    {
        $events = Event::query()
            ->where('status', 'active')
            ->whereDate('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->paginate(10);

        return view('front.events.index', compact('events'));
    }

    public function eventShow(Event $event)
    {
        $event->load([
            'tickets' => fn ($query) => $query->where('status', 'active')->orderBy('price'),
            'images',
        ]);

        return view('front.events.show', compact('event'));
    }

    public function contact()
    {
        return view('front.contact');
    }

    public function checkout()
    {
        return view('front.checkout');
    }
}
