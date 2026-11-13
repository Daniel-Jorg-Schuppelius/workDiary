<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeMarkdownRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages;

use CommonToolkit\Helper\Data\StringHelper;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\{Image, Link};
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Inline\Text;

/**
 * Markdown der Wissensseiten (Feature 191, MVP-1664) als sicheres HTML.
 *
 * Grundlage ist dieselbe Grenze wie im Hilfecenter: GitHub-Markdown mit
 * `html_input = escape` und ohne unsichere Links — eingegebenes HTML erscheint
 * als Text, `javascript:`-Verweise verlieren ihr Ziel. Dazu:
 *
 * - Hinweiskästen `:::hinweis`, `:::tipp`, `:::achtung` (auch `note`, `tip`,
 *   `warning`) und Aufklappabschnitte `:::details Titel`, jeweils bis zur Zeile
 *   `:::`, verschachtelbar. Der Rahmen kommt von hier, der Inhalt läuft durch
 *   denselben Konverter, Titel werden escaped.
 * - Bilder nur aus den Anhängen der Seite: `![Text](anhang:Dateiname.png)`.
 *   Fremde Bild-Adressen werden nicht geladen (Datenschutz, CSP), sondern als
 *   Verweis gezeigt.
 */
final class KnowledgeMarkdownRenderer {
    /** @var array<string, string> Kennwort → Art des Hinweiskastens */
    private const array CALLOUTS = [
        'hinweis' => 'note',
        'note' => 'note',
        'info' => 'note',
        'tipp' => 'tip',
        'tip' => 'tip',
        'achtung' => 'warning',
        'warnung' => 'warning',
        'warning' => 'warning',
    ];

    /** @var array<string, array{class: string, role: string}> */
    private const array CALLOUT_STYLE = [
        'note' => ['class' => 'alert alert-info', 'role' => 'note'],
        'tip' => ['class' => 'alert alert-success', 'role' => 'note'],
        'warning' => ['class' => 'alert alert-warning', 'role' => 'alert'],
    ];

    private const array DETAILS = ['details', 'aufklappen'];

    private const int MAX_CONTAINER_DEPTH = 5;

    /**
     * @param  array<string, string>  $images  Dateiname (klein) → Bildadresse (signierter Link bzw. Data-URL fürs PDF)
     */
    public function render(?string $markdown, array $images = []): string {
        $text = StringHelper::normalizeLineEndings(StringHelper::stripBom((string) $markdown), "\n");
        if (trim($text) === '') {
            return '';
        }
        $converter = $this->converter($images);

        return $this->renderNodes($this->parse($text), $converter);
    }

    /**
     * Zerlegt den Text zeilenweise in Markdown-Abschnitte und Container.
     * Code-Blöcke bleiben unangetastet, ein offener Container endet am Ende.
     *
     * @return array<int, mixed>
     */
    private function parse(string $text): array {
        /** @var array<string, mixed> $root */
        $root = ['children' => [], 'buffer' => []];
        /** @var array<int, array<string, mixed>> $stack */
        $stack = [];
        $fence = null;

        $flush = static function (array &$node): void {
            $buffer = (array) ($node['buffer'] ?? []);
            if ($buffer !== []) {
                $node['children'] = [...(array) ($node['children'] ?? []), ['type' => 'markdown', 'text' => implode("\n", $buffer)]];
                $node['buffer'] = [];
            }
        };

        foreach (StringHelper::lines($text) as $line) {
            $trimmed = ltrim($line);
            if ($fence !== null) {
                if (str_starts_with($trimmed, $fence)) {
                    $fence = null;
                }
                $this->append($root, $stack, $line);

                continue;
            }
            if (preg_match('/^(```|~~~)/', $trimmed, $open) === 1) {
                $fence = $open[1];
                $this->append($root, $stack, $line);

                continue;
            }
            if (preg_match('/^:::\s*$/', $trimmed) === 1 && $stack !== []) {
                $node = array_pop($stack);
                $flush($node);
                $finished = ['type' => $node['type'], 'kind' => $node['kind'], 'title' => $node['title'], 'children' => $node['children']];
                if ($stack !== []) {
                    $parent = array_pop($stack);
                    $flush($parent);
                    $parent['children'][] = $finished;
                    $stack[] = $parent;
                } else {
                    $flush($root);
                    $root['children'][] = $finished;
                }

                continue;
            }
            if (count($stack) < self::MAX_CONTAINER_DEPTH && preg_match('/^:::\s*([\p{L}]+)\s*(.*)$/u', $trimmed, $match) === 1) {
                $word = StringHelper::toLower($match[1]);
                $type = isset(self::CALLOUTS[$word]) ? 'callout' : (in_array($word, self::DETAILS, true) ? 'details' : null);
                if ($type !== null) {
                    if ($stack !== []) {
                        $parent = array_pop($stack);
                        $flush($parent);
                        $stack[] = $parent;
                    } else {
                        $flush($root);
                    }
                    $stack[] = ['type' => $type, 'kind' => self::CALLOUTS[$word] ?? 'details', 'title' => trim($match[2]), 'children' => [], 'buffer' => []];

                    continue;
                }
            }
            $this->append($root, $stack, $line);
        }

        // Nicht geschlossene Container enden mit dem Text.
        while ($stack !== []) {
            $node = array_pop($stack);
            $flush($node);
            $finished = ['type' => $node['type'], 'kind' => $node['kind'], 'title' => $node['title'], 'children' => $node['children']];
            if ($stack !== []) {
                $parent = array_pop($stack);
                $flush($parent);
                $parent['children'][] = $finished;
                $stack[] = $parent;
            } else {
                $flush($root);
                $root['children'][] = $finished;
            }
        }
        $flush($root);

        return $root['children'];
    }

    /**
     * @param  array<string, mixed>  $root
     * @param  array<int, array<string, mixed>>  $stack
     */
    private function append(array &$root, array &$stack, string $line): void {
        if ($stack === []) {
            $root['buffer'] = [...(array) $root['buffer'], $line];

            return;
        }
        $last = array_key_last($stack);
        $stack[$last]['buffer'] = [...(array) $stack[$last]['buffer'], $line];
    }

    /** @param  array<int, mixed>  $nodes */
    private function renderNodes(array $nodes, MarkdownConverter $converter): string {
        $html = '';
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $html .= match ($node['type'] ?? '') {
                'markdown' => (string) $converter->convert((string) ($node['text'] ?? '')),
                'callout' => $this->callout((string) ($node['kind'] ?? 'note'), (string) ($node['title'] ?? ''), $this->renderNodes($children, $converter)),
                'details' => $this->details((string) ($node['title'] ?? ''), $this->renderNodes($children, $converter)),
                default => '',
            };
        }

        return $html;
    }

    private function callout(string $kind, string $title, string $inner): string {
        $style = self::CALLOUT_STYLE[$kind] ?? self::CALLOUT_STYLE['note'];
        $heading = $title !== '' ? $title : (string) __('knowledge_pages.callout.' . $kind);

        return sprintf(
            '<div class="%s my-3 items-start text-sm" role="%s"><div class="min-w-0"><p class="font-semibold">%s</p>%s</div></div>',
            $style['class'],
            $style['role'],
            e($heading),
            $inner,
        );
    }

    private function details(string $title, string $inner): string {
        $summary = $title !== '' ? $title : (string) __('knowledge_pages.callout.details');

        return sprintf(
            '<details class="collapse collapse-arrow my-3 border border-base-300 bg-base-200"><summary class="collapse-title font-medium">%s</summary><div class="collapse-content">%s</div></details>',
            e($summary),
            $inner,
        );
    }

    /** @param  array<string, string>  $images */
    private function converter(array $images): MarkdownConverter {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            'external_link' => [
                'internal_hosts' => is_string($host) && $host !== '' ? [$host] : [],
                'open_in_new_window' => true,
                'nofollow' => 'external',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new ExternalLinkExtension());
        $environment->addEventListener(DocumentParsedEvent::class, static function (DocumentParsedEvent $event) use ($images): void {
            $found = [];
            foreach ($event->getDocument()->iterator() as $node) {
                if ($node instanceof Image) {
                    $found[] = $node;
                }
            }
            foreach ($found as $image) {
                $url = trim($image->getUrl());
                $alt = '';
                foreach ($image->children() as $child) {
                    if ($child instanceof Text) {
                        $alt .= $child->getLiteral();
                    }
                }
                if (str_starts_with(StringHelper::toLower($url), 'anhang:')) {
                    $key = StringHelper::toLower(trim(rawurldecode(substr($url, 7))));
                    if (isset($images[$key])) {
                        $image->setUrl($images[$key]);

                        continue;
                    }
                    $image->replaceWith(new Text('[' . ($alt !== '' ? $alt : $key) . ']'));

                    continue;
                }
                // Fremde Bilder lädt die Seite nicht — sie erscheinen als Verweis.
                $image->replaceWith(new Link($url, $alt !== '' ? $alt : $url));
            }
        });

        return new MarkdownConverter($environment);
    }
}
