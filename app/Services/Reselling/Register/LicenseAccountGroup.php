<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseAccountGroup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\SubscriptionProvider;
use App\Models\Customer\Customer;
use App\Models\Reselling\ResaleSubscription;
use Illuminate\Support\Collection;

/**
 * Ein Lizenzkonto (Feature 185): alle Abos eines Anbieterkontos samt
 * Vorgängern (Ablösung) und Abtretungen. Schlüssel ist (Anbieter,
 * Kundennummer) des Kopfes — des letzten Nachfolgers in der Kette.
 */
final class LicenseAccountGroup {
    /**
     * @param  Collection<int, ResaleSubscription>  $subscriptions  Verträge, Vorgänger und Abtretungen
     */
    public function __construct(
        public readonly string $key,
        public readonly SubscriptionProvider $provider,
        public readonly string $account,
        public readonly Collection $subscriptions,
    ) {}

    /**
     * Verträge (keine Abtretungen), neueste zuerst.
     *
     * @return Collection<int, ResaleSubscription>
     */
    public function contracts(): Collection {
        return $this->subscriptions->filter(static fn(ResaleSubscription $s): bool => $s->parent_id === null)
            ->sortBy([['starts_on', 'desc'], ['id', 'desc']])->values();
    }

    /**
     * Verträge des Kopf-Anbieters (ohne abgelöste Vorgänger anderer Anbieter).
     *
     * @return Collection<int, ResaleSubscription>
     */
    public function headContracts(): Collection {
        return $this->contracts()->filter(fn(ResaleSubscription $s): bool => $s->provider === $this->provider)->values();
    }

    /** @return Collection<int, ResaleSubscription> */
    public function assignments(): Collection {
        return $this->subscriptions->filter(static fn(ResaleSubscription $s): bool => $s->parent_id !== null)->values();
    }

    /** Kunde, an den das Konto hauptsächlich geht: häufigster Rechnungsempfänger der Verträge. */
    public function customer(): ?Customer {
        $counts = [];
        $customers = [];
        foreach ($this->contracts() as $contract) {
            $billedTo = $contract->billedTo();
            if ($billedTo !== null) {
                $counts[$billedTo->id] = ($counts[$billedTo->id] ?? 0) + 1;
                $customers[$billedTo->id] = $billedTo;
            }
        }
        if ($counts === []) {
            return null;
        }
        arsort($counts);

        return $customers[(int) array_key_first($counts)];
    }

    public function name(): string {
        $customer = $this->customer();
        if ($customer !== null) {
            return $customer->name;
        }
        $first = $this->contracts()->first();

        if ($first === null) {
            return $this->account;
        }

        return $first->company_name ?? $first->holderLabel();
    }

    /** Routenparameter — `route()` kodiert selbst, ein vorkodierter Schlüssel käme doppelt kodiert an. */
    public function routeKey(): string {
        return $this->key;
    }

    /**
     * Produkte des Kontos: Schlüssel → Bezeichnung (Katalogartikel, sonst Abo-Name).
     *
     * @return array<string, string>
     */
    public function products(): array {
        $products = [];
        foreach ($this->contracts() as $contract) {
            $products[$contract->productKey()] ??= $contract->productName();
        }
        asort($products);

        return $products;
    }
}
