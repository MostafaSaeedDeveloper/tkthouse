<?php

namespace App\Services\Wallet;

use App\Models\Event;
use App\Models\IssuedTicket;
use Illuminate\Support\Carbon;
use RuntimeException;
use ZipArchive;

/** Builds a signed Apple Wallet pass (.pkpass) for one issued ticket: the event details plus the ticket's QR. */
class ApplePassBuilder
{
    public static function configured(): bool
    {
        $c = (array) config('wallet.apple');

        return filled($c['pass_type_id'] ?? null) && filled($c['team_id'] ?? null)
            && filled($c['certificate_path'] ?? null) && is_file((string) $c['certificate_path'])
            && filled($c['wwdr_path'] ?? null) && is_file((string) $c['wwdr_path'])
            && extension_loaded('zip') && extension_loaded('openssl');
    }

    /** @return string path of a temporary .pkpass file (the caller deletes it after sending) */
    public function build(IssuedTicket $ticket, ?Event $event): string
    {
        if (! self::configured()) {
            throw new RuntimeException('Apple Wallet is not configured.');
        }

        $files = [
            'pass.json' => json_encode($this->passData($ticket, $event), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ] + $this->images();

        $manifest = [];
        foreach ($files as $name => $contents) {
            $manifest[$name] = sha1($contents);
        }
        $files['manifest.json'] = json_encode($manifest, JSON_THROW_ON_ERROR);
        $files['signature'] = $this->sign($files['manifest.json']);

        $path = tempnam(sys_get_temp_dir(), 'pkpass');
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the pass file.');
        }
        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return $path;
    }

    private function passData(IssuedTicket $ticket, ?Event $event): array
    {
        $c = (array) config('wallet.apple');
        $eventName = $event?->name ?: ($ticket->ticket_name ?: 'Event ticket');
        $start = $this->startsAt($event);

        $pass = [
            'formatVersion' => 1,
            'passTypeIdentifier' => $c['pass_type_id'],
            'teamIdentifier' => $c['team_id'],
            'organizationName' => $c['organization_name'],
            'serialNumber' => (string) $ticket->uuid,
            'description' => $eventName,
            'logoText' => $c['organization_name'],
            'backgroundColor' => $c['background_color'],
            'foregroundColor' => $c['foreground_color'],
            'labelColor' => $c['label_color'],
            'barcodes' => [[
                'format' => 'PKBarcodeFormatQR',
                'message' => (string) $ticket->ticket_number, // exactly what the web QR encodes, so the scanner reads it the same way
                'messageEncoding' => 'iso-8859-1',
                'altText' => '#'.$ticket->ticket_number,
            ]],
            'eventTicket' => [
                'primaryFields' => [['key' => 'event', 'label' => 'EVENT', 'value' => $eventName]],
                'secondaryFields' => array_values(array_filter([
                    $event?->event_date ? ['key' => 'date', 'label' => 'DATE', 'value' => $event->formatted_date_range] : null,
                    $event?->event_time ? ['key' => 'time', 'label' => 'TIME', 'value' => Carbon::parse($event->event_time)->format('g:i A')] : null,
                ])),
                'auxiliaryFields' => array_values(array_filter([
                    ['key' => 'attendee', 'label' => 'ATTENDEE', 'value' => $ticket->holder_name ?: 'Attendee'],
                    ['key' => 'type', 'label' => 'TICKET', 'value' => (string) $ticket->ticket_name],
                ])),
                'backFields' => array_values(array_filter([
                    $event?->location ? ['key' => 'venue', 'label' => 'Venue', 'value' => $event->location] : null,
                    $event?->map_url ? ['key' => 'map', 'label' => 'Map', 'value' => $event->map_url] : null,
                    ['key' => 'number', 'label' => 'Ticket number', 'value' => '#'.$ticket->ticket_number],
                ])),
            ],
        ];

        if ($start) {
            $pass['relevantDate'] = $start->toIso8601String();
            $end = $event?->end_date ? Carbon::parse($event->end_date, config('app.timezone'))->endOfDay() : $start->copy()->addDay();
            $pass['expirationDate'] = $end->copy()->addDay()->toIso8601String();
        }

        return $pass;
    }

    private function startsAt(?Event $event): ?Carbon
    {
        if (! $event?->event_date) {
            return null;
        }
        $start = Carbon::parse($event->event_date->format('Y-m-d'), config('app.timezone'));
        if ($event->event_time) {
            try {
                $time = Carbon::parse($event->event_time);
                $start->setTime($time->hour, $time->minute);
            } catch (\Throwable) {
            }
        }

        return $start;
    }

    /** Detached PKCS#7 signature (DER) of manifest.json, signed with the Pass Type ID certificate. */
    private function sign(string $manifest): string
    {
        $c = (array) config('wallet.apple');
        $p12 = file_get_contents((string) $c['certificate_path']);
        $certs = [];
        if (! openssl_pkcs12_read($p12, $certs, (string) ($c['certificate_password'] ?? ''))) {
            throw new RuntimeException('The Pass Type ID certificate could not be read (wrong password?).');
        }

        $in = tempnam(sys_get_temp_dir(), 'pkin');
        $out = tempnam(sys_get_temp_dir(), 'pkout');
        try {
            file_put_contents($in, $manifest);
            $ok = openssl_pkcs7_sign($in, $out, $certs['cert'], [$certs['pkey'], ''], [], PKCS7_BINARY | PKCS7_DETACHED, (string) $c['wwdr_path']);
            if (! $ok) {
                throw new RuntimeException('Signing the pass failed.');
            }
            $smime = (string) file_get_contents($out);
        } finally {
            @unlink($in);
            @unlink($out);
        }

        // The S/MIME output is a MIME message; the signature part is the base64 block after the last boundary header.
        if (! preg_match('/Content-Disposition: attachment; filename="smime\.p7s"\s+(.+?)\r?\n\r?\n?------/s', $smime, $m)) {
            throw new RuntimeException('Unexpected signature format.');
        }

        return base64_decode(trim($m[1]), true) ?: throw new RuntimeException('Unexpected signature encoding.');
    }

    /** icon + logo required by Wallet, drawn from the site logo (an empty icon makes iOS reject the pass). */
    private function images(): array
    {
        $images = [];
        foreach ([['icon.png', 29], ['icon@2x.png', 58], ['icon@3x.png', 87]] as [$name, $size]) {
            $images[$name] = $this->squarePng($size);
        }
        foreach ([['logo.png', 160, 50], ['logo@2x.png', 320, 100]] as [$name, $w, $h]) {
            $images[$name] = $this->logoPng($w, $h);
        }

        return $images;
    }

    private function source(string $file)
    {
        $path = public_path('images/'.$file);

        return is_file($path) ? imagecreatefrompng($path) : false;
    }

    private function canvas(int $w, int $h)
    {
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagealphablending($im, true);

        return $im;
    }

    private function fit($src, int $w, int $h): string
    {
        $im = $this->canvas($w, $h);
        if ($src) {
            $sw = imagesx($src);
            $sh = imagesy($src);
            $scale = min($w / $sw, $h / $sh);
            $nw = max(1, (int) round($sw * $scale));
            $nh = max(1, (int) round($sh * $scale));
            imagecopyresampled($im, $src, intdiv($w - $nw, 2), intdiv($h - $nh, 2), 0, 0, $nw, $nh, $sw, $sh);
        }
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    private function squarePng(int $size): string
    {
        return $this->fit($this->source('logo-dark.png'), $size, $size);
    }

    private function logoPng(int $w, int $h): string
    {
        return $this->fit($this->source('logo-light.png'), $w, $h);
    }
}
