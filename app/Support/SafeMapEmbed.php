<?php

namespace App\Support;

/**
 * Turns the admin-supplied "map embed" value into a safe, canonical iframe.
 *
 * Only the iframe `src` is read, and only when it points to an approved map provider over HTTPS.
 * Every other attribute, tag and script in the pasted markup is discarded and the iframe is rebuilt
 * from scratch, so untrusted HTML/JavaScript can never reach the page.
 */
class SafeMapEmbed
{
    /** host => allowed path prefixes */
    private const ALLOWED = [
        'www.google.com' => ['/maps/embed', '/maps'],
        'maps.google.com' => ['/maps'],
        'www.openstreetmap.org' => ['/export/embed.html'],
        'www.bing.com' => ['/maps/embed'],
    ];

    public static function url(?string $embed): ?string
    {
        $embed = trim((string) $embed);

        if ($embed === '') {
            return null;
        }

        $candidate = $embed;
        if (stripos($embed, '<iframe') !== false) {
            if (! preg_match('/<iframe\b[^>]*?\bsrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $embed, $m)) {
                return null;
            }
            $candidate = ($m[1] ?? '') !== '' ? $m[1] : ((($m[2] ?? '') !== '') ? $m[2] : ($m[3] ?? ''));
        }

        $candidate = html_entity_decode(trim($candidate), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (preg_match('/[\x00-\x20\x7f<>"\'\\\\]/', $candidate)) {
            return null;
        }

        $parts = parse_url($candidate);
        if (! $parts || strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '/';
        $prefixes = self::ALLOWED[$host] ?? null;
        if ($prefixes === null) {
            return null;
        }

        $pathAllowed = false;
        foreach ($prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                $pathAllowed = true;
                break;
            }
        }

        if (! $pathAllowed) {
            return null;
        }

        // Plain google.com/maps pages are not embeddable unless they explicitly ask for the embed output.
        $isGoogle = in_array($host, ['www.google.com', 'maps.google.com'], true);
        if ($isGoogle && ! str_starts_with($path, '/maps/embed')) {
            parse_str($parts['query'] ?? '', $query);

            return ($query['output'] ?? null) === 'embed' ? $candidate : null;
        }

        return $candidate;
    }

    public static function render(?string $embed): string
    {
        $url = static::url($embed);

        if ($url === null) {
            return '';
        }

        return '<iframe src="'.e($url).'" title="Event location map" loading="lazy" allowfullscreen '
            .'referrerpolicy="no-referrer-when-downgrade" sandbox="allow-scripts allow-same-origin allow-popups"></iframe>';
    }
}
