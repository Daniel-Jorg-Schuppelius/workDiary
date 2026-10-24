<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AdfText.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Jira\Services;

/**
 * Begrenzte Textprojektion des Atlassian Document Format für
 * Jira-Beschreibungen: Text, Absätze, Überschriften, Zeilenumbrüche, Listen,
 * Zitate, Code, Erwähnungen und Links als Text. Kein Medienabruf, kein HTML.
 * Unbekannte Knoten und überschrittene Grenzen machen das Ergebnis
 * unvollständig; die Grenzen gelten vor dem Abstieg.
 */
final class AdfText {
    private const MAX_DEPTH = 24;

    private const MAX_NODES = 5000;

    private const MAX_CHARS = 20000;

    private const BLOCKS = ['paragraph', 'heading', 'blockquote', 'codeBlock'];

    private int $nodes = 0;

    private bool $complete = true;

    /** @return array{text: ?string, complete: bool} */
    public static function convert(mixed $document): array {
        if ($document === null) {
            return ['text' => null, 'complete' => true];
        }
        if (is_string($document)) {
            return ['text' => self::clip(trim($document)), 'complete' => mb_strlen(trim($document)) <= self::MAX_CHARS];
        }
        if (! is_array($document) || ($document['type'] ?? null) !== 'doc' || ! is_array($document['content'] ?? null)) {
            return ['text' => null, 'complete' => false];
        }

        $converter = new self();
        $text = trim((string) preg_replace("/\n{3,}/", "\n\n", $converter->children($document['content'], 0, '')));
        $complete = $converter->complete && mb_strlen($text) <= self::MAX_CHARS;

        return ['text' => $text !== '' ? self::clip($text) : null, 'complete' => $complete];
    }

    /** @param array<mixed> $nodes */
    private function children(array $nodes, int $depth, string $indent): string {
        $out = '';
        foreach ($nodes as $node) {
            $out .= is_array($node) ? $this->node($node, $depth + 1, $indent) : '';
            if (! $this->complete && $this->nodes > self::MAX_NODES) {
                break;
            }
        }

        return $out;
    }

    /** @param array<mixed> $node */
    private function node(array $node, int $depth, string $indent): string {
        if ($depth > self::MAX_DEPTH || ++$this->nodes > self::MAX_NODES) {
            $this->complete = false;

            return '';
        }
        $type = is_string($node['type'] ?? null) ? $node['type'] : '';
        $content = is_array($node['content'] ?? null) ? $node['content'] : [];
        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];

        return match ($type) {
            'text' => is_string($node['text'] ?? null) ? $node['text'] : '',
            'hardBreak' => "\n" . $indent,
            'mention', 'emoji' => is_string($attrs['text'] ?? null) ? $attrs['text'] : '',
            'inlineCard' => is_string($attrs['url'] ?? null) ? $attrs['url'] : '',
            'rule' => "\n---\n\n",
            'bulletList', 'orderedList' => $this->list($type, $content, $depth, $indent) . "\n",
            'listItem' => $this->children($content, $depth, $indent),
            default => in_array($type, self::BLOCKS, true)
                ? $this->children($content, $depth, $indent) . "\n\n"
                : $this->unknown(),
        };
    }

    /** @param array<mixed> $items */
    private function list(string $type, array $items, int $depth, string $indent): string {
        $out = '';
        $number = 1;
        foreach ($items as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'listItem') {
                $this->unknown();

                continue;
            }
            $marker = $type === 'orderedList' ? ($number++) . '. ' : '- ';
            $body = trim((string) preg_replace("/\n{2,}/", "\n", $this->node($item, $depth + 1, $indent . '  ')));
            $out .= $indent . $marker . $body . "\n";
        }

        return $out;
    }

    private function unknown(): string {
        $this->complete = false;

        return '';
    }

    private static function clip(string $text): string {
        return mb_substr($text, 0, self::MAX_CHARS);
    }
}
