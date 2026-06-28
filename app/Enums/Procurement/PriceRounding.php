<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PriceRounding.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Procurement;

/**
 * Rundungsstrategie für Verkaufspreisvorschläge (Feature 050, MVP-095). Immer
 * aufrundend, damit die Zielmarge nicht unterschritten wird.
 */
enum PriceRounding: string {
    case None = 'none';
    case Up05 = 'up_0_05';
    case Up10 = 'up_0_10';
    case Up50 = 'up_0_50';
    case Up99 = 'up_0_99'; // nächster psychologischer Preis X,99
    case Up1 = 'up_1';

    public function label(): string {
        return __('procurement.margin.rounding.' . $this->value);
    }

    /** Rundet einen Betrag gemäß der Strategie auf (kaufmännisch 2 Nachkommastellen als Basis). */
    public function apply(float $value): float {
        $eps = 0.0000001;

        return match ($this) {
            self::None => round($value, 2),
            self::Up05 => ceil(($value - $eps) / 0.05) * 0.05,
            self::Up10 => ceil(($value - $eps) / 0.10) * 0.10,
            self::Up50 => ceil(($value - $eps) / 0.50) * 0.50,
            self::Up1 => ceil($value - $eps),
            self::Up99 => $this->upTo99($value, $eps),
        };
    }

    private function upTo99(float $value, float $eps): float {
        $candidate = floor($value) + 0.99;
        if ($candidate < $value - $eps) {
            $candidate += 1.0;
        }

        return $candidate;
    }
}
