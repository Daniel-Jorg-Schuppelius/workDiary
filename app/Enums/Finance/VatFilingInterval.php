<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VatFilingInterval.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Voranmeldungszeitraum der Umsatzsteuer (Feature 125, MVP-684).
 *
 * § 18 Abs. 2 UStG: Regelfall ist das Kalendervierteljahr; monatlich ab einer
 * Vorjahressteuer über 9.000 €, Befreiung von der Voranmeldung bis 2.000 €
 * (dann nur die Jahreserklärung). `None` steht für Fälle ohne
 * Umsatzsteuerpflicht — Kleinunternehmer nach § 19 UStG.
 *
 * Über den Zeitraum entscheidet das Finanzamt. Das Programm führt die
 * Entscheidung nach, es trifft sie nicht.
 */
enum VatFilingInterval: string implements HasLabel {
    use HasOptions;

    /** Kalendermonat — Vorjahressteuer über 9.000 €. */
    case Monthly = 'monthly';

    /** Kalendervierteljahr — gesetzlicher Regelfall. */
    case Quarterly = 'quarterly';

    /** Keine Voranmeldung, nur Jahreserklärung — Vorjahressteuer bis 2.000 €. */
    case Annual = 'annual';

    /** Keine Umsatzsteuervoranmeldung (Kleinunternehmer § 19 UStG). */
    case None = 'none';

    public function label(): string {
        return (string) __('enums.finance.vat-filing-interval.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Monthly => 'warning',
            self::Quarterly => 'info',
            self::Annual => 'accent',
            self::None => 'neutral',
        };
    }

    /** Erzeugt dieser Zeitraum überhaupt Voranmeldungen? */
    public function hasAdvanceReturns(): bool {
        return $this === self::Monthly || $this === self::Quarterly;
    }

    /**
     * Nur Monatszahler schulden die Sondervorauszahlung (§ 47 UStDV) —
     * Vierteljahreszahler bekommen die Fristverlängerung ohne sie.
     */
    public function requiresSpecialPrepayment(): bool {
        return $this === self::Monthly;
    }

    /** Anzahl der Perioden im Kalenderjahr. */
    public function periodsPerYear(): int {
        return match ($this) {
            self::Monthly => 12,
            self::Quarterly => 4,
            self::Annual => 1,
            self::None => 0,
        };
    }

    /** Länge einer Periode in Monaten. */
    public function months(): int {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            default => 12,
        };
    }
}
