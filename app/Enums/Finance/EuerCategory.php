<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EuerCategory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zeile der Anlage EÜR, der ein Konto zugeordnet ist (Feature 125, MVP-680).
 *
 * Die Kategorie hängt am **Konto**, nicht an der Buchung: Andernfalls müsste
 * jede Zeile einzeln eingeordnet werden und die Vorschau wäre bei jedem neuen
 * Beleg wieder unvollständig.
 *
 * Grundregel ist § 11 EStG (Zufluss/Abfluss). Zwei Kategorien fallen bewusst
 * heraus: Abschreibungen (§ 4 Abs. 3 S. 3 EStG — verteilt über die
 * Nutzungsdauer, nicht über die Zahlung) und nicht abziehbare Beträge, die nur
 * nachrichtlich erscheinen.
 */
enum EuerCategory: string implements HasLabel {
    use HasOptions;

    /** Betriebseinnahmen netto. */
    case Income = 'income';

    /** Vereinnahmte Umsatzsteuer. */
    case IncomeVat = 'income_vat';

    /** Private Nutzung (Sachentnahme, Kfz, Telefon). */
    case PrivateUse = 'private_use';

    /** Betriebsausgaben in voller Höhe. */
    case Expense = 'expense';

    /** Abschreibungen — nicht aus Zahlungen ableitbar. */
    case Depreciation = 'depreciation';

    /** Geringwertige Wirtschaftsgüter (§ 6 Abs. 2 EStG). */
    case LowValueAsset = 'low_value_asset';

    /** Gezahlte Vorsteuer. */
    case InputTax = 'input_tax';

    /** An das Finanzamt gezahlte Umsatzsteuer. */
    case PaidVat = 'paid_vat';

    /** Beschränkt abziehbar (§ 4 Abs. 5 EStG) — Anteil über `deductible_percent`. */
    case LimitedDeductible = 'limited_deductible';

    /** Nicht abziehbar — erscheint nachrichtlich, mindert den Gewinn nicht. */
    case NotDeductible = 'not_deductible';

    public function label(): string {
        return (string) __('enums.finance.euer-category.' . $this->value);
    }

    /** Zählt zu den Betriebseinnahmen. */
    public function isIncome(): bool {
        return in_array($this, [self::Income, self::IncomeVat, self::PrivateUse], true);
    }

    /** Mindert den Gewinn — nicht abziehbare Beträge tun das ausdrücklich nicht. */
    public function isExpense(): bool {
        return in_array($this, [
            self::Expense,
            self::Depreciation,
            self::LowValueAsset,
            self::InputTax,
            self::PaidVat,
            self::LimitedDeductible,
        ], true);
    }

    /**
     * Lässt sich der Betrag aus Zahlungen ableiten?
     *
     * Abschreibungen nicht: § 11 EStG gilt für sie nicht, sie werden gebucht,
     * nicht bezahlt. Die Vorschau weist sie deshalb aus dem Journal aus und
     * kennzeichnet sie als manuell zu prüfen.
     */
    public function derivedFromPayments(): bool {
        return $this !== self::Depreciation;
    }

    /** Unterliegt der 10-Tage-Regel um den Jahreswechsel (§ 11 Abs. 1 S. 2 EStG). */
    public function subjectToTenDayRule(): bool {
        return in_array($this, [self::Income, self::IncomeVat, self::Expense, self::InputTax, self::PaidVat], true);
    }

    public function tone(): string {
        return match (true) {
            $this->isIncome() => 'success',
            $this === self::NotDeductible => 'warning',
            default => 'info',
        };
    }
}
