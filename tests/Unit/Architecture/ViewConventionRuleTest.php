<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ViewConventionRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate für View-Konventionen ohne bisheriges Gate (Vollscan
 * 2026-08-23, Welle 1). Ergänzt TableConventionRuleTest (R1–R4) um:
 *
 *  V1  Von/Bis als zwei Datumsfelder statt <x-date-range> (D10/I6)
 *  V2  handgebautes <h1> in App-Views statt <x-page-toolbar> (D16/I9)
 *  V3  <x-table scroll="flex"> ohne Voll-Höhe-Marker bzw. mit Karten
 *      danach — dann greift der Scroll nicht oder quetscht Inhalt weg (I10)
 *  V4  Erfolgs-Flash in der View, obwohl layouts.app ihn bereits rendert (I4)
 *  V5  <x-pagination> auf Index-Seiten ohne `standing` (I18)
 *  V6  Markup im `:subtitle` von <x-page-toolbar>/<x-index-page> — die
 *      Toolbar gibt den Untertitel zusätzlich als `title`-Attribut aus, wo
 *      ein Tag den Attributwert aufbräche (MVP-820). Markup gehört in den
 *      Standard-Slot darunter.
 *  V7  <x-date-range> in <x-filter-bar> ohne :label="false" — die Leiste
 *      zentriert vertikal, ein Label über der Gruppe hebt Von/Bis aus der
 *      Zeile der Nachbarfelder (2026-09-22, 12 Altfälle bereinigt).
 *
 * Seitenkopf mit Überlaufmenü (MVP-966–970, ux-pattern-katalog §3.10), geprüft
 * im Aktionsslot von <x-page-toolbar>/<x-index-page>, auch in Plugin-Views:
 *
 *  V8  Zurück-Button (arrow_back) — Rückpfeil über back/back-route
 *  V9  zwei oder mehr Ausgabeformate direkt im Slot — <x-action-menu> „Export"
 *  V10 Löschen bzw. tone="error" ohne placement="danger"
 *  V11 Statusabzeichen im Slot — Slot `badges` neben dem Titel
 *  V12 handgebautes dropdown-content (überall) — <x-action-menu>
 *  V13 rohe class="btn"-Elemente im Slot — <x-button>/<x-icon-btn>
 *
 * Altfälle stehen mit Welle-Verweis in den Allow-Listen; neue Views müssen die
 * Konvention erfüllen.
 */
class ViewConventionRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> */
    private const SKIP_PREFIXES = [
        'resources/views/legacy/' => 'Legacy-Modul.',
        'resources/views/vendor/' => 'Fremd-Views.',
        'resources/views/components/' => 'Komponenten definieren die Konventionen.',
        'resources/views/layouts/' => 'Layouts.',
        'resources/views/mail/' => 'Mail-Templates.',
        'resources/views/emails/' => 'Mail-Templates.',
        'resources/views/pdf/' => 'PDF-Templates.',
        'resources/views/errors/' => 'Fehlerseiten.',
    ];

    /** @var array<string, string> V1 */
    private const DATE_RANGE_ALLOW = [
        'resources/views/whistleblowing/public/portal.blade.php' => 'Öffentliches Portal-Layout ohne App-Komponenten (bewusst).',
        'resources/views/rental/calendar.blade.php' => 'Welle 4 (I6): starts_at/ends_at (datetime-local).',
    ];

    /** @var array<string, string> V2 */
    private const HEADING_ALLOW = [
        'resources/views/dashboard/index.blade.php' => 'Begrüßungs-Hero des Dashboards (bewusst, ux-pattern-katalog).',
        'resources/views/chat/index.blade.php' => 'Chat-Kopf mit Kanalwechsel (Eigenlayout).',
        'resources/views/admin/document-design/editor.blade.php' => 'Welle 4 (I9): Editor-Kopf.',
        'resources/views/admin/mail/index.blade.php' => 'Welle 4 (I9).',
        'resources/views/procedures/templates/edit.blade.php' => 'Welle 4 (D16).',
        'resources/views/recipes/menus/index.blade.php' => 'Welle 4 (I9): h1 + Rohkarte → x-page-toolbar/x-card.',
        'resources/views/recipes/menus/show.blade.php' => 'Welle 4 (I9).',
    ];

    /** @var array<string, string> V3 */
    private const SCROLL_FLEX_ALLOW = [];

    /** @var array<string, string> V4 — 29 Altfälle, Welle 4 (I4): Block entfernen, Layout rendert den Flash */
    private const FLASH_ALLOW = [];

    /**
     * @var array<string, string> V5 — zweite, gleichzeitig sichtbare Liste derselben
     *      Seite: die stehende Fußzeile gehört genau einer Liste (<x-pagination>:
     *      ein `standing` je Aufruf), die andere blättert in ihrer Karte.
     */
    private const INLINE_PAGINATION_ALLOW = [
        'app/Plugins/OrgaMax/Resources/views/admin/index.blade.php' => 'Übergebene Aufträge blättern in der Karte; die stehende Fußzeile gehört der Rechnungs-Projektion (Konsolidierungs-Audit 2026-10, vierte Runde).',
    ];

    /**
     * @var array<string, string> V6 — bewusste Ausnahmen brauchen einen Grund,
     *      warum der Untertitel Markup tragen muss statt des Slots darunter.
     */
    private const SUBTITLE_ALLOW = [];

    /** @var array<string, string> V10 — Rot als Alarmfarbe, nicht als Löschen */
    private const DANGER_ALLOW = [
        'resources/views/crisis/show.blade.php' => 'Krise ausrufen ist die Hauptaktion der Seite, keine Löschung.',
        'resources/views/crisis/index.blade.php' => 'Krisenfall anlegen ist die Hauptaktion der Seite.',
    ];

    /** @var array<string, string> V12 — keine Aktionsmenüs, sondern Formulare im Popover */
    private const DROPDOWN_ALLOW = [
        'resources/views/admin/maintenance-windows/index.blade.php' => 'Verlängern mit Dauerfeld im Popover.',
        'resources/views/admin/operations/index.blade.php' => 'Delegieren/Ignorieren mit Begründung im Popover.',
        'resources/views/procedures/runs/show.blade.php' => 'Abbruch mit Pflichtbegründung im Popover.',
    ];

    /** @var array<string, string> V13 — Popover-Formulare im Kopf, ihr Auslöser ist ein <summary> */
    private const RAW_BUTTON_ALLOW = [
        'resources/views/contracts/show.blade.php' => 'Kündigen mit Pflichtbegründung im Popover.',
        'resources/views/asset-finance/show.blade.php' => 'Popover-Formulare mit Pflichtfeldern.',
    ];

    /** @var list<array{0: string, 1: string}> Von→Bis-Namenspaare (Teilstring-Ersetzung) */
    private const RANGE_PAIRS = [
        ['from', 'to'],
        ['start', 'end'],
        ['started', 'ended'],
        ['starts', 'ends'],
        ['begin', 'end'],
        ['von', 'bis'],
    ];

    public function test_views_follow_the_ui_conventions(): void {
        $violations = [];

        foreach ($this->bladeFiles() as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::SKIP_PREFIXES)) {
                continue;
            }

            $source = $this->stripBladeComments((string) file_get_contents($file));
            $isPartial = str_starts_with(basename($relative), '_');
            $isAppView = str_contains($source, "@extends('layouts.app')") || str_contains($source, '<x-index-page') || str_contains($source, '<x-page-shell');

            // V1 — Von/Bis als zwei Datumsfelder.
            if (! $this->isAllowListed($relative, self::DATE_RANGE_ALLOW)) {
                foreach ($this->dateRangePairs($source) as [$from, $to]) {
                    $violations[] = sprintf('%s  V1 %s/%s als zwei Datumsfelder — <x-date-range> nutzen', $relative, $from, $to);
                }
            }

            // V2 — handgebautes <h1> in App-Views.
            if ($isAppView && ! $this->isAllowListed($relative, self::HEADING_ALLOW)
                && preg_match('/<h1\b/', $source, $m, PREG_OFFSET_CAPTURE) === 1) {
                $violations[] = sprintf('%s:%d  V2 <h1> handgebaut — <x-page-toolbar>/<x-index-page> nutzen', $relative, $this->lineOf($source, (int) $m[0][1]));
            }

            // V3 — scroll="flex" nur mit Voll-Höhe-Marker und als letztes Element.
            if (! $isPartial && str_contains($source, 'scroll="flex"') && ! $this->isAllowListed($relative, self::SCROLL_FLEX_ALLOW)) {
                if (! str_contains($source, "@include('partials.page-fill')") && ! str_contains($source, "@section('main-class'")) {
                    $violations[] = sprintf('%s  V3 scroll="flex" ohne @include(\'partials.page-fill\') — Voll-Höhe greift nicht', $relative);
                }
                $tail = substr($source, (int) strrpos($source, '</x-table>'));
                if (preg_match('/<x-card\b/', $tail) === 1) {
                    $violations[] = sprintf('%s  V3 <x-card> nach der scroll="flex"-Tabelle — Inhalt vor die Tabelle oder scroll entfernen', $relative);
                }
            }

            // V4 — doppelter Erfolgs-Flash.
            if (str_contains($source, "@extends('layouts.app')") && ! $this->isAllowListed($relative, self::FLASH_ALLOW)
                && preg_match('/session\(\s*[\'"]success[\'"]\s*\)/', $source, $m, PREG_OFFSET_CAPTURE) === 1) {
                $violations[] = sprintf('%s:%d  V4 session(\'success\') in der View — layouts.app rendert den Flash bereits', $relative, $this->lineOf($source, (int) $m[0][1]));
            }

            // V5 — Index-Seiten paginieren stehend.
            if (str_ends_with($relative, 'index.blade.php')
                && ! $this->isAllowListed($relative, self::INLINE_PAGINATION_ALLOW)
                && preg_match('/<x-pagination\b[^>]*>/', $source, $m, PREG_OFFSET_CAPTURE) === 1
                && ! str_contains($m[0][0], 'standing')) {
                $violations[] = sprintf('%s:%d  V5 <x-pagination> ohne standing auf einer Index-Seite', $relative, $this->lineOf($source, (int) $m[0][1]));
            }

            // V6 — Untertitel ist Klartext. Blade-Direktiven im Slot sind unkritisch:
            // sie sind vor dem Rendern aufgelöst, übrig bleibt Text.
            if (! $this->isAllowListed($relative, self::SUBTITLE_ALLOW)) {
                foreach ($this->subtitleMarkup($source) as [$offset, $snippet]) {
                    $violations[] = sprintf(
                        '%s:%d  V6 Markup im Untertitel (%s) — in den Standard-Slot der Toolbar verschieben',
                        $relative,
                        $this->lineOf($source, $offset),
                        $snippet,
                    );
                }
            }

            // V7 — Von/Bis in der Filterleiste ohne :label="false". Die Leiste
            // zentriert ihre Felder vertikal; ein Label über der Gruppe hebt die
            // Datumsfelder aus der Zeile der Nachbarfelder.
            if (preg_match_all('~<x-filter-bar\b.*?</x-filter-bar>~s', $source, $bars, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($bars[0] as [$bar, $barOffset]) {
                    if (preg_match_all('~<x-date-range\b(?:[^>]|->)*?>~s', $bar, $ranges, PREG_OFFSET_CAPTURE) === 0) {
                        continue;
                    }
                    foreach ($ranges[0] as [$tag, $tagOffset]) {
                        if (! str_contains($tag, ':label="false"')) {
                            $violations[] = sprintf('%s:%d  V7 <x-date-range> in der Filterleiste ohne :label="false" — das Label hebt Von/Bis aus der Zeile', $relative, $this->lineOf($source, (int) $barOffset + (int) $tagOffset));
                        }
                    }
                }
            }
        }

        sort($violations);

        $this->assertSame([], $violations, "View-Konvention verletzt (ux-pattern-katalog / Memory-Konventionen):\n\n" . implode("\n", $violations));
    }

    public function test_page_toolbar_actions_follow_the_overflow_conventions(): void {
        $violations = [];

        foreach ($this->bladeFiles() as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::SKIP_PREFIXES)) {
                continue;
            }
            $source = $this->stripBladeComments((string) file_get_contents($file));

            // V12 — handgebaute Dropdowns, auch außerhalb des Seitenkopfs.
            if (! $this->isAllowListed($relative, self::DROPDOWN_ALLOW)
                && preg_match('/\bdropdown-content\b/', $source, $m, PREG_OFFSET_CAPTURE) === 1) {
                $violations[] = sprintf('%s:%d  V12 handgebautes Dropdown — <x-action-menu> nutzen', $relative, $this->lineOf($source, (int) $m[0][1]));
            }

            foreach ($this->toolbarActionSlots($source) as [$slot, $offset]) {
                $at = fn(int $inner): int => $this->lineOf($source, $offset + $inner);
                // Nur die Ebene des Slots: Inhalte von Aktionsmenüs sind gebündelt.
                $flat = preg_replace_callback('~<x-action-menu\b.*?</x-action-menu>~s', static fn(array $m): string => str_repeat(' ', strlen($m[0])), $slot) ?? $slot;

                if (preg_match('/icon="arrow_back"/', $flat, $m, PREG_OFFSET_CAPTURE) === 1) {
                    $violations[] = sprintf('%s:%d  V8 Zurück-Button im Seitenkopf — back-route/back an der Toolbar setzen', $relative, $at((int) $m[0][1]));
                }

                if (preg_match_all("~'(?:export|format)'\\s*=>\\s*'(?:csv|xlsx|json|xml)'~", $flat, $formats, PREG_OFFSET_CAPTURE) >= 2) {
                    $violations[] = sprintf('%s:%d  V9 mehrere Ausgabeformate im Seitenkopf — <x-action-menu icon="download" :label="__(\'Export\')">', $relative, $at((int) $formats[0][0][1]));
                }

                if (! $this->isAllowListed($relative, self::DANGER_ALLOW)) {
                    // Ein als danger markiertes Popover zählt als Ganzes, sein Submit-Knopf nicht einzeln.
                    $withoutPopovers = preg_replace_callback('~<details\\b[^>]*data-toolbar-placement="danger".*?</details>~s', static fn(array $m): string => str_repeat(' ', strlen($m[0])), $slot) ?? $slot;
                    foreach ($this->componentTags($withoutPopovers) as [$tag, $tagOffset]) {
                        $destructive = preg_match('/\bicon="delete(?:_forever)?"|(?<![:\w-])tone="error"/', $tag) === 1;
                        if ($destructive && ! str_contains($tag, 'placement="danger"')) {
                            $violations[] = sprintf('%s:%d  V10 destruktive Aktion ohne placement="danger"', $relative, $at($tagOffset));
                        }
                    }
                }

                $withoutButtons = preg_replace_callback('~<(x-icon-btn|x-button)\b.*?</\1>~s', static fn(array $m): string => str_repeat(' ', strlen($m[0])), $flat) ?? $flat;
                if (preg_match('/<x-status-badge\b|class="badge\b/', $withoutButtons, $m, PREG_OFFSET_CAPTURE) === 1) {
                    $violations[] = sprintf('%s:%d  V11 Abzeichen im Aktionsslot — Slot badges neben dem Titel nutzen', $relative, $at((int) $m[0][1]));
                }

                if (! $this->isAllowListed($relative, self::RAW_BUTTON_ALLOW)
                    && preg_match('/<(?:a|button|summary|label)\b(?:[^>"]|"[^"]*")*class="[^"]*\bbtn\b/', $flat, $m, PREG_OFFSET_CAPTURE) === 1) {
                    $violations[] = sprintf('%s:%d  V13 roher Button im Seitenkopf — <x-button>/<x-icon-btn> nutzen', $relative, $at((int) $m[0][1]));
                }
            }
        }

        sort($violations);

        $this->assertSame([], $violations, "Seitenkopf-Konvention verletzt (ux-pattern-katalog §3.10):\n\n" . implode("\n", $violations));
    }

    /**
     * Aktionsslots der Seitenköpfe. Bei <x-index-page> zählt der erste Slot nur,
     * wenn davor keine andere Komponente mit Aktionsslot öffnet (Karte,
     * Sammelaktionsleiste, Dialog, Kopfkarte) — sonst gehört er ihr.
     *
     * @return list<array{0: string, 1: int}> Slot-Inhalt und Offset im Quelltext
     */
    private function toolbarActionSlots(string $source): array {
        $slots = [];
        if (preg_match_all('~<(x-page-toolbar|x-index-page)\b~', $source, $heads, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === 0) {
            return $slots;
        }
        foreach ($heads as $head) {
            $from = (int) $head[0][1];
            $start = strpos($source, '<x-slot:actions>', $from);
            if ($start === false) {
                continue;
            }
            if ($head[1][0] === 'x-index-page') {
                $foreign = preg_match('~<x-(?:card|bulk-toolbar|modal|entity-header)\\b~', substr($source, $from, $start - $from)) === 1;
            } else {
                $close = strpos($source, '</x-page-toolbar>', $from);
                $foreign = $close !== false && $close < $start;
            }
            if ($foreign) {
                continue;
            }
            $end = strpos($source, '</x-slot:actions>', $start);
            if ($end !== false) {
                $slots[] = [substr($source, $start, $end - $start), $start];
            }
        }

        return $slots;
    }

    /**
     * Öffnende Tags von <x-icon-btn>/<x-button>, Anführungszeichen beachtet.
     *
     * @return list<array{0: string, 1: int}>
     */
    private function componentTags(string $slot): array {
        preg_match_all('~<x-(?:icon-btn|button)\b(?:[^>"]|"[^"]*")*>~', $slot, $tags, PREG_OFFSET_CAPTURE);

        return array_map(static fn(array $t): array => [$t[0], (int) $t[1]], $tags[0]);
    }

    /**
     * Stellen, an denen der Untertitel ein Tag trägt — als Slot oder als Attribut.
     *
     * @return list<array{0: int, 1: string}> Offset und das erste gefundene Tag
     */
    private function subtitleMarkup(string $source): array {
        $found = [];

        if (preg_match_all('~<x-slot:subtitle>(.*?)</x-slot:subtitle>~s', $source, $slots, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) > 0) {
            foreach ($slots as $slot) {
                if (preg_match('~<[a-zA-Z/]~', $slot[1][0], $tag, PREG_OFFSET_CAPTURE) === 1) {
                    $found[] = [(int) $slot[1][1] + (int) $tag[0][1], $this->tagName($slot[1][0], (int) $tag[0][1])];
                }
            }
        }

        if (preg_match_all('~\bsubtitle="([^"]*)"~', $source, $attrs, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) > 0) {
            foreach ($attrs as $attr) {
                if (preg_match('~<[a-zA-Z/]~', $attr[1][0], $tag, PREG_OFFSET_CAPTURE) === 1) {
                    $found[] = [(int) $attr[1][1] + (int) $tag[0][1], $this->tagName($attr[1][0], (int) $tag[0][1])];
                }
            }
        }

        return $found;
    }

    /** Der Tag-Name ab $offset, für eine lesbare Fundstelle. */
    private function tagName(string $haystack, int $offset): string {
        return preg_match('~<(/?[a-zA-Z][\w:.-]*)~', substr($haystack, $offset, 40), $m) === 1 ? '<' . $m[1] . '>' : '<…>';
    }

    /**
     * Datumsfeld-Namen (rohe <input type="date|datetime-local"> und
     * <x-input-field type="date|datetime-local">) und ihre Von/Bis-Paare.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function dateRangePairs(string $source): array {
        $names = [];
        if (preg_match_all('/<(?:input|x-input-field)\b[^>]*>/s', $source, $tags) > 0) {
            foreach ($tags[0] as $tag) {
                if (preg_match('/\btype="(?:date|datetime-local)"/', $tag) !== 1) {
                    continue;
                }
                if (preg_match('/\bname="([a-z0-9_\[\]]+)"/i', $tag, $n) === 1) {
                    $names[$n[1]] = true;
                }
            }
        }

        $pairs = [];
        foreach (array_keys($names) as $name) {
            foreach (self::RANGE_PAIRS as [$from, $to]) {
                if (! str_contains($name, $from)) {
                    continue;
                }
                $partner = str_replace($from, $to, $name);
                if ($partner !== $name && isset($names[$partner])) {
                    $pairs[$name . '/' . $partner] = [$name, $partner];
                }
            }
        }

        return array_values($pairs);
    }
}
