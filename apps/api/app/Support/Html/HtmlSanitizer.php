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
    private const BASIC = 'p,br,strong,b,em,i,u,ul,ol,li,h3,h4,blockquote,code,pre,a[href|title]';

    /** Reading lectures may also hold tables and images — images only from this site's own storage. */
    private const ARTICLE = self::BASIC.',hr,table,thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan],img[src|alt]';

    /** @var array<string, HTMLPurifier> */
    private array $purifiers = [];

    public function clean(?string $html): ?string
    {
        return $this->run('basic', $html);
    }

    /** For article (reading) lectures. */
    public function cleanArticle(?string $html): ?string
    {
        return $this->run('article', $html);
    }

    /** Visible text length, used for "description is long enough" checks. */
    public static function textLength(?string $html): int
    {
        return mb_strlen(trim(html_entity_decode(strip_tags((string) $html))));
    }

    private function run(string $profile, ?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $clean = trim($this->purifier($profile)->purify($html));

        return $clean === '' ? null : $clean;
    }

    private function purifier(string $profile): HTMLPurifier
    {
        if (isset($this->purifiers[$profile])) {
            return $this->purifiers[$profile];
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.DefinitionID', "erudsway-{$profile}");
        $config->set('HTML.Allowed', $profile === 'article' ? self::ARTICLE : self::BASIC);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        if ($profile === 'article') {
            // Images must live on this site: no tracking pixels or hot-linked files.
            $config->set('URI.Host', (string) parse_url((string) config('app.url'), PHP_URL_HOST));
            $config->set('URI.DisableExternalResources', true);
        }
        $config->set('HTML.TargetBlank', true);
        $config->set('HTML.Nofollow', true);
        $config->set('AutoFormat.RemoveEmpty', true);
        $cache = storage_path('framework/cache/htmlpurifier');
        if (! is_dir($cache)) {
            @mkdir($cache, 0775, true);
        }
        $config->set('Cache.SerializerPath', $cache);

        return $this->purifiers[$profile] = new HTMLPurifier($config);
    }
}
