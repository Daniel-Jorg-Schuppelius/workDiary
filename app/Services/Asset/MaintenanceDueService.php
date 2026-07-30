<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenanceDueService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Asset;

use App\Enums\Asset\MaintenanceDueAction;
use App\Enums\ServiceTicket\{ServiceTicketPriority, ServiceTicketSource};
use App\Models\{ExternalReference, MaintenancePlan, Organization, ServiceTicket};
use App\Services\ServiceTicket\ServiceTicketService;

/**
 * Erzeugt bei Fälligkeit eines Wartungsplans den konfigurierten Vorgang
 * (Feature 010 → Rang 43) — heute ein Service-Ticket, gebunden an den
 * SLA-Vertrag des Plans.
 *
 * **Idempotent je (Plan, Fälligkeitsdatum):** eine {@see ExternalReference}
 * (Plugin `maintenance`, Typ `due`, `external_id = <planId>:<Y-m-d>`) verhindert,
 * dass ein zweiter Scan-Lauf für dieselbe Fälligkeit ein weiteres Ticket anlegt.
 * Das Vorrücken von `next_due_on` bleibt bewusst der tatsächlichen Durchführung
 * überlassen ({@see MaintenancePlanService::markCompleted}).
 */
class MaintenanceDueService {
    public const PLUGIN_ID = 'maintenance';

    public const EXTERNAL_TYPE = 'due';

    public function __construct(private readonly ServiceTicketService $tickets) {}

    /** Verarbeitet einen fälligen Plan; gibt das erzeugte Ticket zurück (null = nichts erzeugt). */
    public function handleDue(MaintenancePlan $plan): ?ServiceTicket {
        if ($plan->due_action !== MaintenanceDueAction::Ticket || $plan->next_due_on === null) {
            return null;
        }

        $externalId = $plan->id . ':' . $plan->next_due_on->toDateString();

        $alreadyHandled = ExternalReference::query()
            ->forPlugin($plan->organization_id, self::PLUGIN_ID, self::EXTERNAL_TYPE)
            ->forExternalId($externalId)
            ->exists();
        if ($alreadyHandled) {
            return null; // diese Fälligkeit wurde bereits erzeugt
        }

        $organization = Organization::query()->find($plan->organization_id);
        if (! $organization instanceof Organization) {
            return null;
        }

        $ticket = $this->tickets->create($organization, null, [
            'title' => $plan->label,
            'priority' => ServiceTicketPriority::Normal->value,
            'source' => ServiceTicketSource::MaintenancePlan->value,
            'source_reference' => $plan->code,
            'asset_id' => $plan->asset_id,
            'sla_contract_id' => $plan->sla_contract_id,
            'reported_at' => $plan->next_due_on->toDateString(),
        ]);

        ExternalReference::query()->create([
            'organization_id' => $plan->organization_id,
            'plugin_id' => self::PLUGIN_ID,
            'external_type' => self::EXTERNAL_TYPE,
            'external_id' => $externalId,
            'referenceable_type' => $ticket->getMorphClass(),
            'referenceable_id' => $ticket->getKey(),
            'payload' => ['maintenance_plan_id' => $plan->id, 'due_on' => $plan->next_due_on->toDateString()],
            'synced_at' => now(),
        ]);

        return $ticket;
    }
}
