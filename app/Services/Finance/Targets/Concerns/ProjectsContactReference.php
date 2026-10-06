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
 * Kunde im Fremdsystem eines Fakturaziels: gespeicherte Referenz, sonst dort
 * suchen, sonst anlegen — und die Referenz festhalten. Suchschlüssel und
 * Nutzlast nennt das Ziel.
 */
trait ProjectsContactReference {
    /**
     * @param  callable(): (array<string, mixed>|null)  $find  Treffer im Fremdsystem (mit `id`) oder null
     * @param  callable(): array<string, mixed>  $create  legt an und liefert den Datensatz (mit `id`)
     * @param  string  $unclearMessage  Meldung, wenn der Ausgang des Anlegens offen bleibt
     */
    private function projectContact(Customer $customer, int $organizationId, string $pluginId, string $externalType, callable $find, callable $create, string $unclearMessage): ExternalReference {
        $existing = ExternalReference::query()
            ->forPlugin($organizationId, $pluginId, $externalType)
            ->forReferenceable($customer)
            ->first();
        if ($existing instanceof ExternalReference) {
            return $existing;
        }

        $matched = $find();
        if ($matched === null) {
            try {
                $matched = $create();
            } catch (ConnectException) {
                // Ausgang unklar — der nächste Lauf findet den Kontakt über
                // seine Nummer wieder, statt ihn doppelt anzulegen.
                throw new RuntimeException($unclearMessage);
            }
        }

        $externalId = (string) ($matched['id'] ?? '');
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

    /**
     * Erster Treffer, dessen Feld genau der Kundennummer entspricht.
     *
     * @param  iterable<mixed>  $rows
     * @return array<string, mixed>|null
     */
    private static function rowByNumber(iterable $rows, string $field, string $number): ?array {
        if ($number === '') {
            return null;
        }
        foreach ($rows as $row) {
            if (is_array($row) && (string) ($row[$field] ?? '') === $number && ! empty($row['id'] ?? null)) {
                return $row;
            }
        }

        return null;
    }
}
