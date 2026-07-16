<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainRegistrationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Domain;

use App\Enums\Domain\{DomainCapabilityArea, DomainProviderCommandStatus, DomainRenewalMode, DomainSyncStatus};
use App\Models\Domain\{DomainProjection, DomainProviderCommand, DomainProviderConnection};
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Kontrollierte Domainregistrierung (Feature 083, MVP-388). Preflight erzwingt
 * Kunde, Contact-Handles, Nameserver, Laufzeit/Renewal und eine ausdrückliche
 * Preisbestätigung. Der Befehl läuft über die idempotente Command-Outbox mit
 * anschließender Reconciliation; bei Erfolg entsteht die Projektion samt
 * Kundenzuordnung.
 */
class DomainRegistrationService {
    public function __construct(
        private readonly DomainCommandService $commands,
        private readonly DomainCustomerMappingService $mapping,
    ) {}

    /**
     * @param  array{
     *     customer_id?: int|null,
     *     provider_user?: string|null,
     *     period?: int,
     *     renewal_mode?: string|null,
     *     owner_contact?: string|null,
     *     admin_contact?: string|null,
     *     tech_contact?: string|null,
     *     billing_contact?: string|null,
     *     nameservers?: list<string>,
     *     price_confirmed?: bool,
     *     cost_center?: string|null,
     * }  $options
     */
    public function register(DomainProviderConnection $connection, string $domain, array $options, User $actor): DomainProviderCommand {
        $customerId = $options['customer_id'] ?? null;
        $owner = $options['owner_contact'] ?? null;
        $nameservers = array_values(array_filter($options['nameservers'] ?? []));
        $period = max(1, (int) ($options['period'] ?? 1));

        if ($customerId === null) {
            throw new DomainActionException(__('domain.errors.customer_required'));
        }
        if ($owner === null || $owner === '') {
            throw new DomainActionException(__('domain.errors.owner_contact_required'));
        }
        if (count($nameservers) < 2) {
            throw new DomainActionException(__('domain.errors.nameservers_required'));
        }
        if (($options['price_confirmed'] ?? false) !== true) {
            throw new DomainActionException(__('domain.errors.price_not_confirmed'));
        }

        $params = [
            'domain' => $domain,
            'period' => $period,
            'ownercontact0' => $owner,
            'admincontact0' => $options['admin_contact'] ?? $owner,
            'techcontact0' => $options['tech_contact'] ?? $owner,
            'billingcontact0' => $options['billing_contact'] ?? $owner,
            'renewalmode' => $this->renewalModeValue($options['renewal_mode'] ?? null),
        ];
        foreach ($nameservers as $i => $ns) {
            $params['nameserver' . $i] = $ns;
        }

        $command = $this->commands->createAndDispatch(
            $connection,
            DomainCapabilityArea::Domains,
            'AddDomain',
            $domain,
            $params,
            null,
            $customerId,
            ['cost_center' => $options['cost_center'] ?? null, 'provider_user' => $options['provider_user'] ?? null],
            $actor,
        );

        if ($command->status === DomainProviderCommandStatus::Confirmed) {
            $this->materializeProjection($connection, $domain, $options, $customerId, $actor);
        }

        return $command;
    }

    /** Provider-Renewalmodus als Wert; unbekannt → AUTORENEW. */
    private function renewalModeValue(?string $raw): string {
        $mode = DomainRenewalMode::fromProvider($raw);

        return $mode instanceof DomainRenewalMode ? $mode->value : DomainRenewalMode::Autorenew->value;
    }

    /** @param  array<string, mixed>  $options */
    private function materializeProjection(DomainProviderConnection $connection, string $domain, array $options, int $customerId, User $actor): void {
        $projection = DomainProjection::query()->updateOrCreate(
            [
                'organization_id' => $connection->organization_id,
                'domain_hash' => DomainProjection::hashFor($domain),
            ],
            [
                'connection_id' => $connection->id,
                'external_domain' => $domain,
                'external_user' => $options['provider_user'] ?? '',
                'customer_id' => $customerId,
                'sync_status' => DomainSyncStatus::Current->value,
                'renewal_mode' => $this->renewalModeValue($options['renewal_mode'] ?? null),
                'registration_at' => Carbon::now(),
                'synced_at' => Carbon::now(),
            ],
        );

        if ($projection->customer_id !== null) {
            $customer = $projection->customer;
            if ($customer !== null) {
                $this->mapping->assign($projection, $customer, $actor);
            }
        }
    }
}
