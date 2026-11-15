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
}
