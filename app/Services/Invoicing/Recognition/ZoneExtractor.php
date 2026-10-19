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
use CommonToolkit\Helper\Data\{DateHelper, NumberHelper, VatNumberHelper};
use PDFToolkit\Entities\WordBox;

/**
 * Wert einer Zone (Phase 151): Wörter, deren Mittelpunkt in der Zone liegt,
 * in Leserichtung (Zeile, dann x) verbunden und je Feldart geparst.
 */
final class ZoneExtractor {
    /**
     * @param  array<string, mixed>  $zone
     * @param  list<WordBox>  $boxes
     */
    public function rawText(array $zone, array $boxes): string {
        $page = (int) ($zone['page'] ?? 1);
        $x = (float) ($zone['x'] ?? 0);
        $y = (float) ($zone['y'] ?? 0);
        $w = (float) ($zone['w'] ?? 0);
        $h = (float) ($zone['h'] ?? 0);
        $hits = array_values(array_filter($boxes, static fn (WordBox $box): bool => $box->page === $page && $box->centerWithin($x, $y, $w, $h)));
        usort($hits, static function (WordBox $a, WordBox $b): int {
            $sameRow = abs($a->centerY() - $b->centerY()) < max($a->h, $b->h) * 0.6;

            return $sameRow ? $a->x <=> $b->x : $a->centerY() <=> $b->centerY();
        });

        return trim(implode(' ', array_map(static fn (WordBox $box): string => $box->text, $hits)));
    }

    /**
     * @param  array<string, mixed>  $zone
     * @param  list<WordBox>  $boxes
     */
    public function extract(array $zone, array $boxes): ?string {
        $raw = $this->rawText($zone, $boxes);
        if ($raw === '') {
            return null;
        }

        return self::parse($raw, (string) ($zone['parse'] ?? ZoneFields::parseOf((string) ($zone['field'] ?? ''))));
    }

    /** Text einer Zone in den Wert des Feldes: Datum → Y-m-d, Betrag → Dezimal mit Punkt, USt-IdNr. normalisiert. */
    public static function parse(string $raw, string $parse): ?string {
        $raw = trim($raw);

        return match ($parse) {
            'date' => preg_match('/\d{1,2}[.\/-]\d{1,2}[.\/-]\d{2,4}|\d{4}-\d{2}-\d{2}/', $raw, $m) === 1
                ? DateHelper::parseWithCountryPreference($m[0], CountryCode::Germany)?->format('Y-m-d')
                : null,
            'money' => preg_match('/[+-]?\d{1,3}(?:[.\s]\d{3})*(?:,\d{2})|[+-]?\d+[.,]\d{2}/', $raw, $m) === 1
                ? (($n = NumberHelper::normalizeDecimalStringOrNull($m[0], CountryCode::Germany)) !== null ? NumberHelper::toUSFormat((float) $n, 2) : null)
                : null,
            'vat_id' => self::vatId($raw),
            default => mb_substr(preg_replace('/^[\s:#]+|[\s:;,]+$/u', '', $raw) ?? $raw, 0, 191),
        };
    }

    /** Die Zone darf die Beschriftung enthalten („USt-IdNr. DE 278 737 004“): erster Kandidat mit gültigem Format. */
    private static function vatId(string $raw): ?string {
        preg_match_all('/\b[A-Z]{2}[\s.\-]*(?:[A-Z0-9][\s.\-]*){6,14}/u', $raw, $matches);
        foreach ($matches[0] as $candidate) {
            $vat = VatNumberHelper::normalize($candidate);
            if (VatNumberHelper::isVatId($vat)) {
                return $vat;
            }
        }
        $vat = VatNumberHelper::normalize($raw);

        return preg_match('/^[A-Z]{2}[A-Z0-9]{2,13}$/', $vat) === 1 ? $vat : null;
    }
}
