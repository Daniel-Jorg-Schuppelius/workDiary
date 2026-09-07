<?php
/*
 * Created on   : Mon Sep 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseMonths.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Models\LexofficeVoucherLine;
use Carbon\CarbonImmutable;

/**
 * Lizenzen und Monate einer Rechnungsposition (Feature 152). Der Reseller
 * rechnet je Lizenz „12 Monat" oder „1 Jahr" ab; mehrere Lizenzen stehen als
 * „5 Jahr" (Menge = Lizenzen) oder als wiederholte Monatspositionen. Trägt
 * die Rechnung einen Leistungszeitraum, zählt der: „24 Monat" bei 12 Monaten
 * Leistung sind zwei Lizenzen, „5 Stück" bei 12 Monaten sind 5 × 12. Ohne
 * Einheit entscheidet der Stückpreis (Monatspreise liegen unter 30 €).
 * Lizenzmonate = Lizenzen × Monate — die Größe, in der Perioden gedeckt werden.
 */
final class LicenseMonths {
    private const MONTH_UNITS = ['monat', 'monate', 'month', 'months'];
    private const YEAR_UNITS = ['jahr', 'jahre', 'year', 'years'];
    private const MONTHLY_PRICE_LIMIT = 30.0;

    public static function ofLine(LexofficeVoucherLine $line): float {
        $split = self::split($line);

        return $split['licences'] * $split['months'];
    }

    /**
     * @return array{licences: float, months: float}
     */
    public static function split(LexofficeVoucherLine $line): array {
        $quantity = (float) $line->quantity;
        $service = self::serviceMonths($line);
        if (self::isMonthly($line)) {
            // Monatsposition: Menge = Monate; mit Leistungszeitraum ergibt der Rest die Lizenzen.
            if ($service !== null && $service > 0 && $quantity > $service && abs(fmod($quantity, $service)) < 0.001) {
                return ['licences' => $quantity / $service, 'months' => (float) $service];
            }

            return ['licences' => 1.0, 'months' => $quantity];
        }
        if (self::isYearly($line)) {
            // „2 Jahr" bei 24 Monaten Leistung ist EINE Lizenz über zwei Jahre.
            if ($service !== null && abs($quantity * 12.0 - $service) < 0.001) {
                return ['licences' => 1.0, 'months' => (float) $service];
            }

            return ['licences' => $quantity, 'months' => 12.0];
        }

        // Stück/ohne Einheit: Menge = Lizenzen, Laufzeit aus dem Leistungszeitraum, sonst ein Jahr.
        return ['licences' => $quantity, 'months' => $service !== null ? (float) $service : 12.0];
    }

    /**
     * Ist die Lizenzzahl der Position sicher? Bei „Jahr"/„Stück" steht sie in
     * der Menge, bei Monatspositionen erst mit Leistungszeitraum — „24 Monat"
     * allein kann zwei Lizenzen für ein Jahr oder eine für zwei Jahre sein.
     */
    public static function isLicenceCountCertain(LexofficeVoucherLine $line): bool {
        if (self::serviceMonths($line) !== null) {
            return true;
        }

        return ! self::isMonthly($line);
    }

    /** Monatsposition: Einheit Monat, oder ohne Einheit ein Stückpreis unter dem Monatslimit. */
    public static function isMonthly(LexofficeVoucherLine $line): bool {
        $unit = self::unit($line);
        if (in_array($unit, self::MONTH_UNITS, true)) {
            return true;
        }
        if ($unit !== '') {
            return false;
        }

        return $line->unit_net->toFloat() < self::MONTHLY_PRICE_LIMIT;
    }

    public static function isYearly(LexofficeVoucherLine $line): bool {
        return in_array(self::unit($line), self::YEAR_UNITS, true);
    }

    /** Positionsmenge, die $months Lizenzmonaten entspricht (Monat: 1 je Monat, Jahr: 12 je Stück, sonst Laufzeit je Stück). */
    public static function unitsFor(LexofficeVoucherLine $line, float $months, int $termMonths): float {
        if (self::isYearly($line)) {
            return $months / 12.0;
        }
        if (self::isMonthly($line)) {
            return $months; // Stückpreis je Lizenzmonat
        }
        $perUnit = self::split($line)['months'];

        return $months / ($perUnit > 0 ? $perUnit : max(1, $termMonths));
    }

    /**
     * Anzeige von Lizenzmonaten so, wie der Reseller denkt: „5 × 12 Mon."
     * (Lizenzen × Monate je Lizenz), bei einem Rest unter einer Lizenz nur
     * die Monate („6 Mon."), sonst die nackten Lizenzmonate.
     */
    public static function label(float $licenceMonths, float $perLicence): string {
        $fmt = static fn(float $v): string => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
        if ($perLicence > 0 && $licenceMonths >= $perLicence - 0.001) {
            $licences = $licenceMonths / $perLicence;
            if (abs($licences - round($licences)) < 0.001) {
                return (string) __('resale.link.licences_x_months', ['licences' => $fmt(round($licences)), 'months' => $fmt($perLicence)]);
            }
        }
        if ($perLicence > 0 && $licenceMonths < $perLicence) {
            return (string) __('resale.link.months_only', ['months' => $fmt($licenceMonths)]);
        }

        return (string) __('resale.link.licence_months_only', ['months' => $fmt($licenceMonths)]);
    }

    /**
     * Bezugsdatum einer Position: Beginn des Leistungszeitraums, sonst das
     * Rechnungsdatum — danach findet die Zuordnung die Periode.
     */
    public static function referenceDate(LexofficeVoucherLine $line): ?CarbonImmutable {
        $voucher = $line->relationLoaded('voucher') ? $line->voucher : null;
        if ($voucher === null) {
            return null;
        }
        $start = $voucher->serviceStart();
        if ($start !== null) {
            return $start;
        }

        return $voucher->voucher_date === null ? null : CarbonImmutable::instance($voucher->voucher_date);
    }

    /**
     * Deckt der Leistungszeitraum der Rechnung den Tag? Eine Mehrjahres-
     * Position gehört auch zu Perioden, die lange nach dem Leistungsbeginn
     * starten — das Fenster um das Bezugsdatum reicht dafür nicht.
     */
    public static function serviceCovers(LexofficeVoucherLine $line, CarbonImmutable $day): bool {
        $voucher = $line->relationLoaded('voucher') ? $line->voucher : null;
        if ($voucher === null || $voucher->service_starts_on === null || $voucher->service_ends_on === null) {
            return false;
        }

        return ! $day->lessThan(CarbonImmutable::instance($voucher->service_starts_on)) && ! $day->greaterThan(CarbonImmutable::instance($voucher->service_ends_on));
    }

    private static function serviceMonths(LexofficeVoucherLine $line): ?int {
        return $line->relationLoaded('voucher') ? $line->voucher->serviceMonths() : null;
    }

    private static function unit(LexofficeVoucherLine $line): string {
        return mb_strtolower(trim((string) $line->unit_name));
    }
}
