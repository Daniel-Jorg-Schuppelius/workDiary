<?php
/*
 * Created on   : Sun Oct 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceTaxBreakdown.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

use App\Models\Invoicing\IncomingEInvoice;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Steuersätze einer von Hand erfassten Rechnung (Werte-Dialog): je Satz
 * Netto und Steuer, abgelegt unter `summary.manual.tax_breakdown` in der Form
 * der E-Rechnungs-Aufschlüsselung; die Kopfspalten tragen die Summen.
 */
final class IncomingInvoiceTaxBreakdown {
    /**
     * Ausgefüllte Zeilen; leere fallen weg, die Schlüssel bleiben für die Fehlermeldungen.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, array{percent: ?string, net: ?string, tax: ?string}>
     */
    public static function filled(array $input): array {
        $rows = [];
        foreach ($input as $key => $row) {
            $cell = static function (string $field) use ($row): ?string {
                $value = is_array($row) && is_scalar($row[$field] ?? null) ? trim((string) $row[$field]) : '';

                return $value === '' ? null : $value;
            };
            $cells = ['percent' => $cell('percent'), 'net' => $cell('net'), 'tax' => $cell('tax')];
            if (array_filter($cells) !== []) {
                $rows[$key] = $cells;
            }
        }

        return $rows;
    }

    /**
     * Summen aus geprüften Zeilen. Eine einzelne Zeile mit Satz, aber ohne
     * Beträge rechnet beide aus dem Brutto heraus (Kleinbetragsrechnung
     * „inkl. 19 % USt“). Abgelegt werden die Zeilen nur vollständig.
     *
     * @param  array<array-key, array{percent: ?string, net: ?string, tax: ?string}>  $rows  numerisch geprüft
     * @param  numeric-string  $gross
     * @return array{rows: list<array{percent: numeric-string, net: numeric-string, tax: numeric-string}>, net: ?string, tax: ?string}
     */
    public static function resolve(array $rows, string $gross): array {
        $rows = array_values(array_map(static fn (array $row): array => array_map(
            static fn (?string $value): ?string => $value === null ? null : NumberHelper::roundPrecise(NumberHelper::normalizeDecimalString($value), 2),
            $row,
        ), $rows));
        if (count($rows) === 1 && $rows[0]['percent'] !== null && $rows[0]['net'] === null && $rows[0]['tax'] === null) {
            $tax = NumberHelper::roundPrecise(NumberHelper::dividePrecise(
                NumberHelper::multiplyPrecise($gross, $rows[0]['percent'], 6),
                NumberHelper::addPrecise('100', $rows[0]['percent'], 6),
                6,
            ), 2);
            $rows[0] = ['percent' => $rows[0]['percent'], 'net' => NumberHelper::subtractPrecise($gross, $tax, 2), 'tax' => $tax];
        }
        $complete = array_values(array_filter($rows, static fn (array $row): bool => ! in_array(null, $row, true)));
        $nets = array_column($rows, 'net');
        $taxes = array_column($rows, 'tax');

        return [
            'rows' => count($complete) === count($rows) ? $complete : [],
            'net' => $rows === [] || in_array(null, $nets, true) ? null : NumberHelper::sumPrecise($nets, 2),
            'tax' => $rows === [] || in_array(null, $taxes, true) ? null : NumberHelper::sumPrecise($taxes, 2),
        ];
    }

    /**
     * Summe aus Netto und Steuer, wenn sie mehr als einen Cent vom Brutto abweicht.
     *
     * @param  numeric-string  $gross
     */
    public static function grossMismatch(?string $net, ?string $tax, string $gross): ?string {
        if ($net === null || $tax === null) {
            return null;
        }
        $sum = NumberHelper::addPrecise(NumberHelper::normalizeDecimalString($net), NumberHelper::normalizeDecimalString($tax), 2);

        return NumberHelper::comparePrecise(NumberHelper::absPrecise(NumberHelper::subtractPrecise($sum, $gross, 2)), '0.01', 2) > 0 ? $sum : null;
    }

    /**
     * Zeilen für den Dialog: die erfasste bzw. erkannte Aufschlüsselung (beim
     * Klärfall aus der Diagnose), sonst eine Zeile aus Netto und Steuer. Deren
     * Satz wird abgeleitet und auf ganze Prozent gesetzt, wenn er höchstens
     * 0,1 Punkte daneben liegt (Rundung der Steuer).
     *
     * @param  array<string, mixed>  $found  Teilwerte eines Klärfalls (summary.extraction.found)
     * @return list<array{percent: string, net: string, tax: string}>
     */
    public static function prefill(IncomingEInvoice $incoming, array $found): array {
        $rows = $incoming->taxBreakdown();
        if ($rows === [] && ! isset(((array) $incoming->summary)['manual'])) {
            $rows = array_values(array_filter((array) data_get($incoming->summary, 'extraction.tax_breakdown', []), is_array(...)));
        }
        if ($rows !== []) {
            return array_map(static fn (array $row): array => [
                'percent' => is_scalar($row['percent'] ?? null) ? (string) $row['percent'] : '',
                'net' => is_scalar($row['net'] ?? null) ? (string) $row['net'] : '',
                'tax' => is_scalar($row['tax'] ?? null) ? (string) $row['tax'] : '',
            ], $rows);
        }
        $net = $incoming->amount_net?->getAmount() ?? (is_scalar($found['net'] ?? null) ? (string) $found['net'] : null);
        $tax = $incoming->amount_tax?->getAmount() ?? (is_scalar($found['tax'] ?? null) ? (string) $found['tax'] : null);
        $percent = '';
        if ($net !== null && $tax !== null && ! NumberHelper::isZeroPrecise(NumberHelper::normalizeDecimalString($net), 2)) {
            $exact = NumberHelper::percentagePrecise(NumberHelper::normalizeDecimalString($tax), NumberHelper::normalizeDecimalString($net), 2);
            $whole = NumberHelper::roundPrecise($exact, 0);
            $percent = NumberHelper::comparePrecise(NumberHelper::absPrecise(NumberHelper::subtractPrecise($exact, $whole, 2)), '0.1', 2) <= 0 ? $whole : $exact;
        }

        return [['percent' => $percent, 'net' => (string) ($net ?? ''), 'tax' => (string) ($tax ?? '')]];
    }
}
