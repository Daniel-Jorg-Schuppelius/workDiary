<?php
/*
 * Created on   : Mon Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IndexationMethod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Contract;

/**
 * Preis-/Wertanpassungsregel (Welle D, CLM). Rein deskriptiv als Datenfeld +
 * Berechnungshinweis — WorkDiary bindet KEINE externe Index-API an und
 * berechnet keine amtlichen Indexstände.
 */
enum IndexationMethod: string {
    case None = 'none';
    case ConsumerPriceIndex = 'cpi';
    case FixedPercent = 'fixed_percent';
    case Custom = 'custom';

    public function label(): string {
        return match ($this) {
            self::None => (string) __('Keine Indexierung'),
            self::ConsumerPriceIndex => (string) __('Verbraucherpreisindex (VPI)'),
            self::FixedPercent => (string) __('Fester Prozentsatz'),
            self::Custom => (string) __('Eigene Regel'),
        };
    }

    /** Braucht die Methode einen Zahlenwert (Prozent/Schwelle)? */
    public function usesValue(): bool {
        return in_array($this, [self::ConsumerPriceIndex, self::FixedPercent, self::Custom], true);
    }
}
