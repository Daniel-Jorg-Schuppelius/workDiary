<?php

/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeviationRecorder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Procedure;

use App\Enums\OpenIssue\OpenIssueSeverity;
use App\Enums\OpenIssue\OpenIssueSource;
use App\Enums\OpenIssue\OpenIssueVisibility;
use App\Enums\Procedure\ProcedureDeviationProposedAction;
use App\Enums\Procedure\ProcedureDeviationSeverity;
use App\Enums\Procedure\ProcedureDeviationType;
use App\Enums\Procedure\ProcedureRunEventType;
use App\Enums\Procedure\ProcedureStepRunStatus;
use App\Exceptions\ProcedureDeviationValidationException;
use App\Models\ProcedureDeviation;
use App\Models\ProcedureRun;
use App\Models\ProcedureRunEvent;
use App\Models\ProcedureStepRun;
use App\Models\User;
use App\Services\OpenIssue\OpenIssueService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Erfasst Abweichungen zu Prozedur-Schritten (MVP-029) und stoesst
 * vorgeschlagene Folgeaktionen an. Setzt den Schritt auf `deviated`
 * und verlinkt das Ergebnis (z. B. neuer Offener Punkt).
 */
class DeviationRecorder {
    public const MIN_REASON_LENGTH = 20;

    public function __construct(
        private readonly OpenIssueService $openIssues,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(ProcedureStepRun $stepRun, User $actor, array $payload): ProcedureDeviation {
        if ($stepRun->deviation_id !== null) {
            throw ProcedureDeviationValidationException::for(
                ProcedureDeviationValidationException::REASON_ALREADY_RECORDED,
            );
        }

        $type = $this->parseType($payload['deviation_type'] ?? null);
        $severity = $this->parseSeverity($payload['severity'] ?? null) ?? $type->defaultSeverity();
        $action = $this->parseAction($payload['proposed_action'] ?? null);
        $reason = trim((string) ($payload['reason_text'] ?? ''));

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw ProcedureDeviationValidationException::reasonTooShort();
        }

        if ($severity === ProcedureDeviationSeverity::Critical
            && ($action === null || $action === ProcedureDeviationProposedAction::None)) {
            // Kritische Abweichungen muessen eine Folgeaktion benennen — wenn
            // keine angegeben wurde, eskalieren wir als sicherer Default.
            $action = ProcedureDeviationProposedAction::Escalate;
        }

        $run = $stepRun->run;
        if (! $run instanceof ProcedureRun) {
            throw ProcedureDeviationValidationException::for(
                ProcedureDeviationValidationException::REASON_ALREADY_RECORDED,
            );
        }

        return DB::transaction(function () use ($stepRun, $actor, $type, $severity, $action, $reason, $run, $payload) {
            $deviation = ProcedureDeviation::query()->create([
                'organization_id' => $run->organization_id,
                'procedure_step_run_id' => $stepRun->id,
                'deviation_type' => $type->value,
                'severity' => $severity->value,
                'reason_text' => $reason,
                'proposed_action' => $action?->value,
                'created_by_user_id' => $actor->id,
            ]);

            $stepRun->status = ProcedureStepRunStatus::Deviated;
            $stepRun->deviation_id = $deviation->id;
            $stepRun->executed_by_user_id ??= $actor->id;
            $stepRun->executed_at ??= Carbon::now();
            $stepRun->save();

            $this->recordEvent($run, $stepRun, ProcedureRunEventType::DeviationRecorded, $actor, [
                'deviation_id' => $deviation->id,
                'deviation_type' => $deviation->deviation_type->value,
                'severity' => $deviation->severity->value,
                'proposed_action' => $deviation->proposed_action?->value,
            ]);

            if ($action !== null && $action !== ProcedureDeviationProposedAction::None) {
                $this->triggerAction($deviation, $stepRun, $run, $actor, $action, $payload);
            }

            return $deviation->refresh();
        });
    }

    public function acceptRisk(ProcedureDeviation $deviation, User $actor, ?string $note = null): ProcedureDeviation {
        return DB::transaction(function () use ($deviation, $actor, $note) {
            $deviation->risk_accepted_by_user_id = $actor->id;
            $deviation->risk_accepted_at = Carbon::now();
            $deviation->save();

            $run = $deviation->stepRun?->run;
            if ($run instanceof ProcedureRun) {
                $this->recordEvent($run, $deviation->stepRun, ProcedureRunEventType::CriticalRiskAccepted, $actor, [
                    'deviation_id' => $deviation->id,
                    'note' => $note,
                ]);
            }

            return $deviation->refresh();
        });
    }

    /**
     * Liefert kritische Abweichungen ohne Risk-Accept, die einen
     * Run-Abschluss blockieren.
     *
     * @return list<int>
     */
    public function blockingDeviationIdsFor(ProcedureRun $run): array {
        $rows = ProcedureDeviation::query()
            ->whereIn('procedure_step_run_id', $run->stepRuns()->pluck('id'))
            ->where('severity', ProcedureDeviationSeverity::Critical->value)
            ->whereNull('risk_accepted_at')
            ->pluck('id');

        return array_values(array_map(static fn ($id): int => (int) $id, $rows->all()));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function triggerAction(
        ProcedureDeviation $deviation,
        ProcedureStepRun $stepRun,
        ProcedureRun $run,
        User $actor,
        ProcedureDeviationProposedAction $action,
        array $payload,
    ): void {
        $detail = ['action' => $action->value];

        if ($action === ProcedureDeviationProposedAction::OpenIssue) {
            $issue = $this->openIssues->create($run, $actor, [
                'source_type' => OpenIssueSource::ProcedureDeviation->value,
                'source_ref_id' => $deviation->id,
                'title' => (string) ($payload['issue_title'] ?? sprintf('Prozedur-Abweichung %s', $deviation->deviation_type->value)),
                'description' => $deviation->reason_text,
                'severity' => $this->mapSeverityToIssue($deviation->severity)->value,
                'visibility' => OpenIssueVisibility::Internal->value,
            ]);

            $deviation->open_issue_id = $issue->id;
            $deviation->save();
            $detail['open_issue_id'] = $issue->id;
        } elseif ($action === ProcedureDeviationProposedAction::NewDiaryEntry) {
            // Folgeauftrag-Erzeugung benoetigt einen Auftrag-Builder
            // (Feature 005). MVP-029 speichert ausschliesslich die
            // Verknuepfung, sofern Aufrufer eine ID mitliefern.
            $followUpId = isset($payload['follow_up_diary_entry_id'])
                ? (int) $payload['follow_up_diary_entry_id']
                : null;
            if ($followUpId !== null) {
                $deviation->follow_up_diary_entry_id = $followUpId;
                $deviation->save();
                $detail['follow_up_diary_entry_id'] = $followUpId;
            }
        }

        $this->recordEvent($run, $stepRun, ProcedureRunEventType::DeviationActionTriggered, $actor, $detail);
    }

    private function mapSeverityToIssue(ProcedureDeviationSeverity $severity): OpenIssueSeverity {
        return match ($severity) {
            ProcedureDeviationSeverity::Low => OpenIssueSeverity::Low,
            ProcedureDeviationSeverity::Medium => OpenIssueSeverity::Medium,
            ProcedureDeviationSeverity::High => OpenIssueSeverity::High,
            ProcedureDeviationSeverity::Critical => OpenIssueSeverity::Critical,
        };
    }

    private function parseType(mixed $value): ProcedureDeviationType {
        if ($value instanceof ProcedureDeviationType) {
            return $value;
        }
        $type = is_string($value) ? ProcedureDeviationType::tryFrom($value) : null;
        if ($type === null) {
            throw ProcedureDeviationValidationException::for(
                ProcedureDeviationValidationException::REASON_INVALID_TYPE,
            );
        }

        return $type;
    }

    private function parseSeverity(mixed $value): ?ProcedureDeviationSeverity {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof ProcedureDeviationSeverity) {
            return $value;
        }
        $severity = is_string($value) ? ProcedureDeviationSeverity::tryFrom($value) : null;
        if ($severity === null) {
            throw ProcedureDeviationValidationException::for(
                ProcedureDeviationValidationException::REASON_INVALID_SEVERITY,
            );
        }

        return $severity;
    }

    private function parseAction(mixed $value): ?ProcedureDeviationProposedAction {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof ProcedureDeviationProposedAction) {
            return $value;
        }
        $action = is_string($value) ? ProcedureDeviationProposedAction::tryFrom($value) : null;
        if ($action === null) {
            throw ProcedureDeviationValidationException::for(
                ProcedureDeviationValidationException::REASON_INVALID_ACTION,
            );
        }

        return $action;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordEvent(
        ProcedureRun $run,
        ?ProcedureStepRun $stepRun,
        ProcedureRunEventType $type,
        User $actor,
        array $payload,
    ): void {
        ProcedureRunEvent::query()->create([
            'procedure_run_id' => $run->id,
            'procedure_step_run_id' => $stepRun?->id,
            'event_type' => $type->value,
            'payload' => $payload,
            'actor_user_id' => $actor->id,
            'created_at' => Carbon::now(),
        ]);
    }
}
