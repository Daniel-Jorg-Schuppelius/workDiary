<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceBoundaryDetector.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Split;

use App\Services\Invoicing\InvoicePdfImportService;
use App\Support\ReferenceTokens;
use CommonToolkit\Helper\Data\{BankHelper, VatNumberHelper};
use PDFToolkit\Entities\WordBox;

/**
 * Vorschlag der Rechnungsgrenzen in einer Sammel-PDF (Feature 208, MVP-1662).
 *
 * Jede Seite ab der zweiten bekommt Punkte dafür, dass dort eine neue
 * Rechnung beginnt; ab {@see THRESHOLD} ist sie eine Startseite:
 *
 * - „Seite 1 von n“ (+3), „Seite k von n“ mit k > 1 spricht dagegen (−3);
 * - eine andere Rechnungsnummer als die der laufenden Rechnung (+2),
 *   dieselbe Nummer spricht dagegen (−2);
 * - ein neuer Absender: USt-IdNr. bzw. IBAN der Seite und der laufenden
 *   Rechnung haben nichts gemeinsam (+2) — die eigenen zählen nie;
 * - die Vorseite schloss mit einem Summenblock (+1);
 * - ein anderer Briefkopf im oberen Seitenfünftel (+1).
 *
 * Ein einzelnes schwaches Zeichen (Summenblock, Briefkopf) reicht nie. Das
 * Ergebnis ist ein Vorschlag — der Mensch bestätigt oder verschiebt ihn.
 * Reine Textauswertung ohne Werkzeugaufruf; die Seiten liefert die Wortbox-
 * Quelle (Textlayer oder OCR).
 */
final class InvoiceBoundaryDetector {
    public const int THRESHOLD = 2;

    /** Anteil der Seitenhöhe, der als Briefkopf gilt. */
    private const float HEADER_ZONE = 0.2;

    /** Höchstens so viel Gemeinsamkeit (Jaccard) haben zwei verschiedene Briefköpfe. */
    private const float HEADER_SIMILARITY = 0.2;

    private const int HEADER_MIN_TOKENS = 3;

    private const string PAGE_MARKER = '/\b(?:Seite|Page|Blatt)\s*(\d{1,3})\s*(?:von|of|\/)\s*(\d{1,3})\b/iu';

    private const string VAT_CANDIDATE = '/\b[A-Z]{2}\s?[0-9A-Z][0-9A-Z ]{6,13}\b/';

    public function __construct(private readonly InvoicePdfImportService $import) {}

    /**
     * @param  array<int, string>  $texts  Seitentext je Seite (1-basiert)
     * @param  array<int, string>  $headers  Text des Briefkopfs je Seite
     * @param  list<string>  $own  eigene USt-IdNr. und IBANs (normalisiert)
     * @return array{starts: list<int>, signals: array<int, list<string>>}
     */
    public function propose(array $texts, array $headers = [], array $own = []): array {
        ksort($texts);
        $pages = array_keys($texts);
        if ($pages === []) {
            return ['starts' => [], 'signals' => []];
        }

        $first = $pages[0];
        $starts = [$first];
        $signals = [];
        $current = $this->facts($texts[$first], $headers[$first] ?? '', $own);
        $invoice = ['number' => $current['number'], 'ids' => $current['ids'], 'header' => $current['header']];
        $previousTotal = $current['total'];

        foreach (array_slice($pages, 1) as $page) {
            $facts = $this->facts($texts[$page], $headers[$page] ?? '', $own);
            $score = 0;
            $reasons = [];

            if ($facts['marker'] !== null) {
                if ($facts['marker'][0] === 1) {
                    $score += 3;
                    $reasons[] = 'page_one';
                } else {
                    $score -= 3;
                    $reasons[] = 'continued';
                }
            }
            if ($facts['number'] !== null && $invoice['number'] !== null) {
                if ($facts['number'] !== $invoice['number']) {
                    $score += 2;
                    $reasons[] = 'new_number';
                } else {
                    $score -= 2;
                    $reasons[] = 'same_number';
                }
            }
            if ($facts['ids'] !== [] && $invoice['ids'] !== [] && array_intersect($facts['ids'], $invoice['ids']) === []) {
                $score += 2;
                $reasons[] = 'new_sender';
            }
            if ($previousTotal) {
                $score += 1;
                $reasons[] = 'after_total';
            }
            if (self::headerChanged($invoice['header'], $facts['header'])) {
                $score += 1;
                $reasons[] = 'new_letterhead';
            }

            if ($score >= self::THRESHOLD) {
                $starts[] = $page;
                $invoice = ['number' => $facts['number'], 'ids' => $facts['ids'], 'header' => $facts['header']];
            } else {
                $invoice['number'] ??= $facts['number'];
                $invoice['ids'] = array_values(array_unique([...$invoice['ids'], ...$facts['ids']]));
            }
            $signals[$page] = $reasons;
            $previousTotal = $facts['total'];
        }

        return ['starts' => $starts, 'signals' => $signals];
    }

    /**
     * Seitentexte und Briefköpfe aus Wortboxen: Zeilen nach der Höhe, Wörter
     * nach links-rechts; Briefkopf = oberes Seitenfünftel.
     *
     * @param  list<WordBox>  $boxes
     * @return array{texts: array<int, string>, headers: array<int, string>}
     */
    public static function pagesFromBoxes(array $boxes, int $pageCount): array {
        $byPage = [];
        foreach ($boxes as $box) {
            if ($box->page >= 1 && $box->page <= $pageCount) {
                $byPage[$box->page][] = $box;
            }
        }
        $texts = [];
        $headers = [];
        for ($page = 1; $page <= $pageCount; $page++) {
            $words = $byPage[$page] ?? [];
            usort($words, static fn (WordBox $a, WordBox $b): int => [$a->centerY(), $a->x] <=> [$b->centerY(), $b->x]);
            $lines = [];
            $header = [];
            $line = [];
            $lineY = null;
            foreach ($words as $word) {
                $tolerance = max($word->h * 0.6, 0.004);
                if ($lineY !== null && abs($word->centerY() - $lineY) > $tolerance) {
                    $lines[] = self::joinLine($line);
                    $line = [];
                }
                $line[] = $word;
                $lineY = $word->centerY();
                if ($word->centerY() <= self::HEADER_ZONE) {
                    $header[] = $word->text;
                }
            }
            if ($line !== []) {
                $lines[] = self::joinLine($line);
            }
            $texts[$page] = implode("\n", $lines);
            $headers[$page] = implode(' ', $header);
        }

        return ['texts' => $texts, 'headers' => $headers];
    }

    /**
     * @param  list<string>  $own
     * @return array{marker: array{0: int, 1: int}|null, number: string|null, ids: list<string>, total: bool, header: list<string>}
     */
    private function facts(string $text, string $header, array $own): array {
        $marker = null;
        if (preg_match(self::PAGE_MARKER, $text, $m) === 1 && (int) $m[1] >= 1 && (int) $m[1] <= (int) $m[2]) {
            $marker = [(int) $m[1], (int) $m[2]];
        }
        $analysis = trim($text) === '' ? null : $this->import->analyzeText($text);
        // Eine Auftragsnummer als Ersatz (Marktplatzbelege) ist kein Grenzzeichen.
        $number = $analysis !== null && $analysis['number_hint'] === null ? ReferenceTokens::key($analysis['number']) : null;

        return [
            'marker' => $marker,
            'number' => $number,
            'ids' => array_values(array_diff(self::identifiers($text), $own)),
            'total' => $analysis !== null && $analysis['gross'] !== null,
            'header' => self::tokens($header),
        ];
    }

    /**
     * USt-IdNr. und IBANs einer Seite, normalisiert.
     *
     * @return list<string>
     */
    private static function identifiers(string $text): array {
        $ids = [];
        if (preg_match_all(self::VAT_CANDIDATE, $text, $matches) > 0) {
            foreach ($matches[0] as $candidate) {
                $vat = VatNumberHelper::normalize($candidate);
                if (VatNumberHelper::isVatId($vat)) {
                    $ids[$vat] = true;
                }
            }
        }
        foreach (BankHelper::extractIBANs($text, true, true) as $iban) {
            $ids[$iban] = true;
        }

        return array_keys($ids);
    }

    /** @return list<string> */
    private static function tokens(string $text): array {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [];

        return array_values(array_unique(array_filter($tokens, static fn (string $token): bool => mb_strlen($token) >= 3)));
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private static function headerChanged(array $a, array $b): bool {
        if (count($a) < self::HEADER_MIN_TOKENS || count($b) < self::HEADER_MIN_TOKENS) {
            return false;
        }
        $union = count(array_unique([...$a, ...$b]));

        return count(array_intersect($a, $b)) / $union < self::HEADER_SIMILARITY;
    }

    /** @param  list<WordBox>  $words */
    private static function joinLine(array $words): string {
        usort($words, static fn (WordBox $a, WordBox $b): int => $a->x <=> $b->x);

        return implode(' ', array_map(static fn (WordBox $word): string => $word->text, $words));
    }
}
