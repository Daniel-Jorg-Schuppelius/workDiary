<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupervisoryAuthorityDirectory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Models\Privacy\Incident;

/**
 * Gepruefte offizielle Einstiege fuer Art.-33-Meldungen. Die Liste ist bewusst
 * klein: Ohne Bundesland und Organisationstyp darf keine Zuständigkeit geraten
 * werden. Weitere Behoerden koennen als freier Eintrag dokumentiert werden.
 */
class SupervisoryAuthorityDirectory {
    public function __construct(private readonly GermanFederalStateResolver $stateResolver) {}

    /** @return array<string, array{name: string, url: string, hint: string, state: string}> */
    public function reportingPortals(): array {
        return [
            'baylda' => [
                'name' => 'Bayerisches Landesamt für Datenschutzaufsicht (nicht-öffentlicher Bereich)',
                'url' => 'https://www.lda.bayern.de/de/datenpanne.html',
                'hint' => 'Online-Service für Erst- und Folgemeldungen',
                'state' => 'BY',
            ],
            'lfdi-bw' => [
                'name' => 'LfDI Baden-Württemberg',
                'url' => 'https://www.baden-wuerttemberg.datenschutz.de/datenpanne-melden/',
                'hint' => 'Online-Meldung für Verantwortliche',
                'state' => 'BW',
            ],
        ];
    }

    /** @return array{name: string, url: string, hint: string, state: string}|null */
    public function find(?string $key): ?array {
        if ($key === null || $key === '') {
            return null;
        }

        return $this->reportingPortals()[$key] ?? null;
    }

    public function authorityDirectoryUrl(): string {
        return 'https://www.datenschutzkonferenz-online.de/datenschutzaufsichtsbehoerden.html';
    }

    /**
     * @return array{state_code: string, state_name: string, postal_code: string, source: string, portal_key: string|null}|null
     */
    public function recommendation(Incident $incident): ?array {
        $isProcessor = $incident->controller_role->value === 'processor';
        if ($isProcessor) {
            $customer = $incident->controllerCustomer;
            if ($customer === null) {
                return null;
            }
            $address = $customer->primaryAddress();
            $postalCode = $address?->zip ?: $customer->address_zip;
            $country = $address?->country_code ?: $customer->country;
            $source = 'customer';
        } else {
            $branding = $incident->organization?->brandingSettings() ?? [];
            $contact = (array) ($branding['contact'] ?? []);
            $postalCode = isset($contact['postal_code']) ? (string) $contact['postal_code'] : null;
            $country = isset($contact['country']) ? (string) $contact['country'] : null;
            $source = 'organization';
        }

        $state = $this->stateResolver->resolve($postalCode, $country);
        if ($state === null) {
            return null;
        }

        $portalKey = collect($this->reportingPortals())
            ->search(fn(array $portal): bool => $portal['state'] === $state['code']);

        return [
            'state_code' => $state['code'],
            'state_name' => $state['name'],
            'postal_code' => $state['postal_code'],
            'source' => $source,
            'portal_key' => is_string($portalKey) ? $portalKey : null,
        ];
    }
}
