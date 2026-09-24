<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenMaintenanceTicket.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Helpdesk;

use App\Enums\ServiceTicket\{ServiceTicketPriority, ServiceTicketSource};
use App\Events\Asset\MaintenanceDue;
use App\Listeners\ModuleListener;
use App\Models\Integration\ExternalReference;
use App\Services\Asset\MaintenanceDueService;
use App\Services\ServiceTicket\ServiceTicketService;

/** Fällige Wartung → Ticket, genau einmal je Fälligkeit (ExternalReference als Nachweis). */
final class OpenMaintenanceTicket extends ModuleListener {
    public function __construct(private readonly ServiceTicketService $tickets) {}

    protected function module(): string {
        return 'helpdesk';
    }

    public function handle(MaintenanceDue $event): void {
        if (! $this->shouldHandle($event->organization)) {
            return;
        }
        $plan = $event->plan;
        $alreadyHandled = ExternalReference::query()
            ->forPlugin($plan->organization_id, MaintenanceDueService::PLUGIN_ID, MaintenanceDueService::EXTERNAL_TYPE)
            ->forExternalId($event->externalId)
            ->exists();
        if ($alreadyHandled || $plan->next_due_on === null) {
            return;
        }

        $ticket = $this->tickets->create($event->organization, null, [
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
            'plugin_id' => MaintenanceDueService::PLUGIN_ID,
            'external_type' => MaintenanceDueService::EXTERNAL_TYPE,
            'external_id' => $event->externalId,
            'referenceable_type' => $ticket->getMorphClass(),
            'referenceable_id' => $ticket->getKey(),
            'payload' => ['maintenance_plan_id' => $plan->id, 'due_on' => $plan->next_due_on->toDateString()],
            'synced_at' => now(),
        ]);
    }
}
