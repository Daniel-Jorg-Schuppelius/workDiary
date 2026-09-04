<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HolderResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Models\{Customer, ForeignCustomer, Organization};
use App\Models\Reselling\CompanyMapping;
use App\Services\Reselling\Marketplace\{MarketplaceCompany, NameTokenMatcher};
use App\Support\Sqid;
use Illuminate\Support\Collection;

/**
 * Halter einer importierten Firma (Feature 152, MVP-759) — nur aus dem
 * Datenbestand, nie aus Lexoffice, nie geraten: gespeicherte Zuordnung
 * (151-Dialog), eindeutiger Fremdkunde (Endkunde eines Partners), eindeutiger
 * Kunde (Name oder Kundennummer). Alles andere landet in der Inbox.
 */
final class HolderResolver {
    public const SOURCE_STORED = 'stored';
    public const SOURCE_FOREIGN = 'foreign';
    public const SOURCE_CUSTOMER = 'customer';

    /**
     * @param  array<string, string>  $stored  Firmen-Schlüssel/-Name → Ziel (`CompanyMapping::targetsFor`)
     * @return array{customer_id: int|null, foreign_customer_id: int|null, source: string}|null
     */
    public function resolve(Organization $organization, MarketplaceCompany $company, array $stored = []): ?array {
        $target = $stored[$company->key] ?? $stored[$company->normalizedName()] ?? null;
        if (is_string($target)) {
            $resolved = $this->fromTarget($organization, $company, $target);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $foreign = $this->matchForeignCustomer($organization, $company->name);
        if ($foreign !== null) {
            return ['customer_id' => null, 'foreign_customer_id' => $foreign->id, 'source' => self::SOURCE_FOREIGN];
        }

        $customer = $this->matchCustomer($organization, $company);
        if ($customer !== null) {
            return ['customer_id' => $customer->id, 'foreign_customer_id' => null, 'source' => self::SOURCE_CUSTOMER];
        }

        return null;
    }

    /**
     * Vorschläge für die Inbox: Kunden und Fremdkunden mit ähnlichem Namen.
     *
     * @return array{customers: Collection<int, Customer>, foreign: Collection<int, ForeignCustomer>}
     */
    public function suggestions(Organization $organization, string $companyName): array {
        $wanted = MarketplaceCompany::normalizeName($companyName);
        $customers = Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)
            ->get(['id', 'name', 'company', 'number'])
            ->filter(static fn(Customer $c): bool => self::similar($c->name, $companyName, $wanted) || self::similar((string) $c->company, $companyName, $wanted))
            ->values();
        $foreign = ForeignCustomer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereNull('archived_at')
            ->with('customer:id,name')
            ->get(['id', 'name', 'company', 'customer_id'])
            ->filter(static fn(ForeignCustomer $f): bool => self::similar($f->name, $companyName, $wanted) || self::similar((string) $f->company, $companyName, $wanted))
            ->values();

        return ['customers' => $customers, 'foreign' => $foreign];
    }

    /**
     * @return array{customer_id: int|null, foreign_customer_id: int|null, source: string}|null
     */
    private function fromTarget(Organization $organization, MarketplaceCompany $company, string $target): ?array {
        if (str_starts_with($target, 'customer:')) {
            $id = Sqid::decode(Customer::class, substr($target, 9));
            $customer = $id === null ? null : Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->find($id);

            return $customer === null ? null : ['customer_id' => $customer->id, 'foreign_customer_id' => null, 'source' => self::SOURCE_STORED];
        }
        if (str_starts_with($target, 'partner:')) {
            $id = Sqid::decode(Customer::class, substr($target, 8));
            $partner = $id === null ? null : Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->find($id);
            if ($partner === null) {
                return null;
            }
            $foreign = $this->foreignCustomerUnder($organization, $partner, $company->name);

            return ['customer_id' => null, 'foreign_customer_id' => $foreign->id, 'source' => self::SOURCE_STORED];
        }

        return null; // Lexoffice-Kontakt-UUID: kein Halter im Register
    }

    /**
     * Fremdkunde des Partners mit diesem Namen — vorhanden oder neu angelegt
     * (wie der Zuordnungsdialog aus 151).
     */
    public function foreignCustomerUnder(Organization $organization, Customer $partner, string $companyName): ForeignCustomer {
        $wanted = MarketplaceCompany::normalizeName($companyName);
        $existing = ForeignCustomer::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('customer_id', $partner->id)
            ->whereNull('archived_at')
            ->get()
            ->first(static fn(ForeignCustomer $f): bool => MarketplaceCompany::normalizeName($f->name) === $wanted || NameTokenMatcher::matches($f->name, $companyName));
        if ($existing !== null) {
            return $existing;
        }

        return ForeignCustomer::query()->create([
            'organization_id' => $organization->id,
            'customer_id' => $partner->id,
            'name' => $companyName,
            'company' => $companyName,
        ]);
    }

    private function matchForeignCustomer(Organization $organization, string $companyName): ?ForeignCustomer {
        $wanted = MarketplaceCompany::normalizeName($companyName);
        if ($wanted === '') {
            return null;
        }
        $exact = [];
        $fuzzy = [];
        $candidates = ForeignCustomer::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNull('archived_at')
            ->get();
        foreach ($candidates as $foreign) {
            foreach ([$foreign->name, $foreign->company] as $name) {
                if (! is_string($name) || $name === '') {
                    continue;
                }
                if (MarketplaceCompany::normalizeName($name) === $wanted) {
                    $exact[$foreign->id] = $foreign;
                    break;
                }
                if (NameTokenMatcher::matches($name, $companyName)) {
                    $fuzzy[$foreign->id] = $foreign;
                }
            }
        }
        $matches = $exact !== [] ? $exact : $fuzzy;
        // Ein Treffer ist eindeutig; mehrere nur, wenn sie zum selben Partner gehören — dann der erste.
        $partners = array_unique(array_map(static fn(ForeignCustomer $f): int => (int) $f->customer_id, $matches));
        if ($matches === [] || count($partners) !== 1) {
            return null;
        }

        return array_values($matches)[0];
    }

    private function matchCustomer(Organization $organization, MarketplaceCompany $company): ?Customer {
        $wanted = $company->normalizedName();
        $query = Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id);
        $matches = [];
        if ($company->partnerCustomerNumber !== null && $company->partnerCustomerNumber !== '') {
            foreach ((clone $query)->where('number', $company->partnerCustomerNumber)->get() as $customer) {
                $matches[$customer->id] = $customer;
            }
        }
        if ($matches === [] && $wanted !== '') {
            foreach ($query->get(['id', 'name', 'company', 'number']) as $customer) {
                if (MarketplaceCompany::normalizeName($customer->name) === $wanted || MarketplaceCompany::normalizeName((string) $customer->company) === $wanted) {
                    $matches[$customer->id] = $customer;
                }
            }
        }

        return count($matches) === 1 ? array_values($matches)[0] : null;
    }

    private static function similar(string $name, string $companyName, string $wanted): bool {
        if ($name === '') {
            return false;
        }

        return MarketplaceCompany::normalizeName($name) === $wanted || NameTokenMatcher::matches($name, $companyName);
    }
}
