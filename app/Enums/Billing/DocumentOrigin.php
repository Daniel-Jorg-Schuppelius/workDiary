<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentOrigin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Billing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Führendes System eines Belegs (Feature 105, MVP-542). Ersetzt den früheren
 * Tab „Belege" durch einen Filter: wo der Beleg herkommt, ist eine
 * Eigenschaft, keine eigene Seite.
 *
 * Ein System erscheint hier, sobald es einen lokalen Belegspiegel führt —
 * Lexoffice über `lexoffice_vouchers`, orgaMAX seit MVP-670 über
 * `orgamax_invoices`. Weitere Faktura-Plugins (SevDesk, easybill,
 * InvoicePlane, JTL) spiegeln seit MVP-731 in dieselbe Tabelle — je
 * Anbieter unterscheidet sie nur die `plugin_id`.
 */
enum DocumentOrigin: string implements HasLabel {
    use HasOptions;

    case Local = 'local';
    case Lexoffice = 'lexoffice';
    case OrgaMax = 'orgamax';
    // MVP-611: Belegspiegel über die anbieterneutrale `accounting_vouchers`.
    case SevDesk = 'sevdesk';
    // MVP-731 (Vollscan G18): dieselbe Tabelle, weitere Anbieter.
    case Easybill = 'easybill';
    case InvoicePlane = 'invoiceplane';
    case JtlWawi = 'jtl_wawi';

    public function label(): string {
        return (string) __('enums.billing.origin.' . $this->value);
    }
}
