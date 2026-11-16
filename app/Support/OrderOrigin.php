<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Where a sale came from ("Origin"): instagram, organic, affiliate, ... Stored on the order as a lowercase slug.
 * Tickets show the origin of the order they belong to.
 */
final class OrderOrigin
{
    public const SESSION_KEY = 'order.origin';

    public const ORGANIC = 'organic';

    /** Selectable in the admin. Anything else that was recorded (e.g. a custom utm_source) is still shown and kept. */
    public const OPTIONS = [
        'organic' => 'Organic',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'tiktok' => 'TikTok',
        'whatsapp' => 'WhatsApp',
        'twitter' => 'X (Twitter)',
        'youtube' => 'YouTube',
        'snapchat' => 'Snapchat',
        'google' => 'Google',
        'affiliate' => 'Affiliate',
        'email' => 'Email',
        'other' => 'Other',
    ];

    /** Referrer host fragment => origin. */
    private const HOSTS = [
        'instagram.com' => 'instagram', 'facebook.com' => 'facebook', 'fb.com' => 'facebook', 'fb.me' => 'facebook',
        'tiktok.com' => 'tiktok', 'wa.me' => 'whatsapp', 'whatsapp.com' => 'whatsapp', 'twitter.com' => 'twitter',
        't.co' => 'twitter', 'x.com' => 'twitter', 'youtube.com' => 'youtube', 'youtu.be' => 'youtube',
        'snapchat.com' => 'snapchat', 'google.' => 'google',
    ];

    /** A free-form value to a clean slug, or null when empty. */
    public static function normalize(?string $value): ?string
    {
        $slug = trim(strtolower((string) preg_replace('/[^a-z0-9_]+/i', '_', trim((string) $value))), '_');
        $slug = substr($slug, 0, 50);

        return $slug === '' ? null : $slug;
    }

    public static function label(?string $origin): string
    {
        if ($origin === null || $origin === '') {
            return '—';
        }

        return self::OPTIONS[$origin] ?? ucwords(str_replace('_', ' ', $origin));
    }

    /** Remember the visitor's first touch: ?utm_source= / ?origin= / ?src=, else an external referrer. The first one wins. */
    public static function capture(Request $request): void
    {
        if (! $request->hasSession() || $request->session()->has(self::SESSION_KEY)) {
            return;
        }

        $param = null;
        foreach (['utm_source', 'origin', 'src'] as $key) {
            if (is_string($request->query($key)) && ($param = self::normalize($request->query($key))) !== null) {
                break;
            }
        }
        if ($param !== null) {
            $request->session()->put(self::SESSION_KEY, $param);

            return;
        }

        $host = strtolower((string) parse_url((string) $request->headers->get('referer'), PHP_URL_HOST));
        if ($host === '' || $host === strtolower($request->getHost())) {
            return;
        }
        foreach (self::HOSTS as $needle => $origin) {
            if (str_contains($host, $needle)) {
                $request->session()->put(self::SESSION_KEY, $origin);

                return;
            }
        }
    }

    /** What to store on a new online order: the captured first touch, else affiliate, else organic. */
    public static function forCheckout(Request $request, ?int $affiliateUserId = null): string
    {
        $captured = self::normalize((string) $request->session()->get(self::SESSION_KEY, ''));

        return $captured ?? ($affiliateUserId ? 'affiliate' : self::ORGANIC);
    }
}
