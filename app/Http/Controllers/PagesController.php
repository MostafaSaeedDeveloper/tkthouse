<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class PagesController extends Controller
{
    public function home(Request $request)
    {
        $filters = $request->validate([
            'event_name' => ['nullable', 'string', 'max:120'],
            'event_location' => ['nullable', 'string', 'max:120'],
            'event_date' => ['nullable', 'date'],
        ]);

        $eventName = trim((string) ($filters['event_name'] ?? ''));
        $eventLocation = trim((string) ($filters['event_location'] ?? ''));
        $eventDate = $filters['event_date'] ?? null;
        $hasHomeFilters = $eventName !== '' || $eventLocation !== '' || ! empty($eventDate);

        $applyHomeSearch = function ($query) use ($eventName, $eventLocation, $eventDate) {
            $query
                ->when($eventName !== '', function ($subQuery) use ($eventName) {
                    $subQuery->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($eventName).'%']);
                })
                ->when($eventLocation !== '', function ($subQuery) use ($eventLocation) {
                    $value = '%'.mb_strtolower($eventLocation).'%';
                    $subQuery->whereRaw('LOWER(location) LIKE ?', [$value]);
                })
                ->when($eventDate, function ($subQuery) use ($eventDate) {
                    $subQuery->whereDate('event_date', $eventDate);
                });
        };

        $upcomingEvents = Event::query()
            ->whereIn('status', ['active', 'sold_out'])
            ->whereDate('event_date', '>=', now()->toDateString())
            ->tap($applyHomeSearch)
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
            ->tap($applyHomeSearch)
            ->orderByDesc('event_date')
            ->orderByDesc('event_time')
            ->take(6)
            ->get();

        $locations = Event::query()
            ->whereIn('status', ['active', 'sold_out'])
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->distinct()
            ->orderBy('location')
            ->pluck('location');

        return view('front.index', compact(
            'upcomingEvents',
            'featuredEvents',
            'previousEvents',
            'locations',
            'eventName',
            'eventLocation',
            'eventDate',
            'hasHomeFilters',
        ));
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
                    ->orWhereRaw('LOWER(location) LIKE ?', [$contains]);
            })->orderByRaw(
                "CASE
                    WHEN LOWER(name) LIKE ? THEN 0
                    WHEN LOWER(name) LIKE ? THEN 1
                    WHEN LOWER(location) LIKE ? THEN 2
                    ELSE 3
                END",
                [$startsWith, $contains, $contains]
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

        $mapEmbedUrl  = $this->resolveMapEmbedUrl($event->map_url, $event->location);
        $mapDirectUrl = $this->resolveMapDirectUrl($event->map_url, $event->location);

        return view('front.events.show', compact('event', 'mapEmbedUrl', 'mapDirectUrl'));
    }

    private function resolveMapEmbedUrl(?string $mapUrl, ?string $fallbackLocation): ?string
    {
        $fallback = $fallbackLocation
            ? 'https://www.google.com/maps?q=' . urlencode($fallbackLocation) . '&output=embed'
            : null;

        if (empty($mapUrl)) {
            return $fallback;
        }

        $rawMapUrl = trim($mapUrl);

        if (! filter_var($rawMapUrl, FILTER_VALIDATE_URL)) {
            return 'https://www.google.com/maps?q=' . urlencode($rawMapUrl) . '&output=embed';
        }

        $parsed  = parse_url($rawMapUrl);
        $host    = strtolower($parsed['host'] ?? '');
        $path    = $parsed['path'] ?? '';
        parse_str($parsed['query'] ?? '', $query);

        // Resolve Google short links to the full URL first.
        if (in_array($host, ['maps.app.goo.gl', 'goo.gl'], true)) {
            try {
                $response    = Http::timeout(6)->get($rawMapUrl);
                $resolvedUrl = (string) $response->effectiveUri();

                if ($resolvedUrl !== '' && $resolvedUrl !== $rawMapUrl) {
                    $rawMapUrl = $resolvedUrl;
                    $parsed    = parse_url($rawMapUrl);
                    $host      = strtolower($parsed['host'] ?? '');
                    $path      = $parsed['path'] ?? '';
                    parse_str($parsed['query'] ?? '', $query);
                } else {
                    // Could not resolve — fall back to location text.
                    return $fallback;
                }
            } catch (\Throwable) {
                return $fallback;
            }
        }

        if (str_contains($path, '/maps/embed')) {
            return $rawMapUrl;
        }

        if (isset($query['q']) && $query['q'] !== '') {
            return 'https://www.google.com/maps?q=' . urlencode($query['q']) . '&output=embed';
        }

        if (isset($query['query']) && $query['query'] !== '') {
            return 'https://www.google.com/maps?q=' . urlencode($query['query']) . '&output=embed';
        }

        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $rawMapUrl, $coords)) {
            return 'https://www.google.com/maps?q=' . urlencode($coords[1] . ',' . $coords[2]) . '&output=embed';
        }

        if (str_contains($path, '/place/')) {
            $place = trim(urldecode(substr($path, strpos($path, '/place/') + 7)), '/');
            return 'https://www.google.com/maps?q=' . urlencode(str_replace('+', ' ', $place)) . '&output=embed';
        }

        // For any other Google Maps URL we can't parse, prefer the text location.
        if (str_contains($host, 'google.')) {
            return $fallback ?? 'https://www.google.com/maps?q=' . urlencode($rawMapUrl) . '&output=embed';
        }

        return 'https://www.google.com/maps?q=' . urlencode($rawMapUrl) . '&output=embed';
    }

    private function resolveMapDirectUrl(?string $mapUrl, ?string $fallbackLocation): ?string
    {
        if (! empty($mapUrl)) {
            return trim($mapUrl);
        }

        if (! empty($fallbackLocation)) {
            return 'https://www.google.com/maps/search/' . urlencode($fallbackLocation);
        }

        return null;
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
