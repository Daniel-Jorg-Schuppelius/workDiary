<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket\Demo;

use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use Illuminate\Support\Collection;

/** Helpdesk-Vorführung: Anfrage → Incident → Problem → Change. Aus dem Demo-Showcase gelöst (Welle 4.1); Aufräumen übernimmt der generische Demo-Reset. */
final class HelpdeskDemoBlock implements DemoBlock {
    private DemoSeedContext $context;

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $this->context = $context;
        $actor = $context->users->first();

        return [
            'helpdesk_tickets' => $context->mainCustomer === null ? 0 : $this->seedHelpdesk($context->organization, $context->mainCustomer, $context->users),
        ];
    }

    public function purge(Organization $organization): void {}

    private function moduleActive(string $code): bool {
        return $this->context->moduleActive($code);
    }

    /**
     * IT-Demoszenario Helpdesk (Feature 065, P10): Portal-Queue + Incident
     * mit Konversation und Wartezustand, gelöstes Ticket mit Bewertung,
     * Problem aus Incidents, freigegebene Standard-Change-Vorlage + Change.
     *
     * @param Collection<int, User> $users
     */
    private function seedHelpdesk(Organization $organization, Customer $customer, Collection $users): int {
        if (! $this->moduleActive('module.helpdesk')) {
            return 0;
        }
        if (\App\Models\ServiceTicket\ServiceQueue::query()->where('organization_id', $organization->id)->exists()) {
            return 0;
        }
        /** @var User $agent */
        $agent = $users->first();

        $queue = \App\Models\ServiceTicket\ServiceQueue::query()->create([
            'organization_id' => $organization->id,
            'name' => 'IT-Support',
            'purpose' => 'Zentrale Anlaufstelle für Störungen und Anfragen.',
            'is_default' => true,
            'visibility' => 'portal',
        ]);

        $tickets = app(\App\Services\ServiceTicket\ServiceTicketService::class);
        $conversation = app(\App\Services\ServiceTicket\TicketConversationService::class);

        // Incident mit Konversation + Wartezustand.
        $incident = $tickets->create($organization, $agent, [
            'title' => 'VPN bricht mehrmals täglich ab',
            'description' => 'Mehrere Nutzer melden Abbrüche seit dem letzten Update.',
            'kind' => 'incident',
            'queue_id' => $queue->id,
            'customer_id' => $customer->id,
        ]);
        $tickets->assign($incident, $agent, $agent->id);
        $conversation->reply($incident->fresh() ?? $incident, $agent, 'Wir haben das Problem reproduziert und analysieren die Ursache.');
        $conversation->note($incident->fresh() ?? $incident, $agent, 'Verdacht: MTU-Problem nach Firmware 2.4.1.');

        // Gelöstes zweites Ticket.
        $solved = $tickets->create($organization, $agent, [
            'title' => 'Neuer Arbeitsplatz für Auszubildende',
            'kind' => 'service_request',
            'queue_id' => $queue->id,
            'customer_id' => $customer->id,
        ]);
        $tickets->assign($solved, $agent, $agent->id);
        $solved = $tickets->transition($solved->fresh() ?? $solved, $agent, \App\Enums\ServiceTicket\ServiceTicketStatus::InProgress);
        $solved = $tickets->transition($solved, $agent, \App\Enums\ServiceTicket\ServiceTicketStatus::Done);

        // Problem aus dem Incident + freigegebene Standard-Change-Vorlage + Change.
        $problem = app(\App\Services\ServiceTicket\ProblemService::class)
            ->openFromIncidents([$incident->fresh() ?? $incident], 'Wiederkehrende VPN-Abbrüche nach Firmware-Update', $agent);
        app(\App\Services\ServiceTicket\ProblemService::class)->transition($problem, 'analyzing', $agent);

        $template = \App\Models\ServiceTicket\ChangeTemplate::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Firmware-Rollout Netzwerkgeräte',
            'implementation_plan' => 'Staging → Pilotgruppe → Flächenrollout.',
            'test_plan' => 'VPN-Dauerlast über 24h.',
            'rollback_plan' => 'Firmware-Downgrade auf 2.3.9.',
            'approved' => true,
        ]);
        app(\App\Services\ServiceTicket\ChangeService::class)->submit([
            'title' => 'Firmware-Downgrade VPN-Gateways',
            'change_type' => 'standard',
            'reason' => 'Behebt die VPN-Abbrüche (Problem-Analyse).',
            'problem_id' => $problem->id,
        ], $agent, [], $template);

        return \App\Models\ServiceTicket\ServiceTicket::query()->where('organization_id', $organization->id)->count();
    }
}
