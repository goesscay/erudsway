<?php

namespace App\Support\Html;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Cleans instructor-written rich text before it is stored. Only a small, readable subset
 * of HTML survives; scripts, styles, event handlers and unsafe URLs are removed.
 */
class HtmlSanitizer
{
    private ?HTMLPurifier $purifier = null;

    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $clean = trim($this->purifier()->purify($html));

        return $clean === '' ? null : $clean;
    }

    /** Visible text length, used for "description is long enough" checks. */
    public static function textLength(?string $html): int
    {
        return mb_strlen(trim(html_entity_decode(strip_tags((string) $html))));
    }

    private function purifier(): HTMLPurifier
    {
        if ($this->purifier) {
            return $this->purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,ul,ol,li,h3,h4,blockquote,code,pre,a[href|title]');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('HTML.TargetBlank', true);
        $config->set('HTML.Nofollow', true);
        $config->set('AutoFormat.RemoveEmpty', true);
        $cache = storage_path('framework/cache/htmlpurifier');
        if (! is_dir($cache)) {
            @mkdir($cache, 0775, true);
        }
        $config->set('Cache.SerializerPath', $cache);

        return $this->purifier = new HTMLPurifier($config);
    }
}
