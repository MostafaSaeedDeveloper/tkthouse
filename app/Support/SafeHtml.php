<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Minimal allow-list sanitiser for rich text authored in the admin panel
 * (e.g. the CKEditor event description) before it is printed on public pages.
 */
class SafeHtml
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li',
        'h2', 'h3', 'h4', 'blockquote', 'a', 'span', 'hr',
    ];

    /** Tags that may carry the editor's font size / colour / alignment (and nothing else) in a style attribute. */
    private const STYLED_TAGS = ['span', 'p', 'h2', 'h3', 'h4', 'li', 'blockquote'];

    /** Keeps only safe color / background-color / font-size / text-align declarations with strictly validated values. */
    private static function cleanStyle(string $style): string
    {
        $color = '(?:#[0-9a-f]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)|hsla?\(\s*\d{1,3}(?:\.\d+)?\s*,\s*\d{1,3}(?:\.\d+)?%\s*,\s*\d{1,3}(?:\.\d+)?%\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)|[a-z]{3,20})';
        $rules = [
            'color' => '~^'.$color.'$~i',
            'background-color' => '~^'.$color.'$~i',
            'font-size' => '~^(?:\d{1,3}(?:\.\d{1,2})?(?:px|em|rem|%)|xx-small|x-small|small|medium|large|x-large|xx-large)$~i',
            'text-align' => '~^(?:left|right|center|justify)$~i',
        ];

        $kept = [];
        foreach (explode(';', $style) as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            $property = strtolower(trim($property));
            $value = trim($value);

            if (isset($rules[$property]) && preg_match($rules[$property], $value)) {
                $kept[$property] = $property.':'.$value;
            }
        }

        return implode(';', $kept);
    }

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="safe-html-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('safe-html-root');

        if (! $root) {
            return e(strip_tags($html));
        }

        self::sanitizeChildren($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private static function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof \DOMComment) {
                $parent->removeChild($node);

                continue;
            }

            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);

            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'svg', 'math', 'template', 'noscript', 'base', 'meta', 'link', 'textarea', 'select', 'button', 'input'], true)) {
                $parent->removeChild($node);

                continue;
            }

            self::sanitizeChildren($node);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                // Unwrap unknown tags but keep their (already sanitised) children.
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $keep = $tag === 'a' && in_array($name, ['href', 'title'], true);

                if ($name === 'style' && in_array($tag, self::STYLED_TAGS, true)) {
                    $style = self::cleanStyle($attribute->value);
                    if ($style !== '') {
                        $node->setAttribute('style', $style);
                    } else {
                        $node->removeAttribute('style');
                    }

                    continue;
                }

                if ($keep && $name === 'href' && ! preg_match('#^(https?://|mailto:|tel:|/(?!/))#i', trim($attribute->value))) {
                    $keep = false;
                }

                if (! $keep) {
                    $node->removeAttribute($attribute->name);
                }
            }

            if ($tag === 'a' && $node->hasAttribute('href')) {
                $node->setAttribute('rel', 'noopener noreferrer');
                $node->setAttribute('target', '_blank');
            }
        }
    }
}
