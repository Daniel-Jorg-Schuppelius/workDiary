<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskInvoiceNumberPull.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Services;

use App\Models\Integration\ExternalReference;
use App\Plugins\SevDesk\Api\SevDeskClientFactory;
use App\Plugins\SevDesk\SevDeskPlugin;

/**
 * Holt Nummer und Status übergebener sevDesk-Rechnungen an die Referenz
 * (MVP-1158, Befund B6): Der Entwurf bekommt seine Nummer erst in sevDesk,
 * und sevDesk-Rechnungen werden nicht gespiegelt.
 */
final class SevDeskInvoiceNumberPull {
    /** Bezahlt (1000) ändert sich nicht mehr. */
    private const FINAL_STATUS = '1000';

    public function __construct(private readonly SevDeskClientFactory $clients) {}

    /** @return int Anzahl aktualisierter Referenzen */
    public function pull(int $organizationId, int $limit = 25): int {
        $references = ExternalReference::query()
            ->forPlugin($organizationId, SevDeskPlugin::ID, SevDeskTarget::EXT_TYPE_INVOICE)
            ->where(fn ($q) => $q->whereNull('external_number')->orWhereNull('payload->status')->orWhere('payload->status', '!=', self::FINAL_STATUS))
            ->orderBy('synced_at')
            ->limit($limit)
            ->get();
        if ($references->isEmpty()) {
            return 0;
        }

        $client = $this->clients->for($organizationId);
        $updated = 0;
        foreach ($references as $reference) {
            $invoice = $client->invoice((string) $reference->external_id);
            $number = trim((string) ($invoice['invoiceNumber'] ?? ''));
            $reference->forceFill([
                'external_number' => $number !== '' ? $number : $reference->external_number,
                'payload' => ['status' => (string) ($invoice['status'] ?? '')] + (array) $reference->payload,
                'synced_at' => now(),
            ])->save();
            $updated++;
        }

        return $updated;
    }
}
