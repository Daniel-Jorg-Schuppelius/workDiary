<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportAssignedProviderInvoice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Reselling;

use App\Events\Invoicing\IncomingInvoiceAssigned;
use App\Jobs\Reselling\ImportIncomingProviderInvoiceJob;
use App\Listeners\ModuleListener;
use App\Services\Reselling\Register\IncomingProviderInvoiceImport;

/**
 * Nach der Zuordnung eines Lieferanten im Rechnungseingang (Feature 163) die
 * Rechnung als Einkauf übernehmen, wenn der Lieferant ein Anbieter des
 * Registers ist (Feature 185, MVP-1420). Lesen und Zuteilen laufen im Job —
 * ein PDF kann OCR brauchen.
 */
final class ImportAssignedProviderInvoice extends ModuleListener {
    public function __construct(private readonly IncomingProviderInvoiceImport $import) {}

    protected function module(): string {
        return 'reselling';
    }

    public function handle(IncomingInvoiceAssigned $event): void {
        $organization = $event->incoming->organization;
        if ($organization === null || ! $this->shouldHandle($organization) || $this->import->providerFor($event->incoming) === null) {
            return;
        }
        ImportIncomingProviderInvoiceJob::dispatch((int) $event->incoming->id, $event->incoming->matched_user_id !== null ? (int) $event->incoming->matched_user_id : null);
    }
}
