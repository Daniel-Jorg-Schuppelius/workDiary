<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectsContactReference.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Targets\Concerns;

use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use GuzzleHttp\Exception\ConnectException;
use RuntimeException;

/**
 * Kunde im Fremdsystem eines Fakturaziels: gespeicherte Referenz, sonst über
 * den Kontakt-Push des Plugins. Der sucht über die Nummer, nimmt nur einen
 * Treffer, der derselbe Kunde ist, überschreibt ihn nicht, wenn die
 * Buchhaltung die Stammdaten führt, und übernimmt eine vom Programm vergebene
 * Nummer (MVP-1133). Die Referenz wird festgehalten.
 */
trait ProjectsContactReference {
    /**
     * @param  callable(): string  $push  überträgt den Kunden und liefert die externe ID
     * @param  string  $unclearMessage  Meldung, wenn der Ausgang des Anlegens offen bleibt
     */
    private function projectContact(Customer $customer, int $organizationId, string $pluginId, string $externalType, callable $push, string $unclearMessage): ExternalReference {
        $existing = ExternalReference::query()
            ->forPlugin($organizationId, $pluginId, $externalType)
            ->forReferenceable($customer)
            ->first();
        if ($existing instanceof ExternalReference) {
            return $existing;
        }

        try {
            $externalId = (string) $push();
        } catch (ConnectException) {
            // Ausgang unklar — der nächste Lauf findet den Kontakt über
            // seine Nummer wieder, statt ihn doppelt anzulegen.
            throw new RuntimeException($unclearMessage);
        }
        if ($externalId === '') {
            throw new RuntimeException($pluginId . ' contact projection returned no id.');
        }

        return ExternalReference::updateOrCreate(
            [
                'plugin_id' => $pluginId,
                'external_type' => $externalType,
                'referenceable_type' => $customer->getMorphClass(),
                'referenceable_id' => $customer->getKey(),
            ],
            [
                'organization_id' => $organizationId,
                'external_id' => $externalId,
                'synced_at' => now(),
            ],
        );
    }
}
