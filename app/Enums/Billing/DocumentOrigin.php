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
 * Weitere Buchhaltungs-/Faktura-Plugins (SevDesk, easybill, orgaMAX,
 * InvoicePlane, JTL) kommen mit ihrer jeweiligen Spiegelung hinzu.
 */
enum DocumentOrigin: string implements HasLabel {
    use HasOptions;

    case Local = 'local';
    case Lexoffice = 'lexoffice';

    public function label(): string {
        return (string) __('enums.billing.origin.' . $this->value);
    }
}
