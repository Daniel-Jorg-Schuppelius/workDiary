<?php
/*
 * Created on   : Sat Jul 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconcilesByMarker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Targets\Concerns;

use App\Models\ExternalReference;
use App\Models\Finance\BillingTransfer;

/**
 * Gemeinsame Idempotenz-/Nachweis-Bausteine der Marker-Reconciliation
 * (Vollaudit 2026-07, M41): Transfer-Idempotenz-Lookup und der
 * Übergabenachweis-Datensatz waren in SevDesk/Easybill/OrgaMax wortgleich.
 * Der provider-spezifische Teil (findByMarker-Scan, Payload-Aufbau,
 * ConnectException→outcome_unclear-Fehlerschlüssel) bleibt bewusst in den
 * Targets — unterschiedliche Clients und Fehlersemantik.
 */
trait ReconcilesByMarker {
    /** Bereits übergeben? Harte Idempotenz je Transfer über den Nachweis. */
    private function existingReference(BillingTransfer $transfer, string $pluginId, string $externalType): ?ExternalReference {
        $existing = ExternalReference::query()
            ->forPlugin($transfer->organization_id, $pluginId, $externalType)
            ->forReferenceable($transfer)
            ->first();

        return $existing instanceof ExternalReference ? $existing : null;
    }

    /**
     * Übergabenachweis mit Quellmarker und Adoptions-Flag anlegen.
     *
     * @param  array<string, mixed>  $remote  Remote-Objekt (Rechnung/Beleg/Auftrag)
     */
    private function storeMarkerReference(BillingTransfer $transfer, string $pluginId, string $externalType, string $source, string $payloadKey, array $remote, string $marker, bool $adopted): ExternalReference {
        return ExternalReference::create([
            'organization_id' => $transfer->organization_id,
            'plugin_id' => $pluginId,
            'external_type' => $externalType,
            'referenceable_type' => $transfer->getMorphClass(),
            'referenceable_id' => $transfer->getKey(),
            'external_id' => (string) $remote['id'],
            'payload' => [
                'source' => $source,
                'marker' => $marker,
                'adopted_via_reconciliation' => $adopted,
                $payloadKey => $remote,
            ],
            'synced_at' => now(),
        ]);
    }
}
