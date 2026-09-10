<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeInvoiceParser.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice;

use CommonToolkit\Enums\{CurrencyCode, Month};
use CommonToolkit\Helper\Data\DateHelper;

/**
 * Zerlegt eine Lexoffice-Rechnung (`GET /invoices/{id}`) in Positionen und
 * Belegtexte — gemeinsam für den Belegspiegel (Feature 152, MVP-760) und den
 * positionsgenauen Leser aus Feature 151. Textpositionen fallen weg,
 * Bruttopreise werden über den Steuersatz auf netto gebracht, Rabatte
 * eingerechnet. Der Positionsbetrag kommt aus `lineItemAmount` der API
 * (netto bzw. bei Bruttorechnung über den Steuersatz), sonst gerechnet.
 *
 * @phpstan-type ParsedLine array{position: int, type: string, external_article_id: string, name: string, description: string, quantity: float, unit_name: string, unit_net: float, total_net: float, tax_rate: float|null}
 * @phpstan-type ServicePeriod array{0: ?string, 1: ?string}
 */
final class LexofficeInvoiceParser {
    /** Monatsnamen deutsch/englisch (voll und gekürzt), aufgelöst über {@see Month::fromName()}. */
    private const MONTH_NAMES = 'januar|january|jan|februar|february|feb|märz|maerz|march|mär|mar|april|apr|mai|may|juni|june|jun|juli|july|jul|august|aug|september|sept|sep|oktober|october|okt|oct|november|nov|dezember|december|dez|dec';

    private const RANGE_SEPARATOR = '\s*(?:-|–|—|bis|to|until)\s*';

    /**
     * @param  array<string, mixed>  $invoice
     * @return array{currency: CurrencyCode, voucher_text: string, recipient: string, service_from: ?string, service_to: ?string, lines: list<ParsedLine>, issues: list<string>}
     */
    public static function parse(array $invoice): array {
        $currency = CurrencyCode::tryFrom((string) ($invoice['totalPrice']['currency'] ?? 'EUR')) ?? CurrencyCode::Euro;
        // Belegtexte: Bei Partnerrechnungen steht der Endkunde in Titel,
        // Einleitung oder Schlusstext — nicht in den Positionen.
        $voucherText = trim(implode(' ', array_filter([
            (string) ($invoice['title'] ?? ''),
            (string) ($invoice['introduction'] ?? ''),
            (string) ($invoice['remark'] ?? ''),
        ], static fn(string $part): bool => $part !== '')));
        $recipient = trim((string) ($invoice['address']['name'] ?? ''));
        [$serviceFrom, $serviceTo] = self::servicePeriod($invoice);
        if ($serviceFrom === null && $serviceTo === null) {
            // Strukturierte Quelle hat Vorrang; der Text nur, wenn sie leer ist.
            [$serviceFrom, $serviceTo] = self::servicePeriodFromText($voucherText);
        }
        $grossVoucher = (string) ($invoice['taxConditions']['taxType'] ?? 'net') === 'gross';

        $lines = [];
        $issues = [];
        foreach (array_values((array) ($invoice['lineItems'] ?? [])) as $index => $item) {
            if (! is_array($item) || (string) ($item['type'] ?? '') === 'text') {
                continue;
            }
            $position = $index + 1;
            $name = (string) ($item['name'] ?? '');
            $unit = is_array($item['unitPrice'] ?? null) ? $item['unitPrice'] : [];
            $rate = isset($unit['taxRatePercentage']) && is_numeric($unit['taxRatePercentage']) ? (float) $unit['taxRatePercentage'] : null;
            $net = isset($unit['netAmount']) && is_numeric($unit['netAmount']) ? (float) $unit['netAmount'] : null;
            if ($net === null && isset($unit['grossAmount']) && is_numeric($unit['grossAmount'])) {
                if ($rate === null) {
                    // Nicht still Satz 0 annehmen: Brutto = Netto nur mit Hinweis.
                    $issues[] = sprintf('Position %d „%s": Bruttopreis ohne Steuersatz — Netto als Brutto übernommen.', $position, $name);
                }
                $net = (float) $unit['grossAmount'] / (1 + ($rate ?? 0.0) / 100);
            }
            if ($net === null) {
                continue;
            }
            $discount = (float) ($item['discountPercentage'] ?? 0);
            if ($discount > 0) {
                $net *= 1 - $discount / 100;
            }
            $quantity = (float) ($item['quantity'] ?? 1);
            $lines[] = [
                'position' => $position,
                'type' => (string) ($item['type'] ?? ''),
                'external_article_id' => (string) ($item['id'] ?? ''),
                'name' => $name,
                'description' => (string) ($item['description'] ?? ''),
                'quantity' => $quantity,
                'unit_name' => (string) ($item['unitName'] ?? ''),
                'unit_net' => round($net, 4),
                'total_net' => self::lineNet($item, $grossVoucher, $rate) ?? round($net * $quantity, 2),
                'tax_rate' => $rate,
            ];
        }

        return ['currency' => $currency, 'voucher_text' => $voucherText, 'recipient' => $recipient, 'service_from' => $serviceFrom, 'service_to' => $serviceTo, 'lines' => $lines, 'issues' => $issues];
    }

    /**
     * Positionsbetrag der API (`lineItemAmount`: Menge × Preis abzüglich
     * Rabatt). Bei Nettorechnungen und steuerfreien Belegen netto; bei
     * Bruttorechnungen brutto und nur mit bekanntem Steuersatz umrechenbar.
     *
     * @param  array<string, mixed>  $item
     */
    private static function lineNet(array $item, bool $grossVoucher, ?float $rate): ?float {
        $amount = $item['lineItemAmount'] ?? null;
        if (! is_numeric($amount)) {
            return null;
        }
        $amount = (float) $amount;
        if ($grossVoucher) {
            if ($rate === null) {
                return null;
            }
            $amount /= 1 + $rate / 100;
        }

        return round($amount, 2);
    }

    /**
     * Leistungs-/Lieferdatum bzw. -zeitraum (shippingConditions.shippingDate /
     * shippingEndDate; shippingType service|serviceperiod|delivery|deliveryperiod).
     * Ein Einzeldatum liefert nur den Beginn; „none" nichts.
     *
     * @param  array<string, mixed>  $invoice
     * @return ServicePeriod Y-m-d
     */
    private static function servicePeriod(array $invoice): array {
        $conditions = is_array($invoice['shippingConditions'] ?? null) ? $invoice['shippingConditions'] : [];
        $type = (string) ($conditions['shippingType'] ?? 'none');
        if ($type === 'none' || $type === '') {
            return [null, null];
        }
        $from = self::day($conditions['shippingDate'] ?? null);
        $to = str_ends_with($type, 'period') ? self::day($conditions['shippingEndDate'] ?? null) : null;
        if ($from === null) {
            return [null, null];
        }
        if ($to !== null && $to < $from) {
            $to = null;
        }

        return [$from, $to];
    }

    /**
     * Leistungszeitraum aus dem Belegtext (Fallback ohne shippingConditions):
     * „15.12.2025 – 14.12.2026", „01.01. – 31.12.2026" (Jahr vom Ende),
     * „01.01.26 bis 31.12.26", „Januar 2026" / „Jan. 2026" / „01/2026"
     * (Monatsanfang bis -ende) und Monatsspannen „Januar – März 2026",
     * deutsch und englisch. Ende vor Beginn wird verworfen.
     *
     * @return ServicePeriod Y-m-d
     */
    public static function servicePeriodFromText(string $text): array {
        $text = trim($text);
        if ($text === '') {
            return [null, null];
        }
        $d = '(\d{1,2})\.\s?(\d{1,2})\.\s?(\d{4}|\d{2})';
        $m = '(' . self::MONTH_NAMES . ')\.?';
        $sep = self::RANGE_SEPARATOR;
        $noLetter = '(?<!\p{L})';
        $endNoDigit = '(?![\d.])';

        // Vollständiger Datumsbereich.
        if (preg_match('/' . $noLetter . $d . $sep . $d . $endNoDigit . '/iu', $text, $hit) === 1) {
            return self::validRange(self::date($hit[3], $hit[2], $hit[1]), self::date($hit[6], $hit[5], $hit[4]));
        }
        // Beginn ohne Jahr, Jahr vom Ende — über den Jahreswechsel zurück.
        if (preg_match('/' . $noLetter . '(\d{1,2})\.\s?(\d{1,2})\.' . $sep . $d . $endNoDigit . '/iu', $text, $hit) === 1) {
            $to = self::date($hit[5], $hit[4], $hit[3]);
            $from = self::date($hit[5], $hit[2], $hit[1]);
            if ($from !== null && $to !== null && $from > $to) {
                $from = self::date((string) (self::year($hit[5]) - 1), $hit[2], $hit[1]);
            }

            return self::validRange($from, $to);
        }
        // Monatsspanne „Januar 2026 – März 2026" / „Januar – März 2026".
        if (preg_match('/' . $noLetter . $m . '\s+(\d{4})' . $sep . $m . '\s+(\d{4})(?!\d)/iu', $text, $hit) === 1) {
            return self::validRange(self::monthStart($hit[1], $hit[2]), self::monthEnd($hit[3], $hit[4]));
        }
        if (preg_match('/' . $noLetter . $m . $sep . $m . '\s+(\d{4})(?!\d)/iu', $text, $hit) === 1) {
            return self::validRange(self::monthStart($hit[1], $hit[3]), self::monthEnd($hit[2], $hit[3]));
        }
        // Einzelner Monat: „Januar 2026", „Jan. 2026", „01/2026".
        if (preg_match('/' . $noLetter . $m . '\s+(\d{4})(?!\d)/iu', $text, $hit) === 1) {
            return self::validRange(self::monthStart($hit[1], $hit[2]), self::monthEnd($hit[1], $hit[2]));
        }
        if (preg_match('/(?<![\d.\/])(\d{1,2})\/(\d{4})(?![\d\/])/u', $text, $hit) === 1) {
            return self::validRange(self::monthStart($hit[1], $hit[2]), self::monthEnd($hit[1], $hit[2]));
        }

        return [null, null];
    }

    /** @return ServicePeriod */
    private static function validRange(?string $from, ?string $to): array {
        if ($from === null || $to === null || $to < $from) {
            return [null, null];
        }

        return [$from, $to];
    }

    /** Monatsname (DE/EN) oder Monatszahl → Monatsnummer. */
    private static function month(string $value): ?int {
        if (ctype_digit($value)) {
            $month = (int) $value;

            return $month >= 1 && $month <= 12 ? $month : null;
        }

        return Month::fromName($value)?->value;
    }

    private static function year(string $value): int {
        return strlen($value) === 2 ? DateHelper::expandYear((int) $value) : (int) $value;
    }

    private static function date(string $year, string $month, string $day): ?string {
        $y = self::year($year);
        $m = (int) $month;
        $d = (int) $day;

        return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : null;
    }

    private static function monthStart(string $month, string $year): ?string {
        $m = self::month($month);

        return $m === null ? null : sprintf('%04d-%02d-01', self::year($year), $m);
    }

    private static function monthEnd(string $month, string $year): ?string {
        $m = self::month($month);
        if ($m === null) {
            return null;
        }
        $y = self::year($year);

        return sprintf('%04d-%02d-%02d', $y, $m, DateHelper::getLastDay($y, $m));
    }

    private static function day(mixed $value): ?string {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return \Carbon\CarbonImmutable::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
