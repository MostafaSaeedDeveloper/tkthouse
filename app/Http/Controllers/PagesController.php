<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PagesController extends Controller
{
    public function home()
    {
        $upcomingEvents = Event::query()
            ->whereIn('status', ['active', 'sold_out'])
            ->whereDate('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->take(6)
            ->get();

        $featuredEvents = Event::query()
            ->whereIn('status', ['active', 'sold_out'])
            ->whereDate('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->take(3)
            ->get();

        $previousEvents = Event::query()
            ->whereIn('status', ['active', 'sold_out'])
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
        $filters = request()->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'when' => ['nullable', 'in:all,upcoming,previous'],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $when = $filters['when'] ?? 'all';

        $applySearch = function ($query) use ($search) {
            if ($search === '') {
                return;
            }

            $lowerSearch = mb_strtolower($search);
            $startsWith = $lowerSearch.'%';
            $contains = '%'.$lowerSearch.'%';

            $query->where(function ($subQuery) use ($contains) {
                $subQuery
                    ->whereRaw('LOWER(name) LIKE ?', [$contains])
                    ->orWhereRaw('LOWER(location) LIKE ?', [$contains])
                    ->orWhereRaw('LOWER(venue) LIKE ?', [$contains]);
            })->orderByRaw(
                "CASE
                    WHEN LOWER(name) LIKE ? THEN 0
                    WHEN LOWER(name) LIKE ? THEN 1
                    WHEN LOWER(location) LIKE ? THEN 2
                    WHEN LOWER(venue) LIKE ? THEN 3
                    ELSE 4
                END",
                [$startsWith, $contains, $contains, $contains]
            );
        };

        $events = Event::query()
            ->whereIn('status', ['active', 'sold_out'])
            ->whereDate('event_date', '>=', now()->toDateString())
            ->when($when !== 'previous', $applySearch)
            ->when($when === 'previous', fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->paginate(10)
            ->withQueryString();

        $previousEvents = Event::query()
            ->whereIn('status', ['active', 'sold_out'])
            ->whereDate('event_date', '<', now()->toDateString())
            ->when($when !== 'upcoming', $applySearch)
            ->when($when === 'upcoming', fn ($query) => $query->whereRaw('1 = 0'))
            ->orderByDesc('event_date')
            ->orderByDesc('event_time')
            ->take(10)
            ->get();

        return view('front.events.index', compact('events', 'previousEvents', 'search', 'when'));
    }

    public function eventShow(Event $event)
    {
        $event->load([
            'tickets' => fn ($query) => $query->whereIn('status', ['active', 'sold_out'])->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->orderBy('price'),
            'images',
        ]);

        return view('front.events.show', compact('event'));
    }

    public function contact()
    {
        return view('front.contact');
    }

    public function submitContact(Request $request)
    {
        $data = $request->validate([
            'author' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:180'],
            'subject' => ['nullable', 'string', 'max:180'],
            'comment' => ['required', 'string', 'max:3000'],
        ]);

        $subject = $data['subject'] ?: 'Website Contact Form Message';

        Mail::raw(
            "New contact message from tkthouse.com\n\n"
            . "Name: {$data['author']}\n"
            . "Phone: {$data['phone']}\n"
            . "Email: {$data['email']}\n"
            . "Subject: {$subject}\n\n"
            . "Message:\n{$data['comment']}\n",
            function ($message) use ($data, $subject) {
                $message->to('support@tkthouse.com')
                    ->replyTo($data['email'], $data['author'])
                    ->subject('[TKT House Contact] '.$subject);
            }
        );

        return redirect()->route('front.contact')->with('success', 'Your message has been sent successfully.');
    }

    public function terms()
    {
        return view('front.terms');
    }

    public function privacy()
    {
        return view('front.privacy');
    }

    public function cookie()
    {
        return view('front.cookie');
    }

    public function checkout()
    {
        return view('front.checkout');
    }
}
