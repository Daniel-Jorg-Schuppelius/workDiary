<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanActualExplainService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Suggestions;

use App\Models\Ai\AiTextSuggestion;
use App\Models\{Organization, Project, User};
use App\Services\Ai\AiInvocationService;
use App\Services\Ai\Dto\{AiTextResult, ExplainRequest};
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Suggestions\Concerns\DecidesSuggestions;
use App\Services\Reporting\EconomicsReportBuilder;
use Carbon\CarbonImmutable;

/**
 * KI-Welle 2 — Plan-Ist-Abweichung erklären (Feature 148, MVP-732;
 * Ausblick aus ../plan-ist-abgleich.md): die Nachkalkulation liefert die
 * Zahlen, die KI die Prosa dazu.
 *
 * In den Prompt gehen AUSSCHLIESSLICH benannte Kennzahlen — kein Projekt-,
 * Kunden- oder Personenname, keine Einzelbuchungen. Deshalb ist die
 * Capability `low` eingestuft und für den Cloud-Betrieb geeignet. Das
 * Ergebnis ist eine Lesehilfe: es ändert keine Fachdaten und kennt nur
 * „verwerfen" (nie Auto-Apply, keine automatische Maßnahme).
 */
class PlanActualExplainService {
    use DecidesSuggestions;

    public const CAPABILITY = 'plan_actual.explain';

    public function __construct(
        private readonly AiInvocationService $invocation,
        private readonly EconomicsReportBuilder $economics,
    ) {}

    /** Erklärung der Plan-Ist-Abweichung eines Projekts im Zeitraum. */
    public function explainProject(
        Project $project,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?User $user,
        ?int $connectionId = null,
    ): AiTextSuggestion {
        $organization = $this->organizationOf($project);

        $rows = $this->economics->byProject($from, $to, [(int) $project->id]);
        $row = $rows[0] ?? null;
        if ($row === null) {
            throw new AiException((string) __('ai.error.plan_actual_no_data'));
        }
        if (! self::hasPlan($row)) {
            throw new AiException((string) __('ai.error.plan_actual_no_plan'));
        }

        $facts = self::factsFor($row, $from, $to);

        $request = new ExplainRequest(
            facts: $facts,
            question: (string) __('ai.plan_actual.question'),
            language: app()->getLocale(),
        );

        $result = $this->invocation->invoke($organization, self::CAPABILITY, $request, $connectionId);
        $payload = $result->result;
        if (! $payload instanceof AiTextResult) {
            throw new AiException((string) __('ai.error.unexpected_result_type'));
        }

        return $this->storeProposal(
            (int) $organization->id,
            $project,
            self::CAPABILITY,
            (string) __('ai.plan_actual.source_hint', [
                'from' => $from->format('d.m.Y'),
                'to' => $to->format('d.m.Y'),
            ]),
            $payload->text,
            $result,
            $user,
        );
    }

    /**
     * Führt das Projekt überhaupt einen Plan? `time_budget`/`budget` sind
     * NOT NULL mit Default 0 — „nicht gepflegt" heißt hier also 0, nicht null.
     *
     * @param  array<string, mixed>  $row  Zeile aus {@see EconomicsReportBuilder::byProject()}
     */
    public static function hasPlan(array $row): bool {
        return (int) ($row['planMinutes'] ?? 0) > 0 || (float) ($row['planBudget'] ?? 0.0) > 0.0;
    }

    /**
     * Kennzahlen-Whitelist: nur diese benannten Werte verlassen die App —
     * keine Namen, keine IDs, keine Einzelbuchungen.
     *
     * @param  array<string, mixed>  $row  Zeile aus {@see EconomicsReportBuilder::byProject()}
     * @return array<string, scalar|null>
     */
    public static function factsFor(array $row, CarbonImmutable $from, CarbonImmutable $to): array {
        $planMinutes = $row['planMinutes'] === null ? null : (int) $row['planMinutes'];
        $actualMinutes = (int) $row['actualMinutes'];
        $planBudget = $row['planBudget'] === null ? null : (float) $row['planBudget'];

        return [
            'zeitraum_von' => $from->toDateString(),
            'zeitraum_bis' => $to->toDateString(),
            'plan_minuten' => $planMinutes,
            'ist_minuten' => $actualMinutes,
            'abweichung_minuten' => $row['planMinutesDelta'] === null ? null : (int) $row['planMinutesDelta'],
            'abweichung_zeit_prozent' => $planMinutes !== null && $planMinutes > 0
                ? round((($actualMinutes - $planMinutes) / $planMinutes) * 100, 1)
                : null,
            'plan_budget_eur' => $planBudget,
            'ist_kosten_eur' => round((float) $row['actualCost'], 2),
            'abweichung_budget_eur' => $row['planBudgetDelta'] === null ? null : round((float) $row['planBudgetDelta'], 2),
            'erloes_eur' => round((float) $row['revenue'], 2),
            'deckungsbeitrag_eur' => round((float) $row['contribution'], 2),
            'marge_prozent' => round((float) $row['margin'], 1),
            'nicht_abrechenbar_anteil_prozent' => round((float) $row['nonBillableShare'], 1),
            'nacharbeit_anteil_prozent' => round((float) $row['reworkShare'], 1),
            'kostensaetze_unvollstaendig' => (bool) $row['costRateMissing'],
        ];
    }

    private function organizationOf(Project $project): Organization {
        return $project->organization ?? Organization::query()->withoutGlobalScopes()->findOrFail($project->organization_id);
    }
}
