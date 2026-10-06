<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RawMarkupRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Bausteine laufen über ihre Komponente (Konsolidierungs-Audit 2026-10, k4-13):
 * handgeschriebenes Markup derselben Bausteine driftet in Ton, Größe, Abstand
 * und Barrierefreiheit.
 *
 *  M1  Abzeichen — Element mit Klasse `badge` → `<x-status-badge>` (Ton `plain`
 *      = ohne Tonklasse), Tag-Farben `<x-tag-badge>`.
 *  M2  Knopf — `<a>`/`<button>` mit Klasse `btn` → `<x-button>`, nur Symbol
 *      `<x-icon-btn>`.
 *  M3  Detail-Liste — jedes `<dt>`, ob direkt im `<dl>` oder in einer
 *      `<div>`-Zelle → `<x-detail-grid>` mit `<x-detail-grid.row>`
 *      (`layout="cells"` Label über Wert, `layout="split"` Wert am rechten
 *      Rand).
 *  M4  Leerzustand — alleinstehender Satz „Kein…/Noch kein…“ (bzw. Schlüssel
 *      `….empty`/`….none`) in `<p>`/`<div>` → `<x-empty-state>`;
 *      `@empty <tr><td colspan…>` → `<x-table.empty>`.
 *  M5  Kartenoptik — `rounded-box border border-base-300 bg-base-100` ohne
 *      `card` → `<x-card>` (`as`, `padding`).
 *
 * Kein Kandidat, weil die Komponente das Markup nicht trägt: Abzeichen als
 * `<a>`/`<button>`/`<label>`, `btn` an `<summary>`/`<label>`/`<input>`,
 * Hinweisblöcke (`alert`), schwebende Flächen (`dropdown-content`, `absolute`,
 * `fixed`, `menu`, `collapse`, `modal-box`).
 * Eine bewusste Ausnahme trägt `raw-markup-ok: <Grund>` als Blade-Kommentar
 * auf derselben oder der Zeile davor.
 *
 * Eine Baseline gibt es nicht mehr: der Bestand (rund 1.570 Stellen) ist
 * abgearbeitet, jede rohe Stelle ohne Marker ist ein Fehler. PDF, Druck,
 * Mail, Layouts und die Komponenten selbst sind ausgenommen.
 */
final class RawMarkupRuleTest extends TestCase {
    use ScansSourceTree;

    public const RULES = [
        'M1' => 'Abzeichen → <x-status-badge> (tone, size, outline; tone="plain" ohne Tonklasse)',
        'M2' => 'Knopf → <x-button> bzw. <x-icon-btn> (type="submit" ausschreiben, href mit :href binden)',
        'M3' => 'Detail-Liste → <x-detail-grid> mit <x-detail-grid.row> (layout="cells" Label über Wert, layout="split" Wert am rechten Rand)',
        'M4' => 'Leerzustand → <x-empty-state compact> bzw. <x-table.empty>',
        'M5' => 'Kartenoptik → <x-card> (as="…", padding="…")',
    ];

    private const MARKER = 'raw-markup-ok:';

    private const NOT_A_SCREEN = '~^resources/views/(?:legacy|vendor|components|layouts|mail|emails|pdf|print|errors)/|/pdf/|/print/|(?:^|[/_-])pdf\.blade\.php$|(?:^|[/_-])print\.blade\.php$~';

    /** Träger, deren Markup `<x-status-badge>` nicht rendert. */
    private const BADGE_CARRIERS = ['a', 'button', 'summary', 'label', 'input', 'option'];

    /** Schwebende Flächen und Aufklapper sind keine Seitenkarten. */
    private const FLOATING = ['dropdown-content', 'absolute', 'fixed', 'menu', 'collapse', 'modal-box'];

    private const EMPTY_TEXT = '~^(?:Kein|Noch kein|Nichts|Bisher kein)~u';

    private const EMPTY_KEY = '~^(?:[a-z0-9_-]+::)?[a-z0-9_]+(?:\.[a-z0-9_]+)*\.(?:empty|none|nothing|no_data|no_entries|no_results|[a-z0-9_]+_empty)$~';

    /** @var array<string, array<string, list<int>>>|null Datei → Regel → Zeilen */
    private static ?array $found = null;

    public function test_m1_badges_use_the_component(): void {
        $this->assertRule('M1');
    }

    public function test_m2_buttons_use_the_component(): void {
        $this->assertRule('M2');
    }

    public function test_m3_detail_lists_use_the_component(): void {
        $this->assertRule('M3');
    }

    public function test_m4_empty_states_use_the_component(): void {
        $this->assertRule('M4');
    }

    public function test_m5_card_look_uses_the_component(): void {
        $this->assertRule('M5');
    }

    public function test_rules_recognise_raw_markup(): void {
        $hits = fn (string $source): array => array_map(count(...), $this->scan($source));

        $this->assertSame(['M1' => 2], $hits('<span class="badge badge-sm">A</span><div @class([\'badge\', \'badge-success\' => $ok])>B</div>'));
        $this->assertSame([], $hits('<a class="badge badge-ghost" href="#">A</a><x-status-badge>B</x-status-badge>'));
        $this->assertSame(['M2' => 2], $hits('<button class="btn btn-sm">A</button><a href="#" class="btn {{ $tone }}">B</a>'));
        $this->assertSame([], $hits('<summary class="btn btn-xs">A</summary><label class="btn">B</label><x-button class="btn-outline">C</x-button>'));
        $this->assertSame(['M3' => 2], $hits('<dl><dt>A</dt><dd>1</dd><div><dt>B</dt><dd>2</dd></div></dl>'));
        $this->assertSame(['M3' => 1], $hits('<x-card as="dl" class="grid"><div class="sm:col-span-2"><dt>A</dt><dd>1</dd></div></x-card>'));
        $this->assertSame([], $hits('<x-detail-grid layout="cells" :cols="3"><x-detail-grid.row :label="$a" full>1</x-detail-grid.row></x-detail-grid>'));
        $this->assertSame([], $hits("<dl><div>\n{{-- raw-markup-ok: Beispiel --}}\n<dt>A</dt><dd>1</dd></div></dl>"));
        $this->assertSame(['M4' => 3], $hits("<p class=\"text-sm text-muted\">{{ __('Keine Einträge.') }}</p><div>{{ __('demo::demo.orders.empty') }}</div>@forelse (\$a as \$b) @empty\n<tr><td colspan=\"3\">x</td></tr> @endforelse"));
        $this->assertSame([], $hits("<div role=\"alert\" class=\"alert alert-warning\">{{ __('Kein Zugriff.') }}</div><p>{{ __('Keine Sorge:') }} {{ \$text }}</p><p>{{ __('demo::demo.title') }}</p>"));
        $this->assertSame(['M5' => 1], $hits('<section class="rounded-box border border-base-300 bg-base-100 p-4">A</section>'));
        $this->assertSame([], $hits('<div class="card rounded-box border border-base-300 bg-base-100">A</div><ul class="menu rounded-box border border-base-300 bg-base-100">B</ul><div class="rounded-box border border-warning/40 bg-base-100">C</div>'));
        $this->assertSame([], $hits('<div @class([\'rounded-box border\', \'border-base-300 bg-base-100\' => $filled])>A</div>'));
        $this->assertSame([], $hits("{{-- raw-markup-ok: Beispiel --}}\n<span class=\"badge\">A</span> {{-- <span class=\"badge\">B</span> --}}"));
    }

    /**
     * Fundstellen aller Bildschirm-Views je Datei und Regel (für Auswertungen).
     *
     * @return array<string, array<string, int>> Datei → Regel → Anzahl
     */
    public function counts(): array {
        return array_map(static fn (array $rules): array => array_map(count(...), $rules), $this->found());
    }

    private function assertRule(string $rule): void {
        $violations = [];
        foreach ($this->found() as $relative => $rules) {
            $lines = $rules[$rule] ?? [];
            if ($lines !== []) {
                $violations[] = sprintf('%s — %d Stelle(n) (Zeilen %s)', $relative, count($lines), implode(', ', $lines));
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "$rule: " . self::RULES[$rule] . ".\n"
            . "Rohe Stelle — Komponente nutzen; trägt sie das Markup nicht, mit '{{-- " . self::MARKER . " <Grund> --}}' markieren:\n"
            . implode("\n", $violations));
    }

    /** @return array<string, array<string, list<int>>> */
    private function found(): array {
        if (self::$found !== null) {
            return self::$found;
        }
        $found = [];
        foreach ($this->bladeFiles() as $file) {
            $relative = $this->relativePath($file);
            if (preg_match(self::NOT_A_SCREEN, $relative) === 1) {
                continue;
            }
            $hits = $this->scan((string) file_get_contents($file));
            if ($hits !== []) {
                $found[$relative] = $hits;
            }
        }
        ksort($found);

        return self::$found = $found;
    }

    /**
     * @return array<string, list<int>> Regel → Zeilen der Fundstellen
     */
    private function scan(string $original): array {
        $source = $this->stripBladeComments($original);
        // stripBladeComments kürzt die Zeilen: Marker deshalb im Original suchen.
        $originalLines = preg_split('/\r?\n/', $original) ?: [];
        $hits = [];
        $add = function (string $rule, int $line) use (&$hits, $originalLines): void {
            if (str_contains($originalLines[$line - 1] ?? '', self::MARKER) || str_contains($originalLines[$line - 2] ?? '', self::MARKER)) {
                return;
            }
            $hits[$rule][] = $line;
        };

        foreach ($this->openingTags($source) as [$name, $tag, $offset, $end]) {
            if ($name === 'dt') {
                $add('M3', $this->lineOf($source, $offset));

                continue;
            }
            if (str_starts_with($name, 'x-') || str_contains($name, ':')) {
                continue;
            }
            $classes = $this->classTokens($tag);
            if (in_array('badge', $classes, true) && ! in_array($name, self::BADGE_CARRIERS, true)) {
                $add('M1', $this->lineOf($source, $offset));
            }
            if (in_array('btn', $classes, true) && ($name === 'a' || $name === 'button')) {
                $add('M2', $this->lineOf($source, $offset));
            }
            // Kartenoptik nur, wenn sie fest dasteht — eine bedingte Tönung in @class ist eine Zustandsfläche.
            if (! in_array('card', $classes, true) && array_diff(['rounded-box', 'border', 'border-base-300', 'bg-base-100'], $this->classTokens($tag, false)) === []
                && array_intersect($classes, self::FLOATING) === []) {
                $add('M5', $this->lineOf($source, $offset));
            }
            if (($name === 'p' || $name === 'div') && ! in_array('alert', $classes, true) && $this->isEmptySentence($source, $end, $name)) {
                $add('M4', $this->lineOf($source, $offset));
            }
        }

        if (preg_match_all('~@empty\s*<tr\b[^>]*>\s*<td\b[^>]*\bcolspan~', $source, $matches, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[0] as [$match, $offset]) {
                $add('M4', $this->lineOf($source, (int) $offset + (int) strpos($match, '<tr')));
            }
        }
        ksort($hits);

        return $hits;
    }

    /** Besteht der Inhalt des Elements nur aus einem übersetzten Leer-Satz? */
    private function isEmptySentence(string $source, int $tagEnd, string $name): bool {
        if (preg_match('~\G\s*\{\{\s*(?:__|trans)\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'[^{}]*\)\s*\}\}\s*</' . $name . '>~u', $source, $m, 0, $tagEnd + 1) !== 1) {
            return false;
        }

        return preg_match(self::EMPTY_TEXT, $m[1]) === 1 || preg_match(self::EMPTY_KEY, $m[1]) === 1;
    }

    /**
     * Öffnende Tags. Blade-Echos, Direktiven mit Klammern und Anführungszeichen
     * im Tag werden übersprungen, damit ein `>` darin das Tag nicht beendet.
     *
     * @return list<array{0:string,1:string,2:int,3:int}> [Name, Tag-Quelltext, Offset, Offset des `>`]
     */
    private function openingTags(string $source): array {
        $tags = [];
        $length = strlen($source);
        $i = 0;
        while ($i < $length && ($i = strpos($source, '<', $i)) !== false) {
            if (preg_match('~\G<([a-zA-Z][\w.:-]*)~', $source, $m, 0, $i) !== 1) {
                $i++;

                continue;
            }
            $end = $this->tagEnd($source, $i + strlen($m[0]));
            if ($end === null) {
                $i++;

                continue;
            }
            $tags[] = [$m[1], substr($source, $i, $end - $i + 1), $i, $end];
            $i = $end + 1;
        }

        return $tags;
    }

    private function tagEnd(string $source, int $i): ?int {
        $length = strlen($source);
        $quote = null;
        while ($i < $length) {
            $char = $source[$i];
            if ($char === '{' && ($close = match (true) {
                substr($source, $i, 3) === '{!!' => '!!}',
                substr($source, $i, 2) === '{{' => '}}',
                default => null,
            }) !== null) {
                $stop = strpos($source, $close, $i + 2);
                if ($stop === false) {
                    return null;
                }
                $i = $stop + strlen($close);

                continue;
            }
            if ($quote !== null) {
                $quote = $char === $quote ? null : $quote;
                $i++;

                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '@' && preg_match('~\G@[a-zA-Z]+\s*\(~', $source, $m, 0, $i) === 1) {
                $stop = $this->parenEnd($source, $i + strlen($m[0]) - 1);
                if ($stop === null) {
                    return null;
                }
                $i = $stop;
            } elseif ($char === '>') {
                return $i;
            } elseif ($char === '<') {
                return null;
            }
            $i++;
        }

        return null;
    }

    private function parenEnd(string $source, int $open): ?int {
        $depth = 0;
        $quote = null;
        for ($i = $open, $length = strlen($source); $i < $length; $i++) {
            $char = $source[$i];
            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')' && --$depth === 0) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Klassen aus `class="…"` (ohne Blade-Echos) und aus den Zeichenketten von
     * `@class([...])` — mit oder ohne die bedingten (`'…' => $x`).
     *
     * @return list<string>
     */
    private function classTokens(string $tag, bool $conditional = true): array {
        $value = '';
        if (preg_match('~(?<![\w:.@-])class="((?:[^"{]|\{\{.*?\}\}|\{!!.*?!!\}|\{)*)"~s', $tag, $m) === 1) {
            $value .= ' ' . preg_replace('~\{\{.*?\}\}|\{!!.*?!!\}~s', ' ', $m[1]);
        }
        if (preg_match('~@class\s*\(~', $tag, $m, PREG_OFFSET_CAPTURE) === 1) {
            $open = (int) $m[0][1] + strlen($m[0][0]) - 1;
            $close = $this->parenEnd($tag, $open) ?? strlen($tag);
            preg_match_all('~([\'"])((?:\\\\.|(?!\1).)*)\1(\s*=>)?~s', substr($tag, $open, $close - $open), $strings, PREG_SET_ORDER);
            foreach ($strings as $string) {
                if ($conditional || ($string[3] ?? '') === '') {
                    $value .= ' ' . $string[2];
                }
            }
        }

        return preg_split('~\s+~', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
