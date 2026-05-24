<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpTopicLoader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Help;

use Illuminate\Support\Facades\File;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Lädt Hilfe-Topics aus dem Dateisystem (Markdown mit YAML-Front-Matter)
 * und wandelt sie in eine strukturierte Form für DB-Reindex und Tests um.
 *
 * Dateipfad-Konvention: resources/help/{locale}/{topic}.md
 * Topic-Code = Dateiname ohne `.md`.
 *
 * Front-Matter unterstützt aktuell:
 *  - title: "…"
 *  - version: int
 *  - audience: [list]
 *  - related: [list]
 * (Bewusst minimaler Parser ohne symfony/yaml — die Felder reichen für MVP.)
 */
class HelpTopicLoader {
    public function __construct(
        private readonly string $rootPath,
    ) {}

    public static function defaultPath(): string {
        return resource_path('help');
    }

    /** @return list<string> */
    public function locales(): array {
        if (! File::isDirectory($this->rootPath)) {
            return [];
        }

        $locales = [];
        foreach (File::directories($this->rootPath) as $dir) {
            $locales[] = basename($dir);
        }
        sort($locales);

        return $locales;
    }

    /** @return list<string> */
    public function topicsForLocale(string $locale): array {
        $dir = $this->rootPath . DIRECTORY_SEPARATOR . $locale;
        if (! File::isDirectory($dir)) {
            return [];
        }

        $topics = [];
        foreach (File::files($dir) as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }
            $topics[] = $file->getBasename('.md');
        }
        sort($topics);

        return $topics;
    }

    /**
     * @return array{
     *     topic:string,
     *     locale:string,
     *     title:string,
     *     audience:list<string>,
     *     version:int,
     *     body_md:string,
     *     body_html:string,
     *     related:list<string>,
     *     source_updated_at:\Carbon\CarbonImmutable
     * }|null
     */
    public function load(string $topic, string $locale): ?array {
        $path = $this->pathFor($topic, $locale);
        if (! File::isFile($path)) {
            return null;
        }

        $raw = File::get($path);
        [$frontMatter, $bodyMd] = $this->splitFrontMatter($raw);

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
        $bodyHtml = (string) $converter->convert($bodyMd);

        return [
            'topic' => $topic,
            'locale' => $locale,
            'title' => (string) ($frontMatter['title'] ?? $topic),
            'audience' => $this->normalizeList($frontMatter['audience'] ?? []),
            'version' => (int) ($frontMatter['version'] ?? 1),
            'body_md' => trim($bodyMd),
            'body_html' => $bodyHtml,
            'related' => $this->normalizeList($frontMatter['related'] ?? []),
            'source_updated_at' => \Carbon\CarbonImmutable::createFromTimestamp(File::lastModified($path)),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function loadAllForLocale(string $locale): array {
        $items = [];
        foreach ($this->topicsForLocale($locale) as $topic) {
            $loaded = $this->load($topic, $locale);
            if ($loaded !== null) {
                $items[] = $loaded;
            }
        }

        return $items;
    }

    /** @return list<array<string,mixed>> */
    public function loadAll(): array {
        $all = [];
        foreach ($this->locales() as $locale) {
            foreach ($this->loadAllForLocale($locale) as $item) {
                $all[] = $item;
            }
        }

        return $all;
    }

    /** @return list<string> */
    public function allTopicCodes(): array {
        $codes = [];
        foreach ($this->locales() as $locale) {
            foreach ($this->topicsForLocale($locale) as $topic) {
                $codes[$topic] = true;
            }
        }

        $unique = array_keys($codes);
        sort($unique);

        return $unique;
    }

    private function pathFor(string $topic, string $locale): string {
        return $this->rootPath . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . $topic . '.md';
    }

    /** @return list<string> */
    private function normalizeList(mixed $value): array {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $entry) {
            if (is_string($entry) && $entry !== '') {
                $out[] = $entry;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Trennt Front-Matter vom Markdown-Body und parst die Felder mit dem
     * minimalen YAML-Subset (siehe Klassen-Doc).
     *
     * @return array{0: array<string,mixed>, 1: string}
     */
    private function splitFrontMatter(string $raw): array {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        if (! str_starts_with(ltrim($raw, "\r\n"), '---')) {
            return [[], $raw];
        }

        $trimmed = ltrim($raw, "\r\n");
        $endPos = strpos($trimmed, "\n---", 3);
        if ($endPos === false) {
            return [[], $raw];
        }

        $frontRaw = substr($trimmed, 3, $endPos - 3);
        $body = substr($trimmed, $endPos + 4);
        $body = ltrim($body, "\r\n");

        return [$this->parseMiniYaml($frontRaw), $body];
    }

    /** @return array<string, mixed> */
    private function parseMiniYaml(string $yaml): array {
        $lines = preg_split("/\r?\n/", $yaml) ?: [];
        $result = [];
        $currentListKey = null;

        foreach ($lines as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }

            // Listen-Element (führendes "-" mit Einrückung)
            if (preg_match('/^\s+-\s*(.+?)\s*$/', $line, $m) && $currentListKey !== null) {
                $result[$currentListKey][] = $this->unquote($m[1]);
                continue;
            }

            if (preg_match('/^([A-Za-z0-9_]+)\s*:\s*(.*)$/', $line, $m)) {
                $key = $m[1];
                $value = trim($m[2]);

                if ($value === '' || $value === '[]') {
                    // Beginn einer Liste oder leere Liste
                    $result[$key] = [];
                    $currentListKey = $value === '[]' ? null : $key;
                    continue;
                }

                if (preg_match('/^\[(.*)\]$/', $value, $arr)) {
                    // Inline-Liste [a, b, c]
                    $parts = array_filter(array_map(
                        fn($item) => $this->unquote(trim($item)),
                        explode(',', $arr[1])
                    ), static fn($x) => $x !== '');
                    $result[$key] = array_values($parts);
                    $currentListKey = null;
                    continue;
                }

                $result[$key] = $this->castScalar($this->unquote($value));
                $currentListKey = null;
            }
        }

        return $result;
    }

    private function unquote(string $value): string {
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }

    private function castScalar(string $value): string|int|bool {
        if ($value === 'true' || $value === 'false') {
            return $value === 'true';
        }
        if (preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        return $value;
    }
}
