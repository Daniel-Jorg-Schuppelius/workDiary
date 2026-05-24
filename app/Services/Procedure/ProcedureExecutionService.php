<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureExecutionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Procedure;

use App\Enums\Procedure\{ProcedureRunEventType, ProcedureRunStatus, ProcedureStepRunStatus, ProcedureStepType};
use App\Exceptions\{ProcedureDeviationValidationException, ProcedureRunIncompleteException, ProcedureStepBlockedException};
use App\Models\{ProcedureBackupProof, ProcedureRun, ProcedureRunEvent, ProcedureStepDef, ProcedureStepRun, ProcedureTemplate, ProcedureTemplateVersion, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pflicht- und Reihenfolgelogik fuer Prozedurlaeufe (MVP-026).
 *
 * `canExecute()` blockt Schritte, deren blockierende Vorgaenger noch
 * offen sind oder deren Rolle/Vier-Augen-Anforderung fehlt. Ein Run
 * kann nur abgeschlossen werden, wenn alle Pflichtschritte einen
 * finalen Status haben.
 */
class ProcedureExecutionService {
    public function __construct(
        private readonly ProcedureTemplateService $templates,
        private readonly BackupProofService $backups,
        private readonly SecondPersonGate $secondPerson,
        private readonly DeviationRecorder $deviations,
    ) {
    }

    /**
     * Startet einen Run auf Basis der aktuell gueltigen Version der
     * Vorlage (gefroren) und erzeugt fuer jeden Schritt einen
     * pending Step-Run.
     */
    public function start(ProcedureTemplate $template, Model $subject, User $actor, ?User $assignee = null): ProcedureRun {
        $version = $this->templates->currentVersionFor($template);
        if (! $version instanceof ProcedureTemplateVersion) {
            throw new \RuntimeException(sprintf(
                'Procedure template #%d has no published version at %s.',
                $template->id,
                Carbon::today()->toDateString(),
            ));
        }

        return DB::transaction(function () use ($template, $version, $subject, $actor, $assignee) {
            $run = new ProcedureRun([
                'procedure_template_version_id' => $version->id,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => (int) $subject->getKey(),
                'status' => ProcedureRunStatus::Open->value,
                'assigned_user_id' => $assignee?->id,
                'started_at' => now(),
                'created_by_user_id' => $actor->id,
            ]);
            $run->organization_id = $template->organization_id;
            $run->save();

            foreach ($version->steps as $stepDef) {
                ProcedureStepRun::query()->create([
                    'procedure_run_id' => $run->id,
                    'procedure_step_def_id' => $stepDef->id,
                    'status' => ProcedureStepRunStatus::Pending->value,
                ]);
            }

            $this->recordEvent($run, ProcedureRunEventType::RunStarted, $actor, null, [
                'procedure_template_version_id' => $version->id,
            ]);

            return $run->refresh();
        });
    }

    /**
     * Pruefung der Sperr-Logik aus MVP-026 §3. Wirft eine Exception
     * mit konkretem `reason`; Aufrufer koennen die Pruefung auch
     * defensiv via {@see blockReasonFor()} abfragen.
     */
    public function canExecute(ProcedureStepRun $stepRun, ?User $actor = null): bool {
        $reason = $this->blockReasonFor($stepRun, $actor);
        if ($reason !== null) {
            throw ProcedureStepBlockedException::for($reason, $stepRun);
        }

        return true;
    }

    public function blockReasonFor(ProcedureStepRun $stepRun, ?User $actor = null): ?string {
        $run = $stepRun->run;
        if (! $run instanceof ProcedureRun || ! $run->status->isActive()) {
            return ProcedureStepBlockedException::REASON_RUN_NOT_ACTIVE;
        }

        if ($stepRun->status->isFinal()) {
            return ProcedureStepBlockedException::REASON_STEP_ALREADY_FINAL;
        }

        $def = $stepRun->stepDef;
        if (! $def instanceof ProcedureStepDef) {
            return ProcedureStepBlockedException::REASON_PREVIOUS_STEP_INCOMPLETE;
        }

        if ($this->hasOpenBlockingPredecessor($run, $def)) {
            return ProcedureStepBlockedException::REASON_PREVIOUS_STEP_INCOMPLETE;
        }

        if ($actor !== null && $def->required_role !== null && $def->required_role !== '' && ! $actor->hasRole($def->required_role)) {
            return ProcedureStepBlockedException::REASON_MISSING_ROLE;
        }

        if ($def->required_qualification_code !== null && $def->required_qualification_code !== '') {
            return ProcedureStepBlockedException::REASON_MISSING_QUALIFICATION;
        }

        if ($this->secondPerson->requiresSecondPerson($stepRun)) {
            if ($stepRun->second_person_user_id === null || $stepRun->second_person_signed_at === null) {
                return ProcedureStepBlockedException::REASON_SECOND_PERSON_REQUIRED;
            }
        }

        $config = is_array($def->config) ? $def->config : [];

        if ($def->step_type === ProcedureStepType::Backup) {
            $proof = ProcedureBackupProof::query()
                ->where('procedure_step_run_id', $stepRun->id)
                ->first();
            if (! $proof instanceof ProcedureBackupProof || ! $proof->verified) {
                return ProcedureStepBlockedException::REASON_BACKUP_NOT_VERIFIED;
            }
        }

        if (! empty($config['requires_prior_backup'])) {
            $maxAge = (int) ($config['prior_backup_max_age_minutes'] ?? 0);
            if (! $this->backups->priorBackupValid($stepRun, $maxAge)) {
                return ProcedureStepBlockedException::REASON_PRIOR_BACKUP_MISSING;
            }
        }

        return null;
    }

    /**
     * Setzt einen Schritt auf einen finalen Status (oder
     * uebersteuert pending → blocked / pending). Validiert
     * vorab via {@see canExecute()} (ausser bei `failed`, das auch
     * bei gesperrtem Schritt erlaubt sein muss, um Abweichungen zu
     * dokumentieren — folgt in MVP-029).
     *
     * @param  array<string, mixed>  $payload
     */
    public function execute(ProcedureStepRun $stepRun, User $actor, ProcedureStepRunStatus $target, array $payload = []): ProcedureStepRun {
        if (! $target->isFinal()) {
            throw new \InvalidArgumentException('Target status must be a final step-run status.');
        }

        if ($target === ProcedureStepRunStatus::Done) {
            $this->secondPerson->ensure($stepRun, $actor);
        }

        $this->canExecute($stepRun, $actor);

        return DB::transaction(function () use ($stepRun, $actor, $target, $payload) {
            $stepRun->status = $target;
            $stepRun->executed_by_user_id = $actor->id;
            $stepRun->executed_at = now();
            if (array_key_exists('value_json', $payload)) {
                /** @var array<string, mixed>|null $value */
                $value = $payload['value_json'];
                $stepRun->value_json = $value;
            }
            if (array_key_exists('note', $payload)) {
                $stepRun->note = $payload['note'] !== null ? (string) $payload['note'] : null;
            }
            if (array_key_exists('proof_attachment_id', $payload)) {
                $stepRun->proof_attachment_id = $payload['proof_attachment_id'] !== null ? (int) $payload['proof_attachment_id'] : null;
            }
            $stepRun->save();

            $run = $stepRun->run;
            if ($run instanceof ProcedureRun && $run->status === ProcedureRunStatus::Open) {
                $run->status = ProcedureRunStatus::InProgress;
                $run->save();
            }

            $eventType = match ($target) {
                ProcedureStepRunStatus::Done, ProcedureStepRunStatus::NA => ProcedureRunEventType::StepCompleted,
                ProcedureStepRunStatus::Failed => ProcedureRunEventType::StepFailed,
                ProcedureStepRunStatus::Deviated => ProcedureRunEventType::StepDeviated,
                default => ProcedureRunEventType::StepCompleted,
            };

            $this->recordEvent(
                $stepRun->run,
                $eventType,
                $actor,
                $stepRun,
                ['status' => $target->value],
            );

            return $stepRun->refresh();
        });
    }

    /**
     * Schliesst einen Run ab. Wirft
     * {@see ProcedureRunIncompleteException}, wenn Pflichtschritte
     * offen sind. Vier-Augen-Pruefung fuer kritische Runs folgt in
     * MVP-028 — dieser Service prueft bereits den Risk-Level und
     * legt das Audit-Event ab.
     */
    public function completeRun(ProcedureRun $run, User $actor): ProcedureRun {
        $missing = $this->missingRequiredStepRuns($run);
        if ($missing !== []) {
            $this->recordEvent($run, ProcedureRunEventType::RunCompletionRejected, $actor, null, [
                'missing_step_run_ids' => $missing,
            ]);
            throw new ProcedureRunIncompleteException($run, $missing);
        }

        $blockingDeviations = $this->deviations->blockingDeviationIdsFor($run);
        if ($blockingDeviations !== []) {
            $this->recordEvent($run, ProcedureRunEventType::RunCompletionRejected, $actor, null, [
                'reason' => 'criticalDeviationOpen',
                'deviation_ids' => $blockingDeviations,
            ]);
            throw ProcedureDeviationValidationException::criticalOpen();
        }

        return DB::transaction(function () use ($run, $actor) {
            $run->status = ProcedureRunStatus::Completed;
            $run->completed_at = now();
            $run->save();

            $this->recordEvent($run, ProcedureRunEventType::RunCompleted, $actor, null, null);

            return $run->refresh();
        });
    }

    public function abort(ProcedureRun $run, User $actor, ?string $reason = null): ProcedureRun {
        return DB::transaction(function () use ($run, $actor, $reason) {
            $run->status = ProcedureRunStatus::Aborted;
            $run->aborted_at = now();
            $run->abort_reason = $reason;
            $run->save();

            $this->recordEvent($run, ProcedureRunEventType::RunAborted, $actor, null, [
                'reason' => $reason,
            ]);

            return $run->refresh();
        });
    }

    /**
     * Liefert die IDs offener Pflicht-Step-Runs (leer => alle final).
     *
     * @return list<int>
     */
    public function missingRequiredStepRuns(ProcedureRun $run): array {
        $missing = [];
        foreach ($run->stepRuns()->with('stepDef')->get() as $stepRun) {
            $def = $stepRun->stepDef;
            if (! $def instanceof ProcedureStepDef) {
                continue;
            }
            if ($def->required && ! $stepRun->status->isFinal()) {
                $missing[] = (int) $stepRun->id;
            }
        }

        return $missing;
    }

    private function hasOpenBlockingPredecessor(ProcedureRun $run, ProcedureStepDef $def): bool {
        $rows = ProcedureStepRun::query()
            ->where('procedure_run_id', $run->id)
            ->whereHas('stepDef', function ($q) use ($def): void {
                $q->where('procedure_template_version_id', $def->procedure_template_version_id)
                    ->where('blocking', true)
                    ->where('sort_order', '<', $def->sort_order);
            })
            ->pluck('status');

        foreach ($rows as $status) {
            $enum = $status instanceof ProcedureStepRunStatus
                ? $status
                : ProcedureStepRunStatus::from((string) $status);
            if (! $enum->isFinal()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function recordEvent(?ProcedureRun $run, ProcedureRunEventType $type, ?User $actor, ?ProcedureStepRun $stepRun, ?array $payload): ProcedureRunEvent {
        if (! $run instanceof ProcedureRun) {
            throw new \RuntimeException('Cannot record event without a parent run.');
        }

        return ProcedureRunEvent::query()->create([
            'procedure_run_id' => $run->id,
            'procedure_step_run_id' => $stepRun?->id,
            'event_type' => $type->value,
            'payload' => $payload,
            'actor_user_id' => $actor?->id,
            'created_at' => now(),
        ]);
    }
}
