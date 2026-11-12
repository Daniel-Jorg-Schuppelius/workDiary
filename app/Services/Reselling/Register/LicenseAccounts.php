<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseAccounts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Models\Platform\Organization;
use App\Models\Reselling\ResaleSubscription;
use Illuminate\Support\Collection;

/**
 * Bildet die Lizenzkonten einer Organisation (Feature 185, MVP-1399): jedes
 * Abo gehört zum Konto seines Kopfes — dem letzten Nachfolger in der
 * Ablösungskette —, Abtretungen zum Konto ihres Vertrags. Abos ohne
 * Kundennummer und Firma (von Hand angelegt) bilden je Anbieter und Halter
 * ein eigenes Konto.
 *
 * Abos und Konten liegen je Request im {@see ResaleRequestMemo} hinter einer
 * Bestandssignatur (Anzahl, jüngste Änderung, höchste ID — eine Aggregatabfrage):
 * ein Lizenzkonto lud die Abos dreimal (Leistungsanalyse 2026-11-02, perfc-04);
 * Import und Halterwechsel im selben Prozess ändern die Signatur und laden neu.
 */
final class LicenseAccounts {
    private readonly ResaleRequestMemo $memo;

    public function __construct(?ResaleRequestMemo $memo = null) {
        $this->memo = $memo ?? app(ResaleRequestMemo::class);
    }

    /** @return Collection<int, ResaleSubscription> */
    public function subscriptions(Organization $organization): Collection {
        return $this->memo->remember($this->memoPrefix($organization) . ':subscriptions', static fn(): Collection => ResaleSubscription::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->with(['customer:id,name', 'foreignCustomer:id,name,customer_id', 'foreignCustomer.customer:id,name'])
            ->with(ResaleSubscription::providerBillingRelations())
            ->orderBy('starts_on')
            ->get());
    }

    /**
     * @param  Collection<int, ResaleSubscription>|null  $subscriptions  vorgeladen (Tests, Import)
     * @return array<string, LicenseAccountGroup> Schlüssel → Konto
     */
    public function groups(Organization $organization, ?Collection $subscriptions = null): array {
        if ($subscriptions !== null) {
            return $this->build($subscriptions);
        }

        return $this->memo->remember($this->memoPrefix($organization) . ':groups', fn(): array => $this->build($this->subscriptions($organization)));
    }

    public function group(Organization $organization, string $key): ?LicenseAccountGroup {
        return $this->groups($organization)[$key] ?? null;
    }

    /** Konto eines Abos (Schlüssel des Kopfes seiner Kette). */
    public function keyFor(Organization $organization, ResaleSubscription $subscription): string {
        $byId = $this->subscriptions($organization)->keyBy('id');

        return self::keyOf($this->headOf($byId->get($subscription->id) ?? $subscription, $byId));
    }

    /**
     * @param  Collection<int, ResaleSubscription>  $subscriptions
     * @return array<string, LicenseAccountGroup>
     */
    private function build(Collection $subscriptions): array {
        ResaleSubscription::withCatalogArticles($subscriptions);
        $byId = $subscriptions->keyBy('id');
        /** @var array<string, list<ResaleSubscription>> $members */
        $members = [];
        /** @var array<string, ResaleSubscription> $heads */
        $heads = [];
        foreach ($subscriptions as $subscription) {
            $head = $this->headOf($subscription, $byId);
            $key = self::keyOf($head);
            $members[$key][] = $subscription;
            $heads[$key] ??= $head;
        }
        $groups = [];
        foreach ($members as $key => $list) {
            $head = $heads[$key];
            $groups[$key] = new LicenseAccountGroup($key, $head->provider, self::accountOf($head), collect($list));
        }
        uasort($groups, static fn(LicenseAccountGroup $a, LicenseAccountGroup $b): int => strcmp($a->name(), $b->name()));

        return $groups;
    }

    /**
     * Memo-Präfix der Organisation; eine geänderte Bestandssignatur verwirft Abos und Konten.
     */
    private function memoPrefix(Organization $organization): string {
        $prefix = 'license-accounts:' . $organization->id;
        $signature = $this->signature($organization);
        if ($this->memo->remember($prefix . ':signature', static fn(): string => $signature) !== $signature) {
            $this->memo->forget($prefix);
            $this->memo->remember($prefix . ':signature', static fn(): string => $signature);
        }

        return $prefix;
    }

    private function signature(Organization $organization): string {
        $row = ResaleSubscription::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->selectRaw('COUNT(*) AS n, MAX(updated_at) AS u, MAX(id) AS m')
            ->first();

        return $row === null ? '0' : implode('|', [(string) $row->getAttribute('n'), (string) $row->getAttribute('u'), (string) $row->getAttribute('m')]);
    }

    /**
     * Kopf der Kette: bei Abtretungen der Vertrag, dann der letzte Nachfolger.
     *
     * @param  Collection<int, ResaleSubscription>  $byId
     */
    public function headOf(ResaleSubscription $subscription, Collection $byId): ResaleSubscription {
        $current = $subscription;
        $seen = [$current->id => true];
        $parent = $current->parent_id !== null ? $byId->get($current->parent_id) : null;
        if ($parent !== null) {
            $current = $parent;
            $seen[$current->id] = true;
        }
        while ($current->successor_id !== null && ! isset($seen[$current->successor_id])) {
            $successor = $byId->get($current->successor_id);
            if ($successor === null) {
                break;
            }
            $current = $successor;
            $seen[$current->id] = true;
        }

        return $current;
    }

    /** Schlüssel eines Kopfes: Kundennummer beim Anbieter, sonst Anbieter + Halter. */
    public static function keyOf(ResaleSubscription $head): string {
        return $head->accountKey() ?? ResaleSubscription::accountKeyOf($head->provider, self::accountOf($head));
    }

    /** Kontobezeichner ohne Anbieter (Kundennummer bzw. Ersatzschlüssel). */
    public static function accountOf(ResaleSubscription $head): string {
        $key = $head->accountKey();
        if ($key !== null) {
            return substr($key, strlen($head->provider->value) + 1);
        }

        return 'holder:' . $head->holderKey();
    }
}
