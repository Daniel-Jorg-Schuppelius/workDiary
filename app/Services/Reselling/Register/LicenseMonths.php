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

use App\Services\Reselling\Mirror\MirrorLine;
use Carbon\{CarbonImmutable, CarbonInterface};
use CommonToolkit\Helper\Data\DateHelper;

/**
 * Lizenzen und Monate einer Rechnungsposition (Feature 152). Der Reseller
 * rechnet je Lizenz „12 Monat" oder „1 Jahr" ab; mehrere Lizenzen stehen als
 * „5 Jahr" (Menge = Lizenzen) oder als wiederholte Monatspositionen. Trägt
 * die Rechnung einen Leistungszeitraum, zählt der: „24 Monat" bei 12 Monaten
 * Leistung sind zwei Lizenzen, „5 Stück" bei 12 Monaten sind 5 × 12. Ohne
 * Einheit entscheidet der Stückpreis (Monatspreise liegen unter 30 €).
 * Lizenzmonate = Lizenzen × Monate — die Größe, in der Perioden gedeckt werden.
 * Arbeitet auf der anbieterneutralen {@see MirrorLine} (Spiegel-Abstraktion).
 */
final class LicenseMonths {
    private const MONTH_UNITS = ['monat', 'monate', 'month', 'months'];
    private const YEAR_UNITS = ['jahr', 'jahre', 'year', 'years'];
    private const MONTHLY_PRICE_LIMIT = 30.0;

    public static function ofLine(MirrorLine $line): float {
        $split = self::split($line);

        return $split['licences'] * $split['months'];
    }

    /**
     * @return array{licences: float, months: float}
     */
    public static function split(MirrorLine $line): array {
        $quantity = $line->quantity;
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
    public static function isLicenceCountCertain(MirrorLine $line): bool {
        if (self::serviceMonths($line) !== null) {
            return true;
        }

        return ! self::isMonthly($line);
    }

    /** Monatsartikel? Einheit „Monat"/„Monate"/„month"/„months", Groß-/Kleinschreibung egal. */
    public static function isMonthUnit(?string $unit): bool {
        return in_array(mb_strtolower(trim((string) $unit)), self::MONTH_UNITS, true);
    }

    /**
     * Ganze Monate zwischen zwei Inklusiv-Daten (31.01.–28.02. = 1, 01.01.–31.12. = 12),
     * kaufmännisch gerundet — für Periodenlänge und Leistungszeitraum. Kann 0 sein;
     * ein Ende vor dem Beginn (fremde Belegdaten) ergibt 0 statt der Toolkit-Exception.
     */
    public static function monthsBetween(CarbonInterface $from, CarbonInterface $toInclusive): int {
        if ($toInclusive->toDateString() < $from->toDateString()) {
            return 0;
        }

        return DateHelper::monthsBetweenInclusive($from, $toInclusive);
    }

    /** Monatsposition: Einheit Monat, oder ohne Einheit ein Stückpreis unter dem Monatslimit. */
    public static function isMonthly(MirrorLine $line): bool {
        $unit = self::unit($line);
        if (self::isMonthUnit($unit)) {
            return true;
        }
        if ($unit !== '') {
            return false;
        }

        return $line->unitNet->toFloat() < self::MONTHLY_PRICE_LIMIT;
    }

    public static function isYearly(MirrorLine $line): bool {
        return in_array(self::unit($line), self::YEAR_UNITS, true);
    }

    /** Positionsmenge, die $months Lizenzmonaten entspricht (Monat: 1 je Monat, Jahr: 12 je Stück, sonst Laufzeit je Stück). */
    public static function unitsFor(MirrorLine $line, float $months, int $termMonths): float {
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
    public static function referenceDate(MirrorLine $line): ?CarbonImmutable {
        return $line->serviceFrom ?? $line->voucherDate;
    }

    /**
     * Deckt der Leistungszeitraum der Rechnung den Tag? Eine Mehrjahres-
     * Position gehört auch zu Perioden, die lange nach dem Leistungsbeginn
     * starten — das Fenster um das Bezugsdatum reicht dafür nicht.
     */
    public static function serviceCovers(MirrorLine $line, CarbonImmutable $day): bool {
        if ($line->serviceFrom === null || $line->serviceTo === null) {
            return false;
        }

        return ! $day->lessThan($line->serviceFrom) && ! $day->greaterThan($line->serviceTo);
    }

    private static function serviceMonths(MirrorLine $line): ?int {
        return $line->serviceMonths();
    }

    private static function unit(MirrorLine $line): string {
        return mb_strtolower(trim((string) $line->unitName));
    }
}
