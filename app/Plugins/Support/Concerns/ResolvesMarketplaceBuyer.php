<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesMarketplaceBuyer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Concerns;

use App\Models\{Customer, ExternalReference, Organization};
use App\Services\Integration\Match\MatchProfile;
use Illuminate\Database\Eloquent\Model;

/**
 * Käuferauflösung Inbox-First für Marktplatz-Importe (Vollscan 2026-08-23,
 * B19 — Etsy hatte das Billbee-Muster wörtlich kopiert; VOR der dritten Kopie
 * durch Amazon/JTL-Orders extrahiert): bestehende Referenz/eindeutiger
 * Treffer verlinkt, sonst Inbox-Vorschlag; nach einer Verlinkung werden
 * offene Spiegelzeilen desselben Käufers nachgezogen.
 *
 * Erwartet `$this->resolver` (IntegrationResolver) am nutzenden Service.
 */
trait ResolvesMarketplaceBuyer {
    /**
     * Liefert den verlinkten Kunden oder null (⇒ Zeile bleibt als
     * Inbox-Vorschlag offen — der Aufrufer zählt staged/linked selbst).
     *
     * @param  array<string, ?string>  $attributes  bereits extrahierte Match-Attribute (name/email …)
     * @param  array<string, mixed>  $payload  Roh-Käuferdaten für den Inbox-Vorschlag
     * @param  class-string<Model>  $mirrorModel  Spiegeltabelle mit buyer_external_id/customer_id/inbox_status
     */
    private function resolveMarketplaceBuyer(
        Organization $organization,
        MatchProfile $profile,
        string $pluginId,
        string $buyerExternalId,
        array $attributes,
        array $payload,
        string $source,
        string $mirrorModel,
        string $linkedStatus,
    ): ?Customer {
        $outcome = $this->resolver->resolve(
            $organization,
            $pluginId,
            $profile,
            'customer',
            $buyerExternalId,
            array_filter($attributes, static fn (?string $value): bool => $value !== null && $value !== ''),
            $payload,
            source: $source,
        );

        if (! $outcome->isResolved()) {
            // Conflict-Item trotz bestehender Referenz: die ZUORDNUNG steht
            // fest — nur der Datenabgleich bleibt sichtbar in der Inbox.
            $customer = $this->marketplaceCustomerByReference($organization, $pluginId, $buyerExternalId);
            if (! $customer instanceof Customer) {
                return null;
            }
        } else {
            /** @var Customer $customer */
            $customer = $outcome->model;
        }

        // Wiederkäufer: offene Spiegelzeilen desselben Käufers nachziehen.
        $mirrorModel::query()
            ->where('organization_id', $organization->id)
            ->where('buyer_external_id', $buyerExternalId)
            ->whereNull('customer_id')
            ->update(['customer_id' => $customer->id, 'inbox_status' => $linkedStatus]);

        return $customer;
    }

    /** Kunde aus bestehender Käufer-Referenz (Zuordnung bereits entschieden). */
    private function marketplaceCustomerByReference(Organization $organization, string $pluginId, string $buyerExternalId): ?Customer {
        $reference = ExternalReference::query()
            ->forPlugin($organization, $pluginId, 'customer')
            ->forExternalId($buyerExternalId)
            ->first();
        $referenceable = $reference?->referenceable;

        return $referenceable instanceof Customer ? $referenceable : null;
    }
}
