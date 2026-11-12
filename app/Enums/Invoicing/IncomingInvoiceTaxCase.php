<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceTaxCase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Steuerfall einer Eingangsrechnung (MVP-1621): Vorsteuer aus der Rechnung
 * oder Steuerschuld des Empfängers, der Umsatz- und Vorsteuer selbst bucht.
 */
enum IncomingInvoiceTaxCase: string implements HasLabel {
    use HasOptions;

    case Regular = 'regular';
    /** § 13b Abs. 1 UStG: sonstige Leistung eines Unternehmers aus dem übrigen Gemeinschaftsgebiet (Kz 46/47). */
    case ReverseChargeEu = 'reverse_charge_eu';
    /** § 13b Abs. 2 UStG: Bauleistungen, Gebäudereinigung, Schrott, Mobilfunkgeräte, Werklieferungen ausländischer Unternehmer u. a. (Kz 84/85). */
    case ReverseCharge = 'reverse_charge';
    /** § 1a UStG (Kz 89/93). */
    case IntraCommunityAcquisition = 'intra_community_acquisition';

    public function label(): string {
        return (string) __('enums.invoicing.incoming_invoice_tax_case.' . $this->value);
    }

    public function isSelfAssessed(): bool {
        return $this !== self::Regular;
    }
}
