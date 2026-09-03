<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CompanyMappingMode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Reselling;

use App\Enums\Contracts\HasLabel;

/**
 * Wie eine Marketplace-Firma abgerechnet wird.
 */
enum CompanyMappingMode: string implements HasLabel {
    /** Die Firma ist selbst der Kunde. */
    case Customer = 'customer';

    /** Die Rechnung geht an einen Partner, der sie an die Firma weiterreicht (Fremdkunde). */
    case Partner = 'partner';

    /** Direkt ein Lexoffice-Kontakt (UUID). */
    case Contact = 'contact';

    public function label(): string {
        return (string) __('reselling.mapping.mode.' . $this->value);
    }
}
