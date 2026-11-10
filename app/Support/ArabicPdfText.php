<?php

namespace App\Support;

/**
 * DomPDF draws text left-to-right and does no Arabic joining, so Arabic names would come out unjoined and mirrored.
 * This converts Arabic runs to their joined presentation forms (Unicode "Arabic Presentation Forms-B", which the bundled
 * DejaVu Sans font has) in visual order. It is for PDF output only: e-mail HTML and the database keep the real text.
 * Text without Arabic is returned untouched.
 */
final class ArabicPdfText
{
    /** char => [isolated, final, initial, medial]; two entries = right-joining only (no initial/medial). */
    private const FORMS = [
        0x621 => [0xFE80], 0x622 => [0xFE81, 0xFE82], 0x623 => [0xFE83, 0xFE84], 0x624 => [0xFE85, 0xFE86], 0x625 => [0xFE87, 0xFE88],
        0x626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C], 0x627 => [0xFE8D, 0xFE8E], 0x628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92],
        0x629 => [0xFE93, 0xFE94], 0x62A => [0xFE95, 0xFE96, 0xFE97, 0xFE98], 0x62B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C],
        0x62C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0], 0x62D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4], 0x62E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8],
        0x62F => [0xFEA9, 0xFEAA], 0x630 => [0xFEAB, 0xFEAC], 0x631 => [0xFEAD, 0xFEAE], 0x632 => [0xFEAF, 0xFEB0],
        0x633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4], 0x634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8], 0x635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC],
        0x636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0], 0x637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4], 0x638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],
        0x639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC], 0x63A => [0xFECD, 0xFECE, 0xFECF, 0xFED0], 0x641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4],
        0x642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8], 0x643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC], 0x644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],
        0x645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4], 0x646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8], 0x647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC],
        0x648 => [0xFEED, 0xFEEE], 0x649 => [0xFEEF, 0xFEF0], 0x64A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],
    ];

    /** lam + alef variant => [isolated, final] ligature */
    private const LAM_ALEF = [0x622 => [0xFEF5, 0xFEF6], 0x623 => [0xFEF7, 0xFEF8], 0x625 => [0xFEF9, 0xFEFA], 0x627 => [0xFEFB, 0xFEFC]];

    public static function shape(?string $text): string
    {
        $text = (string) $text;
        if ($text === '' || ! preg_match('/\p{Arabic}/u', $text)) {
            return $text;
        }

        // Arabic segments (words joined by single spaces) are reversed word-by-word; the rest stays as typed.
        $parts = preg_split('/((?:\p{Arabic}[\p{Arabic}\x{200C}\x{200D}\x{0640}]*)(?:[ ]+\p{Arabic}[\p{Arabic}\x{200C}\x{200D}\x{0640}]*)*)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $rtlBase = preg_match('/^\p{Arabic}/u', ltrim($text)) === 1;

        $out = array_map(fn (string $p) => preg_match('/^\p{Arabic}/u', $p) ? self::segment($p) : $p, $parts);

        return implode('', $rtlBase ? array_reverse($out) : $out);
    }

    private static function segment(string $segment): string
    {
        $words = array_reverse(preg_split('/ +/u', $segment));

        return implode(' ', array_map(fn (string $w) => self::word($w), $words));
    }

    private static function word(string $word): string
    {
        $cps = array_values(array_filter(
            array_map('mb_ord', mb_str_split($word)),
            fn (int $c) => ! ($c >= 0x64B && $c <= 0x65F) && $c !== 0x670 && $c !== 0x200C && $c !== 0x200D   // harakat are dropped
        ));

        $dual = fn (?int $c) => $c !== null && ($c === 0x640 || (isset(self::FORMS[$c]) && count(self::FORMS[$c]) === 4));
        $joinsNext = fn (?int $c) => $c !== null && ($c === 0x640 || (isset(self::FORMS[$c]) && count(self::FORMS[$c]) > 1));

        $shaped = [];
        for ($i = 0, $n = count($cps); $i < $n; $i++) {
            $c = $cps[$i];
            $prevJoins = $i > 0 && $dual($cps[$i - 1]);

            if ($c === 0x644 && isset($cps[$i + 1], self::LAM_ALEF[$cps[$i + 1]])) {
                $shaped[] = self::LAM_ALEF[$cps[$i + 1]][$prevJoins ? 1 : 0];
                $i++;

                continue;
            }
            if ($c === 0x640 || ! isset(self::FORMS[$c])) {
                $shaped[] = $c;

                continue;
            }

            $f = self::FORMS[$c];
            $nextJoins = $dual($c) && $joinsNext($cps[$i + 1] ?? null);

            $shaped[] = match (true) {
                count($f) === 1 => $f[0],
                $prevJoins && $nextJoins => $f[3],
                $prevJoins => $f[1],
                $nextJoins => $f[2],
                default => $f[0],
            };
        }

        return implode('', array_map('mb_chr', array_reverse($shaped)));
    }
}
