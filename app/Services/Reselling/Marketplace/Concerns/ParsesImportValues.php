<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ParsesImportValues.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace\Concerns;

use Carbon\CarbonImmutable;
use CommonToolkit\Enums\{CountryCode, CurrencyCode};
use CommonToolkit\Helper\Data\{DateHelper, NumberHelper};
use CommonToolkit\ValueObjects\Money;
use DateTimeInterface;

/**
 * Zellwerte der Reseller-Exporte (CSV-Text oder XLSX-Rohwert) in Datum,
 * Betrag und Ganzzahl übersetzen (Review 2026-09-10, C4/C5/F). Eine Stelle
 * statt vier Kopien; die Deutung ist deutsch (Tausenderpunkt, TT.MM.JJJJ),
 * numerische XLSX-Zellen bleiben exakt. Unlesbares wird null, nie geraten.
 */
trait ParsesImportValues {
    /**
     * Datum aus Datumszelle, Excel-Seriennummer (Zahl oder Text), ISO,
     * TT.MM.JJJJ, T.M.JJJJ oder T/M/JJJJ — deutsch gelesen (Toolkit v1.32
     * kennt ein-/zweistellige Tage und Monate). Ein Slash-Datum, das deutsch
     * unmöglich ist („8/15/25"), gilt als US-Reihenfolge. „2026", „3.2026"
     * und „31.02.2026" sind kein Datum.
     */
    protected static function importDate(mixed $raw): ?CarbonImmutable {
        if ($raw === null || $raw === '' || is_bool($raw)) {
            return null;
        }
        if ($raw instanceof DateTimeInterface) {
            return CarbonImmutable::instance($raw)->startOfDay();
        }
        $text = trim(is_scalar($raw) ? (string) $raw : '');
        if (is_numeric($text)) {
            // Nur eine Seriennummer im Excel-Fenster ist ein Datum — „2026" liegt außerhalb.
            $text = DateHelper::excelCellToGerman($text) ?? '';
        }
        if ($text === '') {
            return null;
        }
        $parsed = DateHelper::parseDateTime($text, CountryCode::Germany) // Round-Trip-geprüft: 31.02. → null
            ?? (str_contains($text, '/') ? DateHelper::parseDateTime($text, CountryCode::UnitedStatesOfAmerica) : null);
        if ($parsed === null) {
            return null;
        }
        $date = CarbonImmutable::instance($parsed)->startOfDay();

        // Dreistellige Jahre („0202-02-01") überleben den Round-Trip — für Abos unplausibel.
        return $date->year >= 1900 && $date->year <= 2200 ? $date : null;
    }

    /**
     * Betrag: numerische Zellen exakt, Text deutsch gedeutet („1.200" = 1200,
     * „10,70 €", geschützte Leerzeichen). Stückpreise mit Scale 4, Summen mit 2.
     */
    protected static function importMoney(mixed $raw, CurrencyCode $currency, int $scale): ?Money {
        if ($raw === null || $raw === '' || is_bool($raw) || $raw instanceof DateTimeInterface) {
            return null;
        }
        if (is_int($raw) || is_float($raw)) {
            return Money::ofFloat((float) $raw, $currency, $scale);
        }

        return Money::ofNullable(is_string($raw) ? $raw : null, $currency, $scale, country: CountryCode::Germany);
    }

    /**
     * Ganzzahl (Menge, Laufzeit): null bei leer, unlesbar oder gebrochen.
     */
    protected static function importInteger(mixed $raw): ?int {
        if ($raw === null || $raw === '' || is_bool($raw) || $raw instanceof DateTimeInterface) {
            return null;
        }
        if (is_int($raw) || is_float($raw)) {
            $number = (float) $raw;
        } else {
            $decimal = NumberHelper::normalizeDecimalStringOrNull(is_string($raw) ? $raw : '', CountryCode::Germany);
            if ($decimal === null) {
                return null;
            }
            $number = (float) $decimal;
        }

        return floor($number) === $number && abs($number) < PHP_INT_MAX ? (int) $number : null;
    }
}
