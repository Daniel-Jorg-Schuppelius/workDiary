<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainInvoiceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Domain;

use App\Enums\Domain\DomainCapabilityArea;
use App\Models\Domain\{DomainExternalInvoice, DomainProviderConnection};
use App\Plugins\Support\Domain\DomainCapabilityMatrix;
use Illuminate\Support\Collection;

/**
 * Capability-gegatete Rechnungssicht (Feature 083, MVP-393). Der dokumentierte
 * Vertrag belegt WEDER Rechnungsliste NOCH Rechnungs-PDF-Download. Ohne
 * verifizierte Capability liefert der Service einen erklärten Blocked-State und
 * NIEMALS eine synthetische Rechnung aus Accounting-Zeilen.
 */
class DomainInvoiceService {
    /** Ist die Rechnungs-Capability für diese Verbindung belegt? */
    public function isAvailable(DomainProviderConnection $connection): bool {
        return DomainCapabilityMatrix::fromStored($connection->capabilities)
            ->allows(DomainCapabilityArea::Invoices);
    }

    /**
     * Rechnungsmetadaten — nur wenn die Capability belegt ist; sonst leer
     * (die UI erklärt die API-Grenze).
     *
     * @return Collection<int, DomainExternalInvoice>
     */
    public function list(DomainProviderConnection $connection): Collection {
        if (! $this->isAvailable($connection)) {
            /** @var Collection<int, DomainExternalInvoice> */
            return new Collection();
        }

        return DomainExternalInvoice::query()
            ->where('connection_id', $connection->id)
            ->orderByDesc('invoice_date')
            ->get();
    }

    /** Erklärende Begründung für den Blocked-State (i18n-Key). */
    public function blockedReason(): string {
        return (string) __('domain.invoices.blocked_reason');
    }
}
