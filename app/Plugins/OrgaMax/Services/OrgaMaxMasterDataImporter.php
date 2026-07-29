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

use APIToolkit\Contracts\Abstracts\NamedEntity;
use App\Enums\Integration\{ConflictFieldPolicy, ImportMatchPolicy};
use App\Models\{OrgaMaxConnection, Organization};
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Services\Integration\IntegrationResolver;
use App\Services\Integration\Match\MatchProfile;
use App\Services\Integration\Profiles\{ArticleMatchProfile, CustomerMatchProfile, SupplierMatchProfile};
use Orgamax\API\Client;
use Orgamax\API\Endpoints\{ArticlesEndpoint, CustomersEndpoint, SuppliersEndpoint};
use Orgamax\Entities\Articles\Article;
use Orgamax\Entities\Customers\Customer;
use Orgamax\Entities\Suppliers\Supplier;

/**
 * Stammdaten-Projektionen (Feature 077, MVP-307/308): Kunden, Lieferanten und
 * Artikel werden paginiert über das orgaMAX-SDK gelesen und ausschließlich
 * über den {@see IntegrationResolver} zugeordnet — sichere Treffer verknüpfen
 * per ExternalReference, unsichere/mehrdeutige landen in der Integrations-Inbox.
 * Keine automatischen Schattenstammdaten (Policy AutoLinkExactOnly);
 * Preis-/Steuer-/Einheiten-Snapshot der Artikel bleibt im Reference-Payload.
 */
class OrgaMaxMasterDataImporter {
    public function __construct(private readonly IntegrationResolver $resolver) {}

    /** @return array{read: int, linked: int, inboxed: int} */
    public function importCustomers(OrgaMaxConnection $connection, Client $client, int $offset, int $limit): array {
        $rows = (new CustomersEndpoint($client))->search(self::page($offset, $limit))?->getValues() ?? [];

        return $this->run($connection, 'customer', new CustomerMatchProfile, $rows, function (Customer $customer): array {
            $address = $customer->getCustomerDefaultAddress()?->getBillingAddress() ?? $customer->getAddress();

            return [
                'name' => self::personName($customer->getName(), $customer->getFirstName(), $customer->getLastName()),
                'company' => (string) ($customer->getCompanyName() ?? $address?->getCompanyName() ?? ''),
                'email' => (string) ($customer->getEmail() ?? ''),
                'vat_id' => (string) ($customer->getVatNumber() ?? ''),
                'address_zip' => (string) ($address?->getZipCode() ?? ''),
                'address_city' => (string) ($address?->getCity() ?? ''),
                'external_number' => (string) ($customer->getNumber() ?? ''),
            ];
        });
    }

    /** @return array{read: int, linked: int, inboxed: int} */
    public function importSuppliers(OrgaMaxConnection $connection, Client $client, int $offset, int $limit): array {
        $rows = (new SuppliersEndpoint($client))->search(self::page($offset, $limit))?->getValues() ?? [];

        return $this->run($connection, 'supplier', new SupplierMatchProfile, $rows, function (Supplier $supplier): array {
            $address = $supplier->getAddress();

            return [
                // Die Lieferanten-Ressource führt Name und Firma in einem Feld;
                // die Firmenangabe steht nur an der Adresse.
                'name' => self::personName($supplier->getName(), $address?->getFirstName(), $address?->getLastName()),
                'company' => (string) ($address?->getCompanyName() ?? ''),
                'email' => (string) ($supplier->getEmail() ?? ''),
                'address_zip' => (string) ($address?->getZipCode() ?? ''),
                'address_city' => (string) ($address?->getCity() ?? ''),
                'external_number' => (string) ($supplier->getNumber() ?? ''),
            ];
        });
    }

    /** @return array{read: int, linked: int, inboxed: int} */
    public function importArticles(OrgaMaxConnection $connection, Client $client, int $offset, int $limit): array {
        $rows = (new ArticlesEndpoint($client))->search(self::page($offset, $limit))?->getValues() ?? [];

        return $this->run($connection, 'article', new ArticleMatchProfile, $rows, function (Article $article): array {
            return [
                'number' => (string) ($article->getNumber() ?? ''),
                'name' => (string) ($article->getTitle() ?? ''),
                // Snapshot für die Übergabe (MVP-308) — bleibt im Payload der
                // ExternalReference, WorkDiary-Preise werden nicht überschrieben.
                'unit' => (string) ($article->getUnit() ?? ''),
                'price_net' => $article->getPrice() !== null ? (string) $article->getPrice() : '',
                'tax_rate' => $article->getVatPercent() !== null ? (string) $article->getVatPercent() : '',
                'account' => (string) ($article->getBookkeepingAccountId() ?? ''),
            ];
        });
    }

    /**
     * @template TEntity of NamedEntity
     *
     * @param  array<int, TEntity>  $rows
     * @param  callable(TEntity): array<string, mixed>  $map
     * @return array{read: int, linked: int, inboxed: int}
     */
    private function run(OrgaMaxConnection $connection, string $externalType, MatchProfile $profile, array $rows, callable $map): array {
        /** @var Organization $organization */
        $organization = Organization::query()->withoutGlobalScopes()->findOrFail($connection->organization_id);
        $linked = 0;
        $inboxed = 0;

        foreach ($rows as $row) {
            $externalId = self::externalId($row);
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
                $row->toArray(),
                ImportMatchPolicy::AutoLinkExactOnly,
                ConflictFieldPolicy::ManualReview,
                'orgamax:sync',
            );

            $outcome->isResolved() ? $linked++ : $inboxed++;
        }

        return ['read' => count($rows), 'linked' => $linked, 'inboxed' => $inboxed];
    }

    /** @return array{offset: int, limit: int} */
    private static function page(int $offset, int $limit): array {
        return ['offset' => $offset, 'limit' => $limit];
    }

    /** IDs sind je Ressource string oder int typisiert — der Resolver führt sie als String. */
    private static function externalId(NamedEntity $entity): string {
        $id = method_exists($entity, 'getId') ? $entity->getId() : null;

        return is_scalar($id) ? (string) $id : '';
    }

    private static function personName(?string $name, ?string $firstName, ?string $lastName): string {
        if ($name !== null && $name !== '') {
            return $name;
        }

        return trim(((string) $firstName) . ' ' . ((string) $lastName));
    }
}
