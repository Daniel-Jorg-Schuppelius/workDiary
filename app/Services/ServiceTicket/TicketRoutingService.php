<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketRoutingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket;

use App\Models\ServiceTicket\{ServiceTicket, TicketRoutingRule, TicketRuleExecution};
use Illuminate\Support\Facades\DB;

/**
 * Regel-Engine (Feature 065, MVP-153) — deterministisch: Regeln in
 * Positions-Reihenfolge, je Aktionstyp gewinnt die ERSTE zutreffende
 * Regel. Jede Anwendung wird mit Regel-Version, erfüllten Bedingungen
 * und Aktionen protokolliert (Pflicht, Vorgabe 065). Dry-Run wertet
 * identisch aus, ändert aber nichts (Regel-Test-Modus).
 */
class TicketRoutingService {
    // Vertrag, Fachdienst, Angebot, Sprache und Geschäftszeit seit MVP-1258.
    private const CONDITION_KEYS = ['kind', 'priority', 'source', 'customer_id', 'queue_id', 'sla_contract_id', 'business_service_id', 'service_offering_id', 'locale', 'business_hours'];

    private const ACTION_KEYS = ['set_queue', 'set_priority', 'set_sla', 'set_team', 'assign'];

    /** Zuweisung innerhalb des Teams der Queue: reihum oder an die Person mit den wenigsten offenen Tickets. */
    public const ASSIGN_STRATEGIES = ['round_robin', 'least_loaded'];

    /**
     * @return array<int, array{rule: TicketRoutingRule, matched: array<string, mixed>, actions: array<string, mixed>}>
     */
    public function apply(ServiceTicket $ticket, bool $dryRun = false): array {
        $rules = TicketRoutingRule::query()
            ->where('organization_id', $ticket->organization_id)
            ->where('active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $appliedActionTypes = [];
        $log = [];

        foreach ($rules as $rule) {
            $matched = $this->matchedConditions($rule, $ticket);
            if ($matched === null) {
                continue;
            }

            // Erste zutreffende Regel je Aktionstyp gewinnt.
            $actions = array_filter(
                array_intersect_key((array) $rule->actions, array_flip(self::ACTION_KEYS)),
                fn($value, string $key): bool => ! in_array($key, $appliedActionTypes, true) && $value !== null && $value !== '',
                ARRAY_FILTER_USE_BOTH,
            );
            if ($actions === []) {
                continue;
            }

            $appliedActionTypes = [...$appliedActionTypes, ...array_keys($actions)];
            $log[] = ['rule' => $rule, 'matched' => $matched, 'actions' => $actions];
        }

        $assignee = null;
        DB::transaction(function () use ($ticket, $log, $dryRun, &$assignee): void {
            foreach ($log as $entry) {
                if (! $dryRun) {
                    $assignee = $this->applyActions($ticket, $entry['actions']) ?? $assignee;
                }
                TicketRuleExecution::query()->create([
                    'organization_id' => $ticket->organization_id,
                    'ticket_routing_rule_id' => $entry['rule']->id,
                    'service_ticket_id' => $ticket->id,
                    'rule_version' => $entry['rule']->version,
                    'matched_conditions' => $entry['matched'],
                    'applied_actions' => $entry['actions'],
                    'dry_run' => $dryRun,
                ]);
            }
            if (! $dryRun && $log !== []) {
                $ticket->save();
            }
        });

        // Über den Dienst, damit Audit und Benachrichtigung der zugewiesenen Person entstehen.
        if ($assignee !== null && $ticket->assigned_to_user_id === null) {
            app(ServiceTicketService::class)->assign($ticket, null, $assignee);
        }

        return $log;
    }

    /**
     * Alle DEFINIERTEN Bedingungen müssen zutreffen (UND); nicht gesetzte
     * Schlüssel sind Wildcards. Null = kein Treffer.
     *
     * @return array<string, mixed>|null
     */
    private function matchedConditions(TicketRoutingRule $rule, ServiceTicket $ticket): ?array {
        $conditions = array_intersect_key((array) $rule->conditions, array_flip(self::CONDITION_KEYS));
        if ($conditions === []) {
            return null; // Regel ohne Bedingungen trifft nie (Schutz vor Catch-All-Versehen)
        }

        $actual = [
            'kind' => $ticket->kind->value,
            'priority' => $ticket->priority->value,
            'source' => $ticket->source->value,
            'customer_id' => $ticket->customer_id !== null ? (int) $ticket->customer_id : null,
            'queue_id' => $ticket->queue_id !== null ? (int) $ticket->queue_id : null,
            'sla_contract_id' => $ticket->sla_contract_id !== null ? (int) $ticket->sla_contract_id : null,
            'business_service_id' => $ticket->business_service_id !== null ? (int) $ticket->business_service_id : null,
            'service_offering_id' => $ticket->service_offering_id !== null ? (int) $ticket->service_offering_id : null,
            'locale' => array_key_exists('locale', $conditions) ? \App\Support\DocumentLocale::for($ticket->customer, $ticket->organization) : null,
            'business_hours' => array_key_exists('business_hours', $conditions) ? ($this->withinBusinessHours($ticket) ? 'in' : 'out') : null,
        ];

        foreach ($conditions as $key => $expected) {
            $value = $actual[$key];
            $expectedList = is_array($expected) ? $expected : [$expected];
            $normalized = array_map(fn($item) => is_numeric($item) ? (int) $item : (string) $item, $expectedList);
            $needle = is_numeric($value) ? (int) $value : (string) $value;
            if ($value === null || ! in_array($needle, $normalized, true)) {
                return null;
            }
        }

        return $conditions;
    }

    /**
     * Gemeldet innerhalb der Geschäftszeit? Fenster der Queue vor denen des
     * SLA-Stands; ohne Fenster gilt immer Geschäftszeit.
     */
    private function withinBusinessHours(ServiceTicket $ticket): bool {
        $hours = $ticket->queue_id !== null
            ? \App\Models\ServiceTicket\ServiceQueue::query()->find($ticket->queue_id, ['id', 'business_hours'])?->business_hours
            : null;
        if (! is_array($hours) || $hours === []) {
            $hours = $ticket->sla_snapshot['business_hours'] ?? null;
        }
        $organization = $ticket->organization;
        $at = ($ticket->reported_at ?? \Illuminate\Support\Carbon::now())->copy()
            ->setTimezone($organization !== null ? \App\Support\Tz::ofOrganization($organization) : (string) config('app.timezone'));

        return app(SlaTimer::class)->isWithinBusinessHours(is_array($hours) ? $hours : null, $at);
    }

    /**
     * Person aus dem Team der Queue nach der Strategie der Regel.
     */
    private function pickAssignee(ServiceTicket $ticket, string $strategy): ?int {
        $teamId = $ticket->queue_id !== null
            ? \App\Models\ServiceTicket\ServiceQueue::query()->whereKey($ticket->queue_id)->value('team_id')
            : null;
        if ($teamId === null) {
            return null;
        }
        $candidates = \App\Models\Platform\User::query()
            ->whereNull('deactivated_at')
            ->whereIn('id', DB::table('team_user')->where('team_id', $teamId)->select('user_id'))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        if ($candidates === []) {
            return null;
        }

        if ($strategy === 'least_loaded') {
            $load = ServiceTicket::query()
                ->where('organization_id', $ticket->organization_id)
                ->whereIn('assigned_to_user_id', $candidates)
                ->whereNotIn('status', [\App\Enums\ServiceTicket\ServiceTicketStatus::Closed, \App\Enums\ServiceTicket\ServiceTicketStatus::Done])
                ->groupBy('assigned_to_user_id')
                ->selectRaw('assigned_to_user_id, COUNT(*) as aggregate')
                ->pluck('aggregate', 'assigned_to_user_id');
            usort($candidates, fn (int $a, int $b): int => [(int) ($load[$a] ?? 0), $a] <=> [(int) ($load[$b] ?? 0), $b]);

            return $candidates[0];
        }

        // Reihum: nach der zuletzt in dieser Queue zugewiesenen Person.
        $last = ServiceTicket::query()
            ->where('organization_id', $ticket->organization_id)
            ->where('queue_id', $ticket->queue_id)
            ->whereKeyNot($ticket->id)
            ->whereIn('assigned_to_user_id', $candidates)
            ->orderByDesc('id')
            ->value('assigned_to_user_id');
        $position = $last !== null ? array_search((int) $last, $candidates, true) : false;

        return $position === false ? $candidates[0] : $candidates[((int) $position + 1) % count($candidates)];
    }

    /** @param array<string, mixed> $actions */
    private function applyActions(ServiceTicket $ticket, array $actions): ?int {
        if (isset($actions['set_queue'])) {
            $queueId = \App\Models\ServiceTicket\ServiceQueue::query()
                ->where('organization_id', $ticket->organization_id)
                ->whereKey((int) $actions['set_queue'])
                ->value('id');
            if ($queueId !== null) {
                $ticket->queue_id = $queueId;
            }
        }
        if (isset($actions['set_priority'])) {
            $priority = \App\Enums\ServiceTicket\ServiceTicketPriority::tryFrom((string) $actions['set_priority']);
            if ($priority !== null) {
                $ticket->priority = $priority;
            }
        }
        if (isset($actions['set_sla'])) {
            $contract = \App\Models\ServiceTicket\SlaContract::query()
                ->where('organization_id', $ticket->organization_id)
                ->whereKey((int) $actions['set_sla'])
                ->first();
            if ($contract !== null) {
                app(ServiceTicketService::class)->attachSla($ticket, $contract);
            }
        }
        if (isset($actions['set_team'])) {
            $queue = \App\Models\ServiceTicket\ServiceQueue::query()
                ->where('organization_id', $ticket->organization_id)
                ->where('team_id', (int) $actions['set_team'])
                ->value('id');
            if ($queue !== null) {
                $ticket->queue_id = $queue;
            }
        }
        if (isset($actions['assign']) && in_array($actions['assign'], self::ASSIGN_STRATEGIES, true)) {
            return $this->pickAssignee($ticket, (string) $actions['assign']);
        }

        return null;
    }
}
