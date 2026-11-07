<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoiceProviders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\ExternalInvoices;

use App\Models\Finance\BillingTransfer;
use App\Modules\ModuleRegistry;
use App\Services\Finance\Contracts\ExternalInvoiceProvider;

/** Angemeldete Fakturasysteme mit Rechnungshoheit (Feature 196), nach Plugin-ID. */
final class ExternalInvoiceProviders {
    public function __construct(private readonly ModuleRegistry $modules) {}

    public function for(string $key): ?ExternalInvoiceProvider {
        foreach ($this->modules->extensions(ExternalInvoiceProvider::class) as $class) {
            $provider = app($class);
            if ($provider instanceof ExternalInvoiceProvider && $provider->key() === $key) {
                return $provider;
            }
        }

        return null;
    }

    public function forTransfer(BillingTransfer $transfer): ?ExternalInvoiceProvider {
        return $this->for($transfer->target->value);
    }
}
