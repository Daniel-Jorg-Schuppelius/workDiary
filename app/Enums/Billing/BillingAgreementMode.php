<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingAgreementMode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Billing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Abrechnungsweg einer Kunden-Sonderkondition (Feature 098): rechnungsloses
 * Kundenkonto mit laufendem Saldo (account), monatliche Rechnung über die
 * normale Fakturierungs-Pipeline (invoice) oder Pauschal-/Retainer-Modell mit
 * Lexoffice-Hoheit (retainer): feste Monatspauschale als Lexoffice-Rechnung,
 * lokaler Leistungssaldo, Lexoffice-Zahlstatus speist die Zahlungen.
 * Bewusst NICHT „BillingMode" — der Name ist durch
 * {@see \App\Enums\Finance\BillingMode} (Rechnungshoheit) belegt.
 */
enum BillingAgreementMode: string implements HasLabel {
    use HasOptions;

    case Account = 'account';
    case Invoice = 'invoice';
    case Retainer = 'retainer';

    public function label(): string {
        return (string) __('enums.billing.agreement-mode.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Account => 'info',
            self::Invoice => 'success',
            self::Retainer => 'secondary',
        };
    }
}
