<?php

namespace App\Modules\Legacy\Moodle;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Turns Moodle page/label HTML into something our reading lectures accept: the embedded
 * video player is taken out (the video becomes its own lecture), images are re-hosted via
 * a callback, and headings shift down a level (the lecture title is the page's heading).
 */
class MoodleHtml
{
    private const PLUGINFILE = '@@PLUGINFILE@@/';

    /**
     * File names of videos embedded with <video>/<source>, in order.
     *
     * @return list<string>
     */
    public static function videoFiles(string $html): array
    {
        preg_match_all('/<source[^>]+src="'.preg_quote(self::PLUGINFILE, '/').'([^"]+)"/i', $html, $m);

        return array_values(array_unique(array_map(fn ($name) => rawurldecode(html_entity_decode($name)), $m[1])));
    }

    /**
     * @param  callable(string): ?string  $image  file name in the module → URL to use, or null to drop the image
     */
    public static function toArticle(string $html, callable $image): string
    {
        $doc = new DOMDocument;
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="moodle-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($doc);

        // The player wrapper (or a bare <video>) — the video is its own lecture now.
        foreach (iterator_to_array($xpath->query('//video') ?: []) as $video) {
            $wrapper = $video->parentNode;
            $target = $wrapper instanceof DOMElement && $wrapper->getAttribute('id') !== 'moodle-root' && trim($wrapper->textContent) === '' ? $wrapper : $video;
            $target->parentNode?->removeChild($target);
        }

        foreach (iterator_to_array($xpath->query('//img') ?: []) as $img) {
            /** @var DOMElement $img */
            $src = $img->getAttribute('src');
            $url = str_starts_with($src, self::PLUGINFILE) ? $image(rawurldecode(substr($src, strlen(self::PLUGINFILE)))) : null;
            if ($url === null) {
                $img->parentNode?->removeChild($img);

                continue;
            }
            foreach (iterator_to_array($img->attributes ?? []) as $attr) {
                $img->removeAttribute($attr->nodeName);
            }
            $img->setAttribute('src', $url);
            $img->setAttribute('alt', '');
        }

        // h3 first, so headings just moved down to h3 aren't moved again.
        foreach (['h3' => 'h4', 'h5' => 'h4', 'h6' => 'h4', 'h1' => 'h3', 'h2' => 'h3'] as $from => $to) {
            foreach (iterator_to_array($doc->getElementsByTagName($from)) as $heading) {
                self::rename($doc, $heading, $to);
            }
        }

        $root = $doc->getElementById('moodle-root') ?? $doc;
        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    /** Visible text, with paragraphs and line breaks kept as new lines (for quiz prompts and options). */
    public static function toText(?string $html): string
    {
        $html = (string) $html;
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</(p|div|li|h\d|tr)>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/ *\n */", "\n", $text) ?? $text;

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    /** The first heading's text, for naming a lecture made from a Moodle label. */
    public static function firstHeading(string $html): ?string
    {
        if (! preg_match('#<h[1-6][^>]*>(.*?)</h[1-6]>#is', $html, $m)) {
            return null;
        }
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

        return $text === '' ? null : $text;
    }

    private static function rename(DOMDocument $doc, DOMNode $node, string $tag): void
    {
        $new = $doc->createElement($tag);
        while ($node->firstChild) {
            $new->appendChild($node->firstChild);
        }
        $node->parentNode?->replaceChild($new, $node);
    }
}
