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

use App\Models\{ServiceTicket, TicketRoutingRule, TicketRuleExecution};
use Illuminate\Support\Facades\DB;

/**
 * Regel-Engine (Feature 065, MVP-153) — deterministisch: Regeln in
 * Positions-Reihenfolge, je Aktionstyp gewinnt die ERSTE zutreffende
 * Regel. Jede Anwendung wird mit Regel-Version, erfüllten Bedingungen
 * und Aktionen protokolliert (Pflicht, Vorgabe 065). Dry-Run wertet
 * identisch aus, ändert aber nichts (Regel-Test-Modus).
 */
class TicketRoutingService {
    private const CONDITION_KEYS = ['kind', 'priority', 'source', 'customer_id', 'queue_id'];

    private const ACTION_KEYS = ['set_queue', 'set_priority', 'set_sla', 'set_team'];

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

        DB::transaction(function () use ($ticket, $log, $dryRun): void {
            foreach ($log as $entry) {
                if (! $dryRun) {
                    $this->applyActions($ticket, $entry['actions']);
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

    /** @param array<string, mixed> $actions */
    private function applyActions(ServiceTicket $ticket, array $actions): void {
        if (isset($actions['set_queue'])) {
            $queueId = \App\Models\ServiceQueue::query()
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
            $contract = \App\Models\SlaContract::query()
                ->where('organization_id', $ticket->organization_id)
                ->whereKey((int) $actions['set_sla'])
                ->first();
            if ($contract !== null) {
                app(ServiceTicketService::class)->attachSla($ticket, $contract);
            }
        }
        if (isset($actions['set_team'])) {
            $queue = \App\Models\ServiceQueue::query()
                ->where('organization_id', $ticket->organization_id)
                ->where('team_id', (int) $actions['set_team'])
                ->value('id');
            if ($queue !== null) {
                $ticket->queue_id = $queue;
            }
        }
    }
}
