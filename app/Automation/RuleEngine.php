<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RuleEngine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Automation;

use App\Automation\Actions\RuleAction;
use App\Models\AutomationRule;
use App\Models\AutomationRuleRun;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Lädt aktive Rules für einen Trigger + Organisation, evaluiert Conditions
 * gegen die {@see Model::toArray()}-Repräsentation des Subjects und führt
 * die Aktionen der ersten matchenden Regel aus. Jeder Auswertungs-Versuch
 * wird in {@see AutomationRuleRun} protokolliert (auch No-Match), um eine
 * lückenlose Audit-Spur zu garantieren.
 *
 * Loop-Prevention: wenn für dieselbe Rule + Subject bereits ein
 * `matched`-Run existiert, wird die Regel übersprungen.
 */
class RuleEngine {
    /**
     * @param  iterable<RuleAction>  $actions
     */
    public function __construct(
        private readonly ConditionEvaluator $evaluator,
        private readonly iterable $actions,
    ) {
    }

    public function dispatch(string $triggerEvent, Model $subject, ?int $organizationId = null): void {
        $organizationId ??= $this->resolveOrganizationId($subject);
        if ($organizationId === null) {
            return;
        }

        $rules = AutomationRule::query()
            ->where('organization_id', $organizationId)
            ->where('trigger_event', $triggerEvent)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        if ($rules->isEmpty()) {
            return;
        }

        $context = $this->context($subject);

        foreach ($rules as $rule) {
            if ($this->alreadyMatched($rule, $subject)) {
                continue;
            }

            try {
                $matches = $this->evaluator->matches((array) ($rule->conditions ?: []), $context);
            } catch (\Throwable $e) {
                $this->logRun($rule, $subject, 'error', ['exception' => $e->getMessage()]);
                continue;
            }

            if (! $matches) {
                $this->logRun($rule, $subject, 'no_match', []);
                continue;
            }

            $log = ['actions' => []];
            foreach ((array) ($rule->actions ?: []) as $actionSpec) {
                $type = (string) ($actionSpec['type'] ?? '');
                $params = (array) ($actionSpec['params'] ?? []);
                $impl = $this->resolveAction($type);
                if (! $impl instanceof RuleAction) {
                    $log['actions'][] = ['type' => $type, 'skipped' => 'unknown_action'];
                    continue;
                }
                try {
                    $log['actions'][] = ['type' => $type, 'result' => $impl->execute($subject, $params)];
                } catch (\Throwable $e) {
                    Log::warning('automation: action failed', [
                        'rule_id' => $rule->id, 'type' => $type, 'error' => $e->getMessage(),
                    ]);
                    $log['actions'][] = ['type' => $type, 'error' => $e->getMessage()];
                }
            }

            $this->logRun($rule, $subject, 'matched', $log);
            // Erste matchende Regel gewinnt; weitere Regeln werden nicht ausgeführt.
            return;
        }
    }

    /** @return array<string, mixed> */
    private function context(Model $subject): array {
        return $subject->toArray();
    }

    private function resolveOrganizationId(Model $subject): ?int {
        $value = $subject->getAttribute('organization_id');

        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : null);
    }

    private function alreadyMatched(AutomationRule $rule, Model $subject): bool {
        return AutomationRuleRun::query()
            ->where('rule_id', $rule->id)
            ->where('subject_type', $subject::class)
            ->where('subject_id', (int) $subject->getKey())
            ->where('decision', 'matched')
            ->exists();
    }

    private function resolveAction(string $type): ?RuleAction {
        foreach ($this->actions as $action) {
            if ($action->type() === $type) {
                return $action;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $log */
    private function logRun(AutomationRule $rule, Model $subject, string $decision, array $log): void {
        AutomationRuleRun::create([
            'rule_id' => $rule->id,
            'subject_type' => $subject::class,
            'subject_id' => (int) $subject->getKey(),
            'decision' => $decision,
            'log' => $log,
            'ran_at' => now(),
        ]);
    }
}
