<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpSiteExporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Help;

use App\Support\Locales;
use CommonToolkit\Helper\Data\{JsonHelper, StringHelper};
use CommonToolkit\Helper\FileSystem\{File, Folder};

/**
 * Öffentliche Doku-Website (MVP-971): statische Seiten aus denselben
 * Markdown-Quellen wie das Hilfecenter — nur Themen ohne Zielgruppe, Suche im
 * Browser über eine Indexdatei, kein Server-Code. Verweise auf interne Themen
 * entfallen.
 */
final class HelpSiteExporter {
    private const EXCERPT_LENGTH = 1500;

    public function __construct(private readonly HelpCenterCatalog $catalog) {}

    /**
     * @param  list<string>  $locales  leer = alle vorhandenen Sprachen
     * @return array{locales: int, pages: int, media: int}
     */
    public function export(string $output, array $locales = []): array {
        $loader = new HelpTopicLoader(HelpTopicLoader::defaultPath());
        $available = $loader->locales();
        $locales = $locales === [] ? $available : array_values(array_intersect($locales, $available));

        $topics = [];
        foreach ($locales as $locale) {
            $topics[$locale] = [];
            foreach ($loader->loadAllForLocale($locale) as $topic) {
                if ($this->isPublic($topic['audience'])) {
                    $topics[$locale][$topic['topic']] = $topic;
                }
            }
        }

        $media = [];
        $pages = 0;
        $previous = app()->getLocale();
        try {
            foreach ($locales as $locale) {
                app()->setLocale($locale);
                $pages += $this->exportLocale($output, $locale, $topics, $media);
            }
            app()->setLocale($previous);
            $this->write($output . '/index.html', view('help.site.root', [
                'languages' => array_map(static fn (string $code): array => ['code' => $code, 'name' => Locales::native($code)], $locales),
            ])->render());
        } finally {
            app()->setLocale($previous);
        }

        $mediaRoot = (string) config('help-center.media_path');
        foreach (array_keys($media) as $path) {
            $source = $mediaRoot . '/' . $path;
            if (File::isFile($source)) {
                $this->ensureFolder(dirname($output . '/media/' . $path));
                File::copy($source, $output . '/media/' . $path);
            }
        }
        foreach (['site.css', 'search.js'] as $asset) {
            File::copy(resource_path('help-site/' . $asset), $output . '/' . $asset);
        }

        return ['locales' => count($locales), 'pages' => $pages, 'media' => count($media)];
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $topics
     * @param  array<string, true>  $media
     */
    private function exportLocale(string $output, string $locale, array $topics, array &$media): int {
        $own = $topics[$locale];
        $languages = array_keys($topics);
        $sectionTitles = array_column($this->catalog->sections(), 'title', 'key');
        $sections = [];
        $index = [];

        foreach ($own as $code => $topic) {
            $body = $this->rewriteMedia((string) $topic['body_html'], $media);
            $sectionKey = $this->catalog->sectionKeyFor($code);
            $sections[$sectionKey][] = ['topic' => $code, 'title' => $topic['title']];
            $index[] = [
                't' => $topic['title'],
                'u' => $code . '.html',
                's' => $sectionTitles[$sectionKey] ?? '',
                'x' => mb_substr(StringHelper::normalizeWhitespace(StringHelper::htmlEntitiesToText(strip_tags($body))), 0, self::EXCERPT_LENGTH),
                'k' => implode(' ', $topic['keywords']),
            ];

            $related = [];
            foreach ($topic['related'] as $relatedCode) {
                if (isset($own[$relatedCode])) {
                    $related[] = ['topic' => $relatedCode, 'title' => $own[$relatedCode]['title']];
                }
            }

            $this->write($output . '/' . $locale . '/' . $code . '.html', view('help.site.page', [
                'locale' => $locale,
                'topic' => $topic,
                'body' => $body,
                'section' => $sectionTitles[$sectionKey] ?? '',
                'related' => $related,
                'languages' => array_values(array_filter($languages, static fn (string $other): bool => isset($topics[$other][$code]))),
            ])->render());
        }

        $grouped = [];
        foreach ($this->catalog->sections() as $section) {
            if (isset($sections[$section['key']])) {
                usort($sections[$section['key']], static fn (array $a, array $b): int => strcmp((string) $a['title'], (string) $b['title']));
                $grouped[] = $section + ['topics' => $sections[$section['key']]];
            }
        }
        $this->write($output . '/' . $locale . '/index.html', view('help.site.index', [
            'locale' => $locale,
            'sections' => $grouped,
            'languages' => $languages,
        ])->render());
        $this->write(
            $output . '/' . $locale . '/search-index.js',
            'window.HELP_SITE_INDEX = ' . JsonHelper::encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ";\n",
        );

        return count($own);
    }

    /** @param list<string> $audience */
    private function isPublic(array $audience): bool {
        return $audience === [] || in_array('*', $audience, true);
    }

    /**
     * Bild-URLs des Hilfecenters (`/hilfe/media/…`) zeigen auf die mitkopierten
     * Dateien; Pfade mit `..` bleiben außen vor.
     *
     * @param  array<string, true>  $media
     */
    private function rewriteMedia(string $html, array &$media): string {
        return (string) preg_replace_callback(
            '/(<img\b[^>]*\bsrc=")\/hilfe\/media\/([A-Za-z0-9_\-\/.]+)(")/i',
            static function (array $m) use (&$media): string {
                if (str_contains($m[2], '..')) {
                    return $m[0];
                }
                $media[$m[2]] = true;

                return $m[1] . '../media/' . $m[2] . $m[3];
            },
            $html,
        );
    }

    private function write(string $file, string $content): void {
        $this->ensureFolder(dirname($file));
        File::write($file, $content);
    }

    private function ensureFolder(string $folder): void {
        if (! Folder::exists($folder)) {
            Folder::create($folder, 0755, true);
        }
    }
}
