<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Ticket QR codes: what goes inside them, and how they are drawn.
 *
 * New tickets carry an unguessable token ("TKT-" + 24 random capital letters, ~113 bits) instead of the order-derived,
 * sequential ticket number. The token has no digits on purpose: the scanner extracts bare ticket numbers from a payload
 * with a digit pattern, and a token must never be mistaken for one. Tickets issued before this change keep their old
 * payload (the bare ticket number) and stay valid.
 *
 * Codes are drawn on our own server: nothing about a ticket is sent to a third-party QR service.
 */
class TicketQr
{
    public const PREFIX = 'TKT-';

    public static function newToken(): string
    {
        $letters = '';
        for ($i = 0; $i < 24; $i++) {
            $letters .= chr(65 + random_int(0, 25));
        }

        return self::PREFIX.$letters;
    }

    public static function isToken(?string $payload): bool
    {
        return is_string($payload) && preg_match('/^'.self::PREFIX.'[A-Z]{24}$/', $payload) === 1;
    }

    public static function svg(string $payload, int $size = 300): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd()));

        return $writer->writeString($payload);
    }

    public static function dataUri(string $payload, int $size = 300): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($payload, $size));
    }

    /**
     * Decorative stand-in shown while a ticket's QR is locked. It is generated from a fixed seed and never from the ticket, its
     * payload or its token, and it is not a decodable QR (the data area is pseudo-random), so nothing about the real code can be
     * read or reconstructed from it. The same markup is served to every ticket.
     */
    public static function placeholderSvg(): string
    {
        $n = 25;
        $seed = 20240607;
        $rects = '';
        $inFinder = static fn (int $x, int $y): bool => ($x < 8 && $y < 8) || ($x >= $n - 8 && $y < 8) || ($x < 8 && $y >= $n - 8);
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
                if (! $inFinder($x, $y) && (($seed >> 16) & 3) < 2) {
                    $rects .= '<rect x="'.$x.'" y="'.$y.'" width="1" height="1"/>';
                }
            }
        }
        $finder = '';
        foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$fx, $fy]) {
            $finder .= '<path fill-rule="evenodd" d="M'.$fx.' '.$fy.'h7v7h-7zM'.($fx + 1).' '.($fy + 1).'v5h5v-5z"/><rect x="'.($fx + 2).'" y="'.($fy + 2).'" width="3" height="3"/>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-1 -1 '.($n + 2).' '.($n + 2).'" shape-rendering="crispEdges" aria-hidden="true" focusable="false"><g fill="currentColor">'.$finder.$rects.'</g></svg>';
    }
}
