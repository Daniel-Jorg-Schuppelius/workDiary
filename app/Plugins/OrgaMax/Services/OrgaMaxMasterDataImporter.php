<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxMasterDataImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Services;

use App\Enums\Integration\{ConflictFieldPolicy, ImportMatchPolicy};
use App\Models\{OrgaMaxConnection, Organization};
use App\Plugins\OrgaMax\Api\OrgaMaxClient;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Services\Integration\IntegrationResolver;
use App\Services\Integration\Match\MatchProfile;
use App\Services\Integration\Profiles\{ArticleMatchProfile, CustomerMatchProfile, SupplierMatchProfile};

/**
 * Stammdaten-Projektionen (Feature 077, MVP-307/308): Kunden, Lieferanten und
 * Artikel werden paginiert gelesen und ausschließlich über den
 * {@see IntegrationResolver} zugeordnet — sichere Treffer verknüpfen per
 * ExternalReference, unsichere/mehrdeutige landen in der Integrations-Inbox.
 * Keine automatischen Schattenstammdaten (Policy AutoLinkExactOnly);
 * Preis-/Steuer-/Einheiten-Snapshot der Artikel bleibt im Reference-Payload.
 */
class OrgaMaxMasterDataImporter {
    public function __construct(private readonly IntegrationResolver $resolver) {}

    /** @return array{read: int, linked: int, inboxed: int} */
    public function importCustomers(OrgaMaxConnection $connection, OrgaMaxClient $client, int $offset, int $limit): array {
        $rows = $client->customers($offset, $limit);

        return $this->run($connection, 'customer', new CustomerMatchProfile, $rows, function (array $row): array {
            return [
                'name' => $this->personName($row),
                'company' => (string) ($row['company'] ?? $row['companyName'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'vat_id' => (string) ($row['vatId'] ?? $row['ustId'] ?? ''),
                'address_zip' => (string) ($row['zipCode'] ?? $row['zip'] ?? ''),
                'address_city' => (string) ($row['city'] ?? ''),
                'external_number' => (string) ($row['customerNumber'] ?? $row['number'] ?? ''),
            ];
        });
    }

    /** @return array{read: int, linked: int, inboxed: int} */
    public function importSuppliers(OrgaMaxConnection $connection, OrgaMaxClient $client, int $offset, int $limit): array {
        $rows = $client->suppliers($offset, $limit);

        return $this->run($connection, 'supplier', new SupplierMatchProfile, $rows, function (array $row): array {
            return [
                'name' => $this->personName($row),
                'company' => (string) ($row['company'] ?? $row['companyName'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'vat_id' => (string) ($row['vatId'] ?? $row['ustId'] ?? ''),
                'address_zip' => (string) ($row['zipCode'] ?? $row['zip'] ?? ''),
                'external_number' => (string) ($row['supplierNumber'] ?? $row['number'] ?? ''),
            ];
        });
    }

    /** @return array{read: int, linked: int, inboxed: int} */
    public function importArticles(OrgaMaxConnection $connection, OrgaMaxClient $client, int $offset, int $limit): array {
        $rows = $client->articles($offset, $limit);

        return $this->run($connection, 'article', new ArticleMatchProfile, $rows, function (array $row): array {
            return [
                'number' => (string) ($row['articleNumber'] ?? $row['number'] ?? ''),
                'name' => (string) ($row['name'] ?? $row['title'] ?? ''),
                'gtin' => (string) ($row['ean'] ?? $row['gtin'] ?? ''),
                // Snapshot für die Übergabe (MVP-308) — bleibt im Payload der
                // ExternalReference, WorkDiary-Preise werden nicht überschrieben.
                'unit' => (string) ($row['unit'] ?? ''),
                'price_net' => (string) ($row['price'] ?? $row['netPrice'] ?? ''),
                'tax_rate' => (string) ($row['taxRate'] ?? $row['vatRate'] ?? ''),
                'account' => (string) ($row['account'] ?? $row['ledgerAccount'] ?? ''),
            ];
        });
    }

    /**
     * @param array<int, mixed> $rows
     * @param callable(array<string, mixed>): array<string, mixed> $map
     * @return array{read: int, linked: int, inboxed: int}
     */
    private function run(OrgaMaxConnection $connection, string $externalType, MatchProfile $profile, array $rows, callable $map): array {
        /** @var Organization $organization */
        $organization = Organization::query()->withoutGlobalScopes()->findOrFail($connection->organization_id);
        $linked = 0;
        $inboxed = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $externalId = (string) ($row['id'] ?? $row['uuid'] ?? '');
            if ($externalId === '') {
                continue;
            }

            $outcome = $this->resolver->resolve(
                $organization,
                OrgaMaxPlugin::ID,
                $profile,
                $externalType,
                $externalId,
                array_filter($map($row), fn($v) => $v !== ''),
                $row,
                ImportMatchPolicy::AutoLinkExactOnly,
                ConflictFieldPolicy::ManualReview,
                'orgamax:sync',
            );

            $outcome->isResolved() ? $linked++ : $inboxed++;
        }

        return ['read' => count($rows), 'linked' => $linked, 'inboxed' => $inboxed];
    }

    /** @param array<string, mixed> $row */
    private function personName(array $row): string {
        $name = (string) ($row['name'] ?? '');
        if ($name !== '') {
            return $name;
        }

        return trim(((string) ($row['firstName'] ?? '')) . ' ' . ((string) ($row['lastName'] ?? '')));
    }
}
