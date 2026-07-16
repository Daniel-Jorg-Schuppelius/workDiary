<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainLifecycleService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Domain;

use App\Enums\Domain\{DomainCapabilityArea, DomainProviderCommandStatus, DomainRenewalMode};
use App\Models\Domain\{DomainProjection, DomainProviderCommand};
use App\Models\User;

/**
 * Renewal, Transfer und Transferlock (Feature 083, MVP-390) — getrennte,
 * berechtigte Aktionen. Transfer-In wird mit Status/Ereignissen begleitet;
 * unklare Ausgänge werden nicht blind wiederholt. Hochrisikoaktionen liegen
 * bewusst in {@see DomainDangerousActionService}.
 */
class DomainLifecycleService {
    public function __construct(private readonly DomainCommandService $commands) {}

    /** Renewal-Modus ändern (getrennt vom manuellen Renewal). */
    public function setRenewalMode(DomainProjection $domain, DomainRenewalMode $mode, User $actor): DomainProviderCommand {
        $command = $this->commands->createAndDispatch(
            $domain->providerConnection(),
            DomainCapabilityArea::Renewal,
            'ModifyDomain',
            $domain->external_domain,
            ['domain' => $domain->external_domain, 'renewalmode' => $mode->value],
            $domain,
            $domain->customer_id,
            null,
            $actor,
        );

        if ($command->status === DomainProviderCommandStatus::Confirmed) {
            $domain->forceFill(['renewal_mode' => $mode->value])->save();
        }

        return $command;
    }

    /** Manueller Renewal um `period` Jahre. */
    public function renewNow(DomainProjection $domain, int $period, User $actor): DomainProviderCommand {
        $period = max(1, $period);

        return $this->commands->createAndDispatch(
            $domain->providerConnection(),
            DomainCapabilityArea::Renewal,
            'RenewDomain',
            $domain->external_domain,
            ['domain' => $domain->external_domain, 'period' => $period],
            $domain,
            $domain->customer_id,
            null,
            $actor,
        );
    }

    /** Transferlock setzen/aufheben (getrennte Aktion). */
    public function setTransferLock(DomainProjection $domain, bool $locked, User $actor): DomainProviderCommand {
        $command = $this->commands->createAndDispatch(
            $domain->providerConnection(),
            DomainCapabilityArea::Transfer,
            'ModifyDomain',
            $domain->external_domain,
            ['domain' => $domain->external_domain, 'transferlock' => $locked ? '1' : '0'],
            $domain,
            $domain->customer_id,
            null,
            $actor,
        );

        if ($command->status === DomainProviderCommandStatus::Confirmed) {
            $domain->forceFill(['transferlock' => $locked])->save();
        }

        return $command;
    }

    /**
     * Transfer-In anstoßen. Der Auth-Code wird nur transient übergeben und in
     * der gespeicherten Payload redigiert (nie geloggt/persistiert).
     */
    public function startTransferIn(DomainProjection $domain, string $authCode, User $actor): DomainProviderCommand {
        return $this->commands->createAndDispatch(
            $domain->providerConnection(),
            DomainCapabilityArea::Transfer,
            'TransferDomain',
            $domain->external_domain,
            ['domain' => $domain->external_domain, 'action' => 'REQUEST', 'auth' => $authCode],
            $domain,
            $domain->customer_id,
            null,
            $actor,
        );
    }
}
