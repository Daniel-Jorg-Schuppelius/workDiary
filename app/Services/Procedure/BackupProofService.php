<?php

/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupProofService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Procedure;

use App\Enums\Procedure\ProcedureBackupScope;
use App\Enums\Procedure\ProcedureBackupStorageTarget;
use App\Enums\Procedure\ProcedureBackupVerifyMethod;
use App\Enums\Procedure\ProcedureRunEventType;
use App\Enums\Procedure\ProcedureStepRunStatus;
use App\Enums\Procedure\ProcedureStepType;
use App\Exceptions\ProcedureBackupValidationException;
use App\Exceptions\ProcedureStepBlockedException;
use App\Models\ProcedureBackupProof;
use App\Models\ProcedureRunEvent;
use App\Models\ProcedureStepRun;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Verwaltet Backup-Nachweise (MVP-027) und stellt sicher, dass ein
 * Backup-Schritt erst dann auf `done` gehen kann, wenn der Nachweis
 * erfolgreich verifiziert wurde.
 */
class BackupProofService {
    /**
     * @param  array{
     *     backup_scope: string,
     *     source_label: string,
     *     taken_at?: \DateTimeInterface|string|null,
     *     size_bytes?: int,
     *     checksum_algo?: ?string,
     *     checksum_value?: ?string,
     *     storage_target: string,
     *     attachment_id?: ?int,
     *     external_ref?: ?string,
     *     verify_method: string,
     *     verify_note?: ?string
     * }  $payload
     */
    public function register(ProcedureStepRun $stepRun, User $actor, array $payload): ProcedureBackupProof {
        $this->assertBackupStep($stepRun);

        $config = $this->stepConfig($stepRun);
        $rawTakenAt = $payload['taken_at'] ?? null;
        $takenAt = $rawTakenAt instanceof \DateTimeInterface
            ? Carbon::instance($rawTakenAt)
            : ($rawTakenAt !== null ? Carbon::parse((string) $rawTakenAt) : Carbon::now());

        $sizeBytes = (int) ($payload['size_bytes'] ?? 0);
        $minSizeKb = (int) ($config['min_size_kb'] ?? 0);
        if ($minSizeKb > 0 && $sizeBytes < $minSizeKb * 1024) {
            throw ProcedureBackupValidationException::for(ProcedureBackupValidationException::REASON_TOO_SMALL);
        }

        $maxAgeMinutes = (int) ($config['max_age_minutes'] ?? 0);
        if ($maxAgeMinutes > 0 && abs((int) $takenAt->diffInMinutes(Carbon::now())) > $maxAgeMinutes) {
            throw ProcedureBackupValidationException::for(ProcedureBackupValidationException::REASON_TOO_OLD);
        }

        $storageTarget = ProcedureBackupStorageTarget::from($payload['storage_target']);
        if ($storageTarget === ProcedureBackupStorageTarget::External) {
            $externalRef = isset($payload['external_ref']) ? (string) $payload['external_ref'] : '';
            if ($externalRef === '') {
                throw ProcedureBackupValidationException::for(ProcedureBackupValidationException::REASON_MISSING_EXTERNAL_REF);
            }
        } elseif (($payload['attachment_id'] ?? null) === null) {
            throw ProcedureBackupValidationException::for(ProcedureBackupValidationException::REASON_MISSING_ATTACHMENT);
        }

        $verifyMethod = ProcedureBackupVerifyMethod::from($payload['verify_method']);

        return DB::transaction(function () use ($stepRun, $actor, $payload, $takenAt, $sizeBytes, $storageTarget, $verifyMethod) {
            $existing = ProcedureBackupProof::query()
                ->where('procedure_step_run_id', $stepRun->id)
                ->first();
            if ($existing instanceof ProcedureBackupProof) {
                $existing->delete();
            }

            $proof = ProcedureBackupProof::query()->create([
                'procedure_step_run_id' => $stepRun->id,
                'backup_scope' => ProcedureBackupScope::from($payload['backup_scope'])->value,
                'source_label' => $payload['source_label'],
                'taken_at' => $takenAt,
                'size_bytes' => $sizeBytes,
                'checksum_algo' => $payload['checksum_algo'] ?? null,
                'checksum_value' => $payload['checksum_value'] ?? null,
                'storage_target' => $storageTarget->value,
                'attachment_id' => $payload['attachment_id'] ?? null,
                'external_ref' => $payload['external_ref'] ?? null,
                'verified' => false,
                'verify_method' => $verifyMethod->value,
                'verify_note' => $payload['verify_note'] ?? null,
                'created_at' => Carbon::now(),
            ]);

            $this->recordEvent($stepRun, ProcedureRunEventType::BackupRegistered, $actor, [
                'proof_id' => $proof->id,
                'verify_method' => $verifyMethod->value,
            ]);

            return $proof->refresh();
        });
    }

    /**
     * Markiert einen Backup-Nachweis als verifiziert. Bei `checksum`
     * wird der vom Aufrufer uebergebene Wert mit dem gespeicherten
     * verglichen; bei `restoreCheck`/`managerConfirmation` reicht ein
     * Berechtigter aus (Vier-Augen-Pruefung folgt in MVP-028).
     */
    public function verify(ProcedureBackupProof $proof, User $verifier, ?string $checksumProbe = null, ?string $note = null): ProcedureBackupProof {
        if ($proof->verify_method === ProcedureBackupVerifyMethod::Checksum) {
            $expected = (string) ($proof->checksum_value ?? '');
            if ($expected === '' || $checksumProbe === null || ! hash_equals($expected, $checksumProbe)) {
                throw ProcedureBackupValidationException::for(ProcedureBackupValidationException::REASON_CHECKSUM_MISMATCH);
            }
        }

        return DB::transaction(function () use ($proof, $verifier, $note) {
            $proof->verified = true;
            $proof->verified_at = Carbon::now();
            $proof->verified_by_user_id = $verifier->id;
            if ($note !== null) {
                $proof->verify_note = $note;
            }
            $proof->save();

            $stepRun = $proof->stepRun;
            if ($stepRun instanceof ProcedureStepRun) {
                $this->recordEvent($stepRun, ProcedureRunEventType::BackupVerified, $verifier, [
                    'proof_id' => $proof->id,
                ]);
            }

            return $proof->refresh();
        });
    }

    /**
     * Pruefung fuer {@see ProcedureExecutionService}:
     * Backup-Schritt darf nur `done` werden, wenn ein Nachweis
     * existiert und verifiziert ist.
     */
    public function ensureReleaseAllowed(ProcedureStepRun $stepRun): void {
        $stepDef = $stepRun->stepDef;
        if ($stepDef === null || $stepDef->step_type !== ProcedureStepType::Backup) {
            return;
        }

        $proof = ProcedureBackupProof::query()
            ->where('procedure_step_run_id', $stepRun->id)
            ->first();
        if (! $proof instanceof ProcedureBackupProof || ! $proof->verified) {
            throw ProcedureBackupValidationException::for(ProcedureBackupValidationException::REASON_NOT_VERIFIED);
        }
    }

    /**
     * Liefert den letzten verifizierten Backup-Schritt innerhalb
     * desselben Runs, der innerhalb von `max_age_minutes` liegt.
     * Genutzt fuer `requires_prior_backup`-Pruefungen in
     * {@see ProcedureExecutionService::blockReasonFor}.
     */
    public function priorBackupValid(ProcedureStepRun $stepRun, int $maxAgeMinutes): bool {
        $run = $stepRun->run;
        if ($run === null) {
            return false;
        }

        $candidates = ProcedureStepRun::query()
            ->where('procedure_run_id', $run->id)
            ->where('status', ProcedureStepRunStatus::Done->value)
            ->whereHas('stepDef', function ($q): void {
                $q->where('step_type', ProcedureStepType::Backup->value);
            })
            ->pluck('id');

        if ($candidates->isEmpty()) {
            return false;
        }

        $cutoff = $maxAgeMinutes > 0 ? Carbon::now()->subMinutes($maxAgeMinutes) : null;
        $query = ProcedureBackupProof::query()
            ->whereIn('procedure_step_run_id', $candidates)
            ->where('verified', true);
        if ($cutoff !== null) {
            $query->where('taken_at', '>=', $cutoff);
        }

        return $query->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function stepConfig(ProcedureStepRun $stepRun): array {
        $def = $stepRun->stepDef;
        if ($def === null) {
            return [];
        }
        $config = $def->config;

        return is_array($config) ? $config : [];
    }

    private function assertBackupStep(ProcedureStepRun $stepRun): void {
        $def = $stepRun->stepDef;
        if ($def === null || $def->step_type !== ProcedureStepType::Backup) {
            throw ProcedureStepBlockedException::for(
                ProcedureStepBlockedException::REASON_STEP_ALREADY_FINAL,
                $stepRun,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordEvent(ProcedureStepRun $stepRun, ProcedureRunEventType $type, User $actor, array $payload): void {
        $run = $stepRun->run;
        if ($run === null) {
            return;
        }

        ProcedureRunEvent::query()->create([
            'procedure_run_id' => $run->id,
            'procedure_step_run_id' => $stepRun->id,
            'event_type' => $type->value,
            'payload' => $payload,
            'actor_user_id' => $actor->id,
            'created_at' => Carbon::now(),
        ]);
    }
}
