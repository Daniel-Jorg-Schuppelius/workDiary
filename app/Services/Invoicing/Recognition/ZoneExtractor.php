<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZoneExtractor.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Recognition;

use CommonToolkit\Enums\CountryCode;
use CommonToolkit\Helper\Data\{AmountTokenizer, StringHelper};
use CommonToolkit\Helper\Data\{DateHelper, NumberHelper, VatNumberHelper};
use PDFToolkit\Entities\WordBox;

/**
 * Wert einer Zone (Phase 151): Wörter, deren Mittelpunkt in der Zone liegt,
 * in Leserichtung (Zeile, dann x) verbunden und je Feldart geparst.
 *
 * Seit Phase 155 kennt eine Zone ihren Anker: die Beschriftungswörter, neben
 * denen der Wert steht („Gesamt EUR inkl. MwSt.“). Dann wird der Anker als
 * Wortfolge einer Zeile auf den Seiten des Bereichs gesucht und das Kästchen
 * am gespeicherten Versatz dorthin verschoben — ohne Rückfall auf feste
 * Koordinaten (E1). Das Kästchen bleibt, wie es gezeichnet wurde; die Feldart
 * holt den Wert aus seinem Text (letzter Betrag, erstes Datum, erste Kennung),
 * ein optionales Zeilenmuster liest ihn stattdessen per regulärem Ausdruck
 * aus dem Text im Kästchen, Zeile für Zeile durch Zeilenumbruch getrennt.
 */
final class ZoneExtractor {
    /** Anteil der Worthöhe, innerhalb dessen zwei Wörter eine Zeile bilden */
    private const ROW_TOLERANCE = 0.6;

    private const MARGIN_X = 0.01;

    private const MARGIN_Y = 0.004;

    public const PATTERN_MAX_LENGTH = 200;

    /**
     * @param  array<string, mixed>  $zone
     * @param  list<WordBox>  $boxes
     */
    public function rawText(array $zone, array $boxes): string {
        return $this->firstHit($zone, $boxes)['raw'] ?? '';
    }

    /**
     * @param  array<string, mixed>  $zone
     * @param  list<WordBox>  $boxes
     */
    public function extract(array $zone, array $boxes): ?string {
        return $this->firstHit($zone, $boxes)['value'] ?? null;
    }

    /**
     * Fundstellen der Zone im Bereich: ohne Anker die feste Lage je Seite des
     * Bereichs, mit Anker eine je Ankervorkommen.
     *
     * @param  array<string, mixed>  $zone
     * @param  list<WordBox>  $boxes
     * @return list<array{page: int, raw: string, value: ?string, anchor_box: ?array{x: float, y: float, w: float, h: float}}>
     */
    public function hits(array $zone, array $boxes): array {
        $parse = (string) ($zone['parse'] ?? ZoneFields::parseOf((string) ($zone['field'] ?? '')));
        $pattern = self::patternOf($zone);
        $x = (float) ($zone['x'] ?? 0);
        $y = (float) ($zone['y'] ?? 0);
        $w = (float) ($zone['w'] ?? 0);
        $h = (float) ($zone['h'] ?? 0);
        $anchor = self::anchorTokens((string) ($zone['anchor'] ?? ''));
        $anchorBox = is_array($zone['anchor_box'] ?? null) ? $zone['anchor_box'] : null;
        $hits = [];

        if ($anchor === [] || $anchorBox === null) {
            foreach ($this->pagesFor($zone, $boxes) as $page) {
                $raw = self::text($this->within($boxes, $page, $x, $y, $w, $h));
                $value = $raw === '' ? null : ($pattern !== null ? self::captured($pattern, $raw, $parse) : self::parse($raw, $parse));
                $hits[] = ['page' => $page, 'raw' => $raw, 'value' => $value, 'anchor_box' => null];
            }

            return $hits;
        }

        $dx = $x - (float) ($anchorBox['x'] ?? 0);
        $dy = $y - (float) ($anchorBox['y'] ?? 0);
        foreach ($this->pagesFor($zone, $boxes) as $page) {
            foreach ($this->rows($boxes, $page) as $row) {
                foreach (self::occurrences($row, $anchor) as $words) {
                    $found = self::bounds($words);
                    $raw = self::text($this->within($boxes, $page, $found['x'] + $dx, $found['y'] + $dy, $w, $h));
                    $value = $raw === '' ? null : ($pattern !== null ? self::captured($pattern, $raw, $parse) : self::parse($raw, $parse));
                    $hits[] = ['page' => $page, 'raw' => $raw, 'value' => $value, 'anchor_box' => $found];
                }
            }
        }

        return $hits;
    }

    /**
     * Beschriftung und Wert eines gezeichneten Rechtecks trennen (Phase 155,
     * E2): Wörter vor dem ersten Wort mit Ziffer sind der Anker, der Wert
     * folgt der Feldart (Betrag: letzter Betrag samt Währung, Datum: erstes
     * Datum, Kennung: erstes Wort mit Ziffer, USt-IdNr.: alle Wörter mit
     * Ziffer). Ohne Beschriftungswörter bleibt die Zone fest.
     *
     * @param  array<string, mixed>  $zone
     * @param  list<WordBox>  $boxes
     * @return array{anchor: ?string, anchor_box: ?array{x: float, y: float, w: float, h: float}, value_box: ?array{x: float, y: float, w: float, h: float}, raw: string, value: ?string}
     */
    public function split(array $zone, array $boxes): array {
        $parse = (string) ($zone['parse'] ?? ZoneFields::parseOf((string) ($zone['field'] ?? '')));
        $words = $this->within($boxes, (int) ($zone['page'] ?? 1), (float) ($zone['x'] ?? 0), (float) ($zone['y'] ?? 0), (float) ($zone['w'] ?? 0), (float) ($zone['h'] ?? 0));
        $raw = self::text($words);
        $none = ['anchor' => null, 'anchor_box' => null, 'value_box' => null, 'raw' => $raw, 'value' => $raw === '' ? null : self::parse($raw, $parse)];
        $firstDigit = null;
        foreach ($words as $index => $word) {
            if (preg_match('/\d/', $word->text) === 1) {
                $firstDigit = $index;
                break;
            }
        }
        if ($firstDigit === null || $firstDigit === 0) {
            return $none;
        }
        $valueWords = self::valueWords(array_slice($words, $firstDigit), $parse);
        if ($valueWords === []) {
            return $none;
        }
        $anchorWords = array_slice($words, 0, $firstDigit);
        $value = self::parse($raw, $parse);

        return [
            'anchor' => self::join($anchorWords),
            'anchor_box' => self::bounds($anchorWords),
            'value_box' => self::bounds($valueWords, self::MARGIN_X, self::MARGIN_Y),
            'raw' => $raw,
            'value' => $value,
        ];
    }

    /** Größte Lücke zwischen zwei Wörtern derselben Beschriftung (Anteil der Seitenbreite); eine Spaltenlücke ist breiter. */
    private const WORD_GAP = 0.02;

    /**
     * Beschriftungswörter links eines Wertes in derselben Zeile (für gelernte
     * Zonen): zusammenhängend, ohne Ziffern, höchstens sechs Wörter. Eine
     * Spaltenlücke beendet die Beschriftung — sonst zöge „Zahlbar: 8 Tage nach
     * Rechnungsdatum netto“ in den Anker von „Gesamt EUR inkl. MwSt.“ hinein.
     *
     * @param  list<WordBox>  $boxes
     * @return list<WordBox> in Leserichtung
     */
    public function anchorWordsLeftOf(WordBox $value, array $boxes): array {
        $row = array_values(array_filter($boxes, static fn (WordBox $box): bool => $box->page === $value->page && $box->x + $box->w <= $value->x + 1e-6 && self::sameRow($box, $value)));
        usort($row, static fn (WordBox $a, WordBox $b): int => $b->x <=> $a->x);
        $anchor = [];
        $edge = $value->x;
        foreach ($row as $word) {
            $gap = $edge - ($word->x + $word->w);
            if ($gap > ($anchor === [] ? 0.45 : self::WORD_GAP) || preg_match('/\d/', $word->text) === 1 || count($anchor) >= 6) {
                break;
            }
            $anchor[] = $word;
            $edge = $word->x;
        }

        return array_reverse($anchor);
    }

    /** Text einer Zone in den Wert des Feldes: Datum → Y-m-d, Betrag → Dezimal mit Punkt, USt-IdNr. normalisiert, Kennung mit Ziffer. */
    public static function parse(string $raw, string $parse): ?string {
        $raw = trim($raw);

        return match ($parse) {
            'date' => preg_match('/\d{1,2}[.\/-]\d{1,2}[.\/-]\d{2,4}|\d{4}-\d{2}-\d{2}/', $raw, $m) === 1
                ? DateHelper::parseWithCountryPreference($m[0], CountryCode::Germany)?->format('Y-m-d')
                : null,
            'money' => self::money($raw),
            'vat_id' => self::vatId($raw),
            'id' => self::identifier($raw),
            default => mb_substr(preg_replace('/^[\s:#]+|[\s:;,]+$/u', '', $raw) ?? $raw, 0, 191),
        };
    }

    /** Zeilenmuster prüfen (E3): PCRE-Körper ohne Begrenzer, höchstens 200 Zeichen, muss übersetzbar sein. */
    public static function isValidPattern(string $pattern): bool {
        $pattern = trim($pattern);
        if ($pattern === '' || mb_strlen($pattern) > self::PATTERN_MAX_LENGTH) {
            return false;
        }

        return @preg_match('~' . str_replace('~', '\~', $pattern) . '~u', '') !== false;
    }

    /** Vergleichsschlüssel eines Ankers: normalisierte Wörter, durch Leerzeichen getrennt. */
    public static function anchorKey(string $anchor): string {
        return implode(' ', self::anchorTokens($anchor));
    }

    /** @return list<string> normalisierte Wörter des Ankers */
    private static function anchorTokens(string $anchor): array {
        $tokens = [];
        foreach (StringHelper::words($anchor) as $word) {
            $token = self::normalizeWord($word);
            if ($token !== '') {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    private static function normalizeWord(string $word): string {
        return mb_strtolower(trim($word, " \t.,;:()[]|-/"));
    }

    /** @param  array<string, mixed>  $zone */
    private static function patternOf(array $zone): ?string {
        $pattern = trim((string) ($zone['pattern'] ?? ''));

        return self::isValidPattern($pattern) ? str_replace('~', '\~', $pattern) : null;
    }

    private static function captured(string $pattern, string $text, string $parse): ?string {
        if (preg_match('~' . $pattern . '~u', $text, $matches) !== 1) {
            return null;
        }
        $captured = $matches[1] ?? $matches[0];

        return $captured === '' ? null : self::parse($captured, $parse);
    }

    /**
     * Seiten, auf denen die Zone gilt: `page` nur die gespeicherte Seite, `first` die erste, `last` die letzte, `any` alle.
     *
     * @param  array<string, mixed>  $zone
     * @param  list<WordBox>  $boxes
     * @return list<int>
     */
    private function pagesFor(array $zone, array $boxes): array {
        $pages = array_values(array_unique(array_map(static fn (WordBox $box): int => $box->page, $boxes)));
        sort($pages);
        $scope = (string) ($zone['scope'] ?? 'page');

        return match ($scope) {
            'first' => $pages === [] ? [1] : [$pages[0]],
            'last' => $pages === [] ? [1] : [$pages[count($pages) - 1]],
            'any' => $pages === [] ? [1] : $pages,
            default => [(int) ($zone['page'] ?? 1)],
        };
    }

    /**
     * @param  list<WordBox>  $boxes
     * @return list<WordBox> in Leserichtung
     */
    private function within(array $boxes, int $page, float $x, float $y, float $w, float $h): array {
        $hits = array_values(array_filter($boxes, static fn (WordBox $box): bool => $box->page === $page && $box->centerWithin($x, $y, $w, $h)));
        usort($hits, static fn (WordBox $a, WordBox $b): int => self::sameRow($a, $b) ? $a->x <=> $b->x : $a->centerY() <=> $b->centerY());

        return $hits;
    }

    /**
     * Zeilen einer Seite: Wörter mit annähernd gleicher Mitte, je Zeile nach x sortiert.
     *
     * @param  list<WordBox>  $boxes
     * @return list<list<WordBox>>
     */
    private function rows(array $boxes, int $page): array {
        $words = array_values(array_filter($boxes, static fn (WordBox $box): bool => $box->page === $page));
        usort($words, static fn (WordBox $a, WordBox $b): int => $a->centerY() <=> $b->centerY());
        $rows = [];
        foreach ($words as $word) {
            $last = $rows === [] ? null : $rows[count($rows) - 1];
            if ($last !== null && self::sameRow($last[0], $word)) {
                $rows[count($rows) - 1][] = $word;
            } else {
                $rows[] = [$word];
            }
        }
        foreach ($rows as &$row) {
            usort($row, static fn (WordBox $a, WordBox $b): int => $a->x <=> $b->x);
        }
        unset($row);

        return $rows;
    }

    private static function sameRow(WordBox $a, WordBox $b): bool {
        return abs($a->centerY() - $b->centerY()) < max($a->h, $b->h) * self::ROW_TOLERANCE;
    }

    /**
     * Vorkommen der Ankerwörter als zusammenhängende Folge in einer Zeile.
     *
     * @param  list<WordBox>  $row
     * @param  list<string>  $anchor
     * @return list<list<WordBox>>
     */
    private static function occurrences(array $row, array $anchor): array {
        $tokens = array_map(static fn (WordBox $box): string => self::normalizeWord($box->text), $row);
        $count = count($anchor);
        $found = [];
        for ($start = 0; $start + $count <= count($tokens); $start++) {
            if (array_slice($tokens, $start, $count) === $anchor) {
                $found[] = array_slice($row, $start, $count);
            }
        }

        return $found;
    }

    /**
     * @param  list<WordBox>  $words
     * @return list<WordBox>
     */
    private static function valueWords(array $words, string $parse): array {
        if ($parse === 'money') {
            $index = null;
            foreach ($words as $i => $word) {
                if (ValueWords::isAmount($word->text)) {
                    $index = $i;
                }
            }
            if ($index === null) {
                return [];
            }
            $value = [$words[$index]];
            // Währung direkt hinter dem Betrag gehört zum Wert („1.190,00 €“).
            $next = $words[$index + 1] ?? null;
            if ($next !== null && preg_match('/\d/', $next->text) !== 1 && mb_strlen($next->text) <= 4) {
                $value[] = $next;
            }

            return $value;
        }
        if ($parse === 'date') {
            foreach ($words as $word) {
                if (ValueWords::isDate($word->text)) {
                    return [$word];
                }
            }

            return [];
        }
        if ($parse === 'vat_id') {
            return array_values(array_filter($words, static fn (WordBox $word): bool => preg_match('/\d/', $word->text) === 1));
        }

        return [$words[0]];
    }

    /**
     * @param  list<WordBox>  $words
     * @return array{x: float, y: float, w: float, h: float}
     */
    public static function bounds(array $words, float $marginX = 0.0, float $marginY = 0.0): array {
        $x0 = 1.0;
        $y0 = 1.0;
        $x1 = 0.0;
        $y1 = 0.0;
        foreach ($words as $word) {
            $x0 = min($x0, $word->x);
            $y0 = min($y0, $word->y);
            $x1 = max($x1, $word->x + $word->w);
            $y1 = max($y1, $word->y + $word->h);
        }
        if ($words === []) {
            $x0 = $y0 = 0.0;
        }
        $clamp = static fn (float $v): float => max(0.0, min(1.0, $v));

        return ['x' => $clamp($x0 - $marginX), 'y' => $clamp($y0 - $marginY), 'w' => $clamp($x1 - $x0 + 2 * $marginX), 'h' => $clamp($y1 - $y0 + 2 * $marginY)];
    }

    /** @param  list<WordBox>  $words */
    private static function join(array $words): string {
        return trim(implode(' ', array_map(static fn (WordBox $box): string => $box->text, $words)));
    }

    /**
     * Text der Wörter in Leserichtung: Wörter einer Zeile durch Leerzeichen, Zeilen durch Zeilenumbruch getrennt —
     * so greift ein Zeilenmuster je Zeile („.“ überspringt keinen Umbruch).
     *
     * @param  list<WordBox>  $words  bereits in Leserichtung
     */
    private static function text(array $words): string {
        $lines = [];
        $current = [];
        $previous = null;
        foreach ($words as $word) {
            if ($previous !== null && ! self::sameRow($previous, $word)) {
                $lines[] = implode(' ', $current);
                $current = [];
            }
            $current[] = $word->text;
            $previous = $word;
        }
        if ($current !== []) {
            $lines[] = implode(' ', $current);
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @param  array<string, mixed>  $zone
     * @param  list<WordBox>  $boxes
     * @return array{page: int, raw: string, value: ?string, anchor_box: ?array{x: float, y: float, w: float, h: float}}|null
     */
    private function firstHit(array $zone, array $boxes): ?array {
        $hits = $this->hits($zone, $boxes);
        foreach ($hits as $hit) {
            if ($hit['value'] !== null) {
                return $hit;
            }
        }

        return $hits[0] ?? null;
    }

    /**
     * Der letzte Betrag im Text — Beträge sind rechtsbündig; einer vor einem Prozentzeichen ist ein Satz,
     * kein Betrag (L4). Derselbe Leser wie in der Heuristik (`AmountTokenizer`, DE/EN/CH-Schreibweise),
     * damit Vorlage und Heuristik denselben Brutto lesen (Konsolidierungs-Audit 2026-10-21, ko-02).
     */
    private static function money(string $raw): ?string {
        $tokens = [];
        foreach (AmountTokenizer::tokens($raw) as $token) {
            $tokens[] = $token;
        }
        foreach (array_reverse($tokens) as $token) {
            if (preg_match('/^\s*%/u', mb_substr($raw, $token->end)) === 1) {
                continue;
            }

            return NumberHelper::toUSFormat((float) $token->value, 2);
        }

        return null;
    }

    /** Erstes Wort mit Ziffer; ein Datum ist keine Kennung. */
    private static function identifier(string $raw): ?string {
        foreach (StringHelper::words($raw) as $word) {
            $word = trim($word, " \t:;,#");
            if ($word === '' || preg_match('/\d/', $word) !== 1) {
                continue;
            }

            return ValueWords::isDate($word) ? null : mb_substr($word, 0, 64);
        }

        return null;
    }

    /**
     * Die Zone darf die Beschriftung enthalten („USt-IdNr. DE 278 737 004“): erster Kandidat mit gültigem Format.
     * Kandidaten überlappen („MWST CHE-…“ prüft auch „CHE-…“). Ein Rückfall gilt nur für Länder, deren Format
     * das Toolkit nicht kennt — was `isVatId` für ein bekanntes Land ablehnt, ist keine USt-IdNr. (tk-06).
     */
    private static function vatId(string $raw): ?string {
        preg_match_all('/(?=\b([A-Z]{2}[\s.\-]*(?:[A-Z0-9][\s.\-]*){6,14}))/u', $raw, $matches);
        $candidates = array_map(VatNumberHelper::normalize(...), $matches[1]);
        foreach ($candidates as $vat) {
            if (VatNumberHelper::isVatId($vat)) {
                return $vat;
            }
        }
        $supported = VatNumberHelper::getSupportedCountries();
        foreach ($candidates as $vat) {
            if (preg_match('/^([A-Z]{2})[A-Z]?\d[A-Z0-9]{1,12}$/', $vat, $match) === 1 && ! in_array($match[1], $supported, true)) {
                return $vat;
            }
        }

        return null;
    }
}
