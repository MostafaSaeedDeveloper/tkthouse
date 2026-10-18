<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/** The signed-in user's own bell notifications. A user can never read or change anyone else's. */
class NotificationController extends Controller
{
    public const BELL_LIMIT = 8;

    public function index(Request $request)
    {
        $user = $request->user();
        $unreadOnly = $request->boolean('unread');
        $notifications = $user->notifications()->when($unreadOnly, fn ($q) => $q->whereNull('read_at'))->paginate(20)->withQueryString();

        return view('admin.notifications.index', ['notifications' => $notifications, 'unreadOnly' => $unreadOnly, 'unread' => $user->unreadNotifications()->count()]);
    }

    /** What the bell polls: the unread counter and the rendered latest items. */
    public function feed(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'html' => view('admin.notifications._items', ['items' => $user->notifications()->limit(self::BELL_LIMIT)->get()])->render(),
        ])->header('Cache-Control', 'no-store');
    }

    /** Marks one read and goes where it points (so a click on the bell item is a plain link). */
    public function open(Request $request, string $id)
    {
        $n = $this->mine($request, $id);
        $n->markAsRead();
        $url = (string) ($n->data['url'] ?? '');

        return redirect()->to($this->safeUrl($url) ?: route('admin.notifications.index'));
    }

    public function read(Request $request, string $id)
    {
        $this->mine($request, $id)->markAsRead();

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    private function mine(Request $request, string $id): DatabaseNotification
    {
        return $request->user()->notifications()->whereKey($id)->firstOrFail();
    }

    /** Only same-site links are followed. */
    private function safeUrl(string $url): ?string
    {
        if ($url === '') {
            return null;
        }
        $host = parse_url($url, PHP_URL_HOST);

        return ($host === null || $host === parse_url(config('app.url'), PHP_URL_HOST) || $host === request()->getHost()) ? $url : null;
    }
}
