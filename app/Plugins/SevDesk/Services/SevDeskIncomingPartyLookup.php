<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskIncomingPartyLookup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Services;

use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\Organization;
use App\Plugins\PluginManager;
use App\Plugins\SevDesk\Api\{SevDeskClient, SevDeskClientFactory};
use App\Plugins\SevDesk\{SevDeskConfig, SevDeskPlugin};
use App\Services\Invoicing\Contracts\IncomingPartyLookup;
use App\Services\Invoicing\Dto\IncomingPartyCandidate;
use App\Services\Invoicing\EInvoice\IncomingInvoiceParties;
use App\Services\Stammdaten\ContactPersons;

/**
 * Kontaktsuche in sevDesk (Phase 152, MVP-1226): `GET /Contact?name=`; die
 * Kontaktliste trägt weder E-Mail noch Anschrift (E6). Personen einer
 * Organisation sind eigene Kontakte mit `parent` und werden je Treffer
 * nachgeladen; eine Privatperson ist selbst der Ansprechpartner.
 */
final class SevDeskIncomingPartyLookup implements IncomingPartyLookup {
    public function key(): string {
        return SevDeskPlugin::ID;
    }

    public function label(): string {
        return app(PluginManager::class)->get(SevDeskPlugin::ID)?->name() ?? 'sevDesk';
    }

    public function isEnabled(Organization $organization): bool {
        $config = SevDeskConfig::resolve((int) $organization->id);

        return $config['enabled'] && app(SevDeskClientFactory::class)->isConfigured((int) $organization->id);
    }

    public function lookup(IncomingEInvoice $incoming): array {
        $fields = app(IncomingInvoiceParties::class)->fieldsOf($incoming);
        if ($fields['name'] === null) {
            return [];
        }
        $candidates = [];
        $client = app(SevDeskClientFactory::class)->for((int) $incoming->organization_id);
        foreach ($client->contactsByName($fields['name']) as $row) {
            $candidate = is_array($row) ? $this->candidate($row, $client) : null;
            if ($candidate !== null) {
                $candidates[$candidate->externalId] = $candidate;
            }
        }

        return array_values($candidates);
    }

    /** @param  array<string, mixed>  $row */
    private function candidate(array $row, SevDeskClient $client): ?IncomingPartyCandidate {
        $id = (string) ($row['id'] ?? '');
        $organization = trim((string) ($row['name'] ?? ''));
        $name = $organization ?: trim(((string) ($row['surename'] ?? '')) . ' ' . ((string) ($row['familyname'] ?? '')));
        if ($id === '' || $name === '') {
            return null;
        }
        $number = trim((string) ($row['customerNumber'] ?? ''));
        $vat = trim((string) ($row['vatNumber'] ?? ''));
        $persons = [];
        if ($organization !== '') {
            foreach ($client->contactsByParent($id) as $person) {
                $entry = is_array($person) ? self::person($person, false) : null;
                if ($entry !== null) {
                    $persons[] = $entry;
                }
            }
        }

        return new IncomingPartyCandidate(
            pluginId: SevDeskPlugin::ID,
            pluginLabel: $this->label(),
            externalId: $id,
            name: $name,
            number: $number !== '' ? $number : null,
            vatId: $vat !== '' ? $vat : null,
            contact: $organization === '' ? self::person($row, true) : null,
            persons: $persons,
        );
    }

    /**
     * Person aus einem sevDesk-Kontakt: `surename` ist der Vorname, `gender`
     * m/w (oder frei), `academicTitle` der Titel.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private static function person(array $row, bool $primary): ?array {
        $gender = trim((string) ($row['gender'] ?? ''));

        return ContactPersons::fromExternal(
            match (mb_strtolower($gender)) { 'm' => 'mr', 'w', 'f' => 'ms', '' => null, default => $gender },
            is_string($row['surename'] ?? null) ? $row['surename'] : null,
            is_string($row['familyname'] ?? null) ? $row['familyname'] : null,
            is_string($row['academicTitle'] ?? null) ? $row['academicTitle'] : null,
            primary: $primary,
        );
    }
}
