<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Abrechnungsregeln, Kundenkonten, Belegfluss“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class BillingManifest extends Manifest {
    public function code(): string {
        return 'billing';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Abrechnungsregeln, Kundenkonten, Belegfluss';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Billing',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'customer_account_payments',
            'customer_billing_agreements',
            'customer_billing_rates',
            'customer_billing_statements',
        ];
    }
}
