<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProviderSuppliers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\SubscriptionProvider;
use App\Models\Platform\Organization;
use App\Models\Reselling\ResaleProviderSupplier;
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Collection;

/**
 * Zuordnung Lieferant → Anbieter (Feature 185, MVP-1419). Der Namensvorschlag
 * ist nur ein Vorschlag im Dialog — zugeordnet wird nie automatisch.
 */
final class ProviderSuppliers {
    /** Namensbestandteile (klein) je Anbieter für den Vorschlag. */
    private const HINTS = [
        'domainreselling' => ['united-domains', 'united domains', 'udag'],
        'qualityhosting' => ['qualityhosting', 'quality hosting', 'quality-hosting'],
        'telekom_marketplace' => ['telekom'],
    ];

    /** @var array<int, array<int, SubscriptionProvider|null>> Organisation → Lieferant → Anbieter */
    private array $cache = [];

    public function providerFor(Organization $organization, Supplier|int $supplier): ?SubscriptionProvider {
        $supplierId = $supplier instanceof Supplier ? (int) $supplier->id : $supplier;
        if (! array_key_exists($supplierId, $this->cache[$organization->id] ?? [])) {
            $this->cache[$organization->id][$supplierId] = ResaleProviderSupplier::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('supplier_id', $supplierId)
                ->first()?->provider;
        }

        return $this->cache[$organization->id][$supplierId];
    }

    public function suggest(string $supplierName): ?SubscriptionProvider {
        $name = mb_strtolower($supplierName);
        foreach (self::HINTS as $provider => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($name, $needle)) {
                    return SubscriptionProvider::from($provider);
                }
            }
        }

        return null;
    }

    /** @return Collection<int, ResaleProviderSupplier> */
    public function all(Organization $organization): Collection {
        return ResaleProviderSupplier::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->with('supplier:id,name')
            ->get()
            ->sortBy(static fn(ResaleProviderSupplier $m): string => mb_strtolower((string) $m->supplier->name))
            ->values();
    }

    public function forget(): void {
        $this->cache = [];
    }
}
