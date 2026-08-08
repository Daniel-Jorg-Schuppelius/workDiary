<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainCustomerMappingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Domain;

use App\Models\{Customer, ExternalReference, ExternalReferenceAlias, ForeignCustomer, User};
use App\Models\Domain\{DomainProjection, DomainResellerAccount};
use App\Plugins\DomainReselling\DomainResellingPlugin;

/**
 * Kundenzuordnung von Domains und Subusern (Feature 083, MVP-386). Vorschläge
 * nutzen nur nachvollziehbare Merkmale und werden NIE automatisch bestätigt.
 * Eine Zuordnung erzeugt keine Domainkopie und verschiebt beim Provider
 * nichts; sie hält nur die WorkDiary-seitige Verknüpfung + ExternalReference.
 * Eine Domain darf innerhalb einer Organisation nur einem Kunden gehören.
 */
class DomainCustomerMappingService {
    /**
     * Nachvollziehbare Match-Vorschläge (Homepage/E-Mail-Domain/bestätigte
     * ExternalReference). Nur Vorschläge — nie automatisch bestätigt.
     *
     * @return list<array{customer: Customer, reason: string}>
     */
    public function suggestFor(DomainProjection $projection): array {
        $orgId = $projection->organization_id;
        $domain = mb_strtolower($projection->external_domain);
        $suggestions = [];
        $seen = [];

        // 1) Bereits bestätigte Zuordnung dieser Domain. Ein Kunde darf mehrere
        //    Domains halten: die erste liegt als Primär-Referenz, jede weitere
        //    als Alias (extref_unique lässt je Kunde nur EINE Primär-Referenz).
        $confirmedCustomerId = ExternalReference::query()
            ->forPlugin($orgId, DomainResellingPlugin::ID, 'domain')
            ->forExternalId($domain)
            ->where('referenceable_type', Customer::class)
            ->value('referenceable_id')
            ?? ExternalReferenceAlias::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('plugin_id', DomainResellingPlugin::ID)
            ->where('external_type', 'domain')
            ->where('external_id', $domain)
            ->where('referenceable_type', Customer::class)
            ->value('referenceable_id');
        if ($confirmedCustomerId !== null) {
            $customer = Customer::query()->whereKey($confirmedCustomerId)->first();
            if ($customer !== null) {
                $suggestions[] = ['customer' => $customer, 'reason' => 'external_reference'];
                $seen[$customer->id] = true;
            }
        }

        // 2) Kunden, deren Homepage die Domain enthält oder deren E-Mail-Domain passt.
        $matches = Customer::query()
            ->where('organization_id', $orgId)
            ->where(function ($q) use ($domain): void {
                $q->whereLikeEscaped('homepage', $domain)
                    ->orWhereLikeEscaped('email', '@' . $domain);
            })
            ->limit(10)
            ->get();
        foreach ($matches as $customer) {
            if (isset($seen[$customer->id])) {
                continue;
            }
            $reason = str_contains(mb_strtolower((string) $customer->email), '@' . $domain) ? 'email_domain' : 'homepage';
            $suggestions[] = ['customer' => $customer, 'reason' => $reason];
            $seen[$customer->id] = true;
        }

        return $suggestions;
    }

    /**
     * Ordnet die Domain einem Kunden zu (kein Provider-Move). Legt/aktualisiert
     * die ExternalReference als bestätigte Zuordnung. Optional mit Endkunde
     * (Fremdkunde DES Kunden, Reseller-Fall) — ein fremder Endkunde wird
     * verworfen. Eine Kundenzuordnung hebt das Eigenbestand-Flag auf.
     */
    public function assign(DomainProjection $projection, Customer $customer, ?User $actor = null, ?ForeignCustomer $foreignCustomer = null): void {
        if ($foreignCustomer !== null && $foreignCustomer->customer_id !== $customer->id) {
            $foreignCustomer = null;
        }

        $projection->forceFill([
            'customer_id' => $customer->id,
            'foreign_customer_id' => $foreignCustomer?->id,
            'is_own_holding' => false,
        ])->save();

        $this->rememberCustomerReference($projection, $customer, $actor);
    }

    /**
     * Verankert die bestätigte Domain↔Kunde-Zuordnung idempotent. Da ein Kunde
     * mehrere Domains hält, `extref_unique` (plugin, typ, ziel) aber nur EINE
     * Primär-Referenz je Kunde zulässt, wird die erste Domain als Primär-
     * Referenz gespeichert und jede weitere als {@see ExternalReferenceAlias}
     * auf denselben Kunden (Muster wie Toggl/OpenProject/RemoteSupport).
     */
    private function rememberCustomerReference(DomainProjection $projection, Customer $customer, ?User $actor): void {
        $key = [
            'organization_id' => $projection->organization_id,
            'plugin_id' => DomainResellingPlugin::ID,
            'external_type' => 'domain',
            'external_id' => mb_strtolower($projection->external_domain),
        ];
        $target = [
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->getKey(),
        ];

        // Diese Domain gehört jetzt (neu) zu $customer — alte Verweise auf die
        // Domain in beiden Tabellen entfernen, danach frisch verankern.
        ExternalReference::query()->withoutGlobalScopes()->where($key)->delete();
        ExternalReferenceAlias::query()->withoutGlobalScopes()->where($key)->delete();

        $customerHasPrimary = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('plugin_id', DomainResellingPlugin::ID)
            ->where('external_type', 'domain')
            ->where($target)
            ->exists();

        if ($customerHasPrimary) {
            ExternalReferenceAlias::query()->withoutGlobalScopes()->create($key + $target);

            return;
        }

        ExternalReference::query()->withoutGlobalScopes()->create($key + $target + [
            'payload' => ['assigned_by' => $actor?->id],
            'synced_at' => now(),
        ]);
    }

    /** Hebt Kunden- und Endkunden-Zuordnung wieder auf (auch Eigenbestand). */
    public function clearAssignment(DomainProjection $projection): void {
        $projection->forceFill([
            'customer_id' => null,
            'foreign_customer_id' => null,
            'is_own_holding' => false,
        ])->save();

        $domain = mb_strtolower($projection->external_domain);
        ExternalReference::query()
            ->forPlugin($projection->organization_id, DomainResellingPlugin::ID, 'domain')
            ->forExternalId($domain)
            ->delete();
        ExternalReferenceAlias::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $projection->organization_id)
            ->where('plugin_id', DomainResellingPlugin::ID)
            ->where('external_type', 'domain')
            ->where('external_id', $domain)
            ->delete();
    }

    /**
     * Kennzeichnet die Domain als Eigenbestand (gehört der eigenen Firma).
     * Eigenbestand schließt eine Kundenzuordnung aus — sie wird aufgehoben;
     * Berichte zählen die Domain nicht mehr als „ohne Kundenzuordnung".
     */
    public function markOwnHolding(DomainProjection $projection): void {
        $this->clearAssignment($projection);
        $projection->forceFill(['is_own_holding' => true])->save();
    }

    /** Hebt die Eigenbestand-Kennzeichnung wieder auf. */
    public function clearOwnHolding(DomainProjection $projection): void {
        $projection->forceFill(['is_own_holding' => false])->save();
    }

    /**
     * Ordnet einen Subuser/Subreseller einem Kunden zu. Dessen Domains werden
     * dadurch in der Kundenakte gruppiert (bleiben „geführt unter Subuser …").
     * Direkte Domain-Zuordnungen werden NICHT überschrieben.
     */
    public function assignReseller(DomainResellerAccount $account, Customer $customer): void {
        $account->forceFill(['customer_id' => $customer->id])->save();
    }
}
