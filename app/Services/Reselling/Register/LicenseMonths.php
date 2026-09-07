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

/**
 * Lizenzmonate einer Rechnungsposition (Feature 152): der Reseller rechnet
 * je Lizenz „12 Monat" oder „1 Jahr" ab — Menge und Einheit tragen die
 * Laufzeit. Ohne Einheit entscheidet der Stückpreis (Monatspreise liegen
 * unter 30 €). Eine Stelle für Vorschlagslauf, Abgleich und Dialoge.
 */
final class LicenseMonths {
    private const MONTH_UNITS = ['monat', 'monate', 'month', 'months'];
    private const YEAR_UNITS = ['jahr', 'jahre', 'year', 'years'];
    private const MONTHLY_PRICE_LIMIT = 30.0;

    public static function ofLine(LexofficeVoucherLine $line): float {
        $quantity = (float) $line->quantity;

        return self::isMonthly($line) ? $quantity : $quantity * 12.0;
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

        return self::isMonthly($line) ? $months : $months / max(1, $termMonths);
    }

    private static function unit(LexofficeVoucherLine $line): string {
        return mb_strtolower(trim((string) $line->unit_name));
    }
}
