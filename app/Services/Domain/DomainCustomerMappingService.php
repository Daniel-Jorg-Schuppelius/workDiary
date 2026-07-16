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

use App\Models\{Customer, ExternalReference, User};
use App\Models\Domain\{DomainProjection, DomainResellerAccount};
use App\Plugins\DomainReselling\DomainResellingPlugin;
use Illuminate\Support\Carbon;

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

        // 1) Bereits bestätigte ExternalReference auf genau diese Domain.
        $ref = ExternalReference::query()
            ->where('organization_id', $orgId)
            ->where('plugin_id', DomainResellingPlugin::ID)
            ->where('external_type', 'domain')
            ->where('external_id', $domain)
            ->where('referenceable_type', Customer::class)
            ->first();
        if ($ref !== null) {
            $customer = Customer::query()->whereKey($ref->referenceable_id)->first();
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
     * die ExternalReference als bestätigte Zuordnung.
     */
    public function assign(DomainProjection $projection, Customer $customer, ?User $actor = null): void {
        $projection->forceFill(['customer_id' => $customer->id])->save();

        ExternalReference::query()->updateOrCreate(
            [
                'organization_id' => $projection->organization_id,
                'plugin_id' => DomainResellingPlugin::ID,
                'external_type' => 'domain',
                'external_id' => mb_strtolower($projection->external_domain),
            ],
            [
                'referenceable_type' => Customer::class,
                'referenceable_id' => $customer->id,
                'payload' => ['assigned_by' => $actor?->id],
                'synced_at' => Carbon::now(),
            ],
        );
    }

    /** Hebt die Kundenzuordnung wieder auf. */
    public function clearAssignment(DomainProjection $projection): void {
        $projection->forceFill(['customer_id' => null])->save();

        ExternalReference::query()
            ->where('organization_id', $projection->organization_id)
            ->where('plugin_id', DomainResellingPlugin::ID)
            ->where('external_type', 'domain')
            ->where('external_id', mb_strtolower($projection->external_domain))
            ->delete();
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
