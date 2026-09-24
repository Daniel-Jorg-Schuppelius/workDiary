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
use App\Events\Asset\MaintenanceDue;
use App\Models\Asset\MaintenancePlan;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;

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
/**
 * Fällige Wartungspläne mit Aktion „Ticket": prüft die Fälligkeit und meldet
 * sie als {@see MaintenanceDue}; das Ticket legt der Helpdesk an (MVP-863).
 * Idempotenz je Fälligkeit über eine ExternalReference am Ticket.
 */
class MaintenanceDueService {
    public const PLUGIN_ID = 'maintenance';

    public const EXTERNAL_TYPE = 'due';

    /** @return bool Fälligkeit gemeldet (noch nicht behandelt) */
    public function handleDue(MaintenancePlan $plan): bool {
        if ($plan->due_action !== MaintenanceDueAction::Ticket || $plan->next_due_on === null) {
            return false;
        }

        $externalId = $plan->id . ':' . $plan->next_due_on->toDateString();

        $alreadyHandled = ExternalReference::query()
            ->forPlugin($plan->organization_id, self::PLUGIN_ID, self::EXTERNAL_TYPE)
            ->forExternalId($externalId)
            ->exists();
        if ($alreadyHandled) {
            return false; // diese Fälligkeit wurde bereits erzeugt
        }

        $organization = Organization::query()->find($plan->organization_id);
        if (! $organization instanceof Organization) {
            return false;
        }

        MaintenanceDue::dispatch($plan, $organization, $externalId);

        return true;
    }
}
