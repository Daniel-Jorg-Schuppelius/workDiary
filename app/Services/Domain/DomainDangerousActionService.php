<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainDangerousActionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Domain;

use App\Enums\Domain\DomainCapabilityArea;
use App\Models\Domain\{DomainProjection, DomainProviderCommand};
use App\Models\User;

/**
 * Hochrisikoaktionen (Feature 083, MVP-390): DeleteDomain, PushDomain,
 * TradeDomain, AssignObject und Transfer-Out. JEDE verlangt die erneute
 * Eingabe des Domainnamens und legt einen Command als ENTWURF an, der eine
 * Vier-Augen-Freigabe ({@see DomainCommandService::approve()}) und einen
 * anschließenden Statusabgleich benötigt. Es gibt KEINE Einzelklick- oder
 * Scheduler-Ausführung.
 */
class DomainDangerousActionService {
    public function __construct(private readonly DomainCommandService $commands) {}

    public function requestDelete(DomainProjection $domain, string $confirmationName, User $requestedBy): DomainProviderCommand {
        return $this->request($domain, 'DeleteDomain', DomainCapabilityArea::Domains, ['domain' => $domain->external_domain], $confirmationName, $requestedBy);
    }

    public function requestPush(DomainProjection $domain, string $targetUser, string $confirmationName, User $requestedBy): DomainProviderCommand {
        return $this->request($domain, 'PushDomain', DomainCapabilityArea::Domains, ['domain' => $domain->external_domain, 'target' => $targetUser], $confirmationName, $requestedBy);
    }

    public function requestTrade(DomainProjection $domain, string $confirmationName, User $requestedBy): DomainProviderCommand {
        return $this->request($domain, 'TradeDomain', DomainCapabilityArea::Domains, ['domain' => $domain->external_domain], $confirmationName, $requestedBy);
    }

    public function requestTransferOut(DomainProjection $domain, string $confirmationName, User $requestedBy): DomainProviderCommand {
        return $this->request($domain, 'TransferDomain', DomainCapabilityArea::Transfer, ['domain' => $domain->external_domain, 'action' => 'APPROVE'], $confirmationName, $requestedBy);
    }

    public function requestAssign(DomainProjection $domain, string $targetUser, string $confirmationName, User $requestedBy): DomainProviderCommand {
        return $this->request($domain, 'AssignObject', DomainCapabilityArea::Domains, ['object' => $domain->external_domain, 'target' => $targetUser], $confirmationName, $requestedBy);
    }

    /**
     * @param  array<string, scalar|null>  $params
     */
    private function request(DomainProjection $domain, string $command, DomainCapabilityArea $area, array $params, string $confirmationName, User $requestedBy): DomainProviderCommand {
        if (mb_strtolower(trim($confirmationName)) !== mb_strtolower($domain->external_domain)) {
            throw new DomainActionException(__('domain.errors.name_confirmation_mismatch'));
        }

        // Entwurf: wartet auf Vier-Augen-Freigabe, wird NICHT sofort gesendet.
        return $this->commands->create(
            $domain->providerConnection(),
            $area,
            $command,
            $domain->external_domain,
            $params,
            true, // requiresSecondApproval
            $domain,
            $domain->customer_id,
            ['domain_status' => $domain->status, 'revision' => $domain->revision],
            $requestedBy,
        );
    }
}
