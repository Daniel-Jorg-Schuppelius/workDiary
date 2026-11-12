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

use App\Enums\Procedure\{ProcedureBackupScope, ProcedureBackupStorageTarget, ProcedureBackupVerifyMethod, ProcedureRunEventType, ProcedureStepRunStatus, ProcedureStepType};
use App\Exceptions\{ProcedureBackupValidationException, ProcedureStepBlockedException};
use App\Models\Platform\User;
use App\Models\Procedure\{ProcedureBackupProof, ProcedureStepRun};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Verwaltet Backup-Nachweise (MVP-027) und stellt sicher, dass ein
 * Backup-Schritt erst dann auf `done` gehen kann, wenn der Nachweis
 * erfolgreich verifiziert wurde.
 */
class BackupProofService {
    // Zentraler Event-Schreiber + stepConfig (Vollaudit 2026-07, N38).
    use \App\Services\Procedure\Concerns\RecordsProcedureRunEvents;

    /**
     * `file_checksum` ist die am hochgeladenen Nachweis berechnete Prüfsumme;
     * stimmt sie mit der erwarteten überein, gilt der Nachweis ohne zweite
     * Person als verifiziert.
     *
     * @param  array{
     *     backup_scope: string,
     *     source_label: string,
     *     taken_at?: \DateTimeInterface|string|null,
     *     size_bytes?: int,
     *     checksum_algo?: ?string,
     *     checksum_value?: ?string,
     *     file_checksum?: ?string,
     *     storage_target: string,
     *     attachment_id?: ?int,
     *     external_ref?: ?string,
     *     verify_method: string,
     *     verify_note?: ?string
     * }  $payload
     */
    public function register(ProcedureStepRun $stepRun, User $actor, array $payload): ProcedureBackupProof {
        [$takenAt, $sizeBytes, $storageTarget, $verifyMethod, $expected, $automatic] = $this->checked($stepRun, $payload, false);

        return DB::transaction(function () use ($stepRun, $actor, $payload, $takenAt, $sizeBytes, $storageTarget, $verifyMethod, $expected, $automatic) {
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
                'checksum_value' => $expected,
                'storage_target' => $storageTarget->value,
                'attachment_id' => $payload['attachment_id'] ?? null,
                'external_ref' => $payload['external_ref'] ?? null,
                'registered_by_user_id' => $actor->id,
                'verified' => false,
                'verify_method' => $verifyMethod->value,
                'verify_note' => $payload['verify_note'] ?? null,
                'created_at' => Carbon::now(),
            ]);

            $this->recordStepEvent($stepRun, ProcedureRunEventType::BackupRegistered, $actor, [
                'proof_id' => $proof->id,
                'verify_method' => $verifyMethod->value,
            ]);

            if ($automatic) {
                $this->markVerified($proof, $actor, null, true);
            }

            return $proof->refresh();
        });
    }

    /**
     * Markiert einen Backup-Nachweis als verifiziert. Bei `checksum` wird der
     * übergebene Wert mit dem gespeicherten verglichen; Rückspieltest und
     * Bestätigung durch die Leitung verlangen eine andere Person als die, die
     * den Nachweis erfasst hat.
     */
    public function verify(ProcedureBackupProof $proof, User $verifier, ?string $checksumProbe = null, ?string $note = null): ProcedureBackupProof {
        if ($proof->verify_method === ProcedureBackupVerifyMethod::Checksum) {
            $expected = (string) ($proof->checksum_value ?? '');
            $probe = self::normalizeChecksum($checksumProbe);
            if ($expected === '' || $probe === null || ! hash_equals($expected, $probe)) {
                throw ProcedureBackupValidationException::for(ProcedureBackupValidationException::REASON_CHECKSUM_MISMATCH);
            }
        } elseif ($proof->registered_by_user_id !== null && (int) $proof->registered_by_user_id === (int) $verifier->id) {
            throw ProcedureBackupValidationException::for(ProcedureBackupValidationException::REASON_SELF_VERIFY);
        }

        return DB::transaction(fn (): ProcedureBackupProof => $this->markVerified($proof, $verifier, $note, false));
    }

    /** Lehnt einen Nachweis mit Begründung ab; der Schritt bleibt gesperrt, bis ein neuer Nachweis erfasst ist. */
    public function reject(ProcedureBackupProof $proof, User $actor, string $reason): ProcedureBackupProof {
        return DB::transaction(function () use ($proof, $actor, $reason) {
            $proof->verified = false;
            $proof->verified_at = null;
            $proof->verified_by_user_id = $actor->id;
            $proof->rejected_at = Carbon::now();
            $proof->verify_note = $reason;
            $proof->save();

            $stepRun = $proof->stepRun;
            if ($stepRun instanceof ProcedureStepRun) {
                $this->recordStepEvent($stepRun, ProcedureRunEventType::BackupRejected, $actor, [
                    'proof_id' => $proof->id,
                    'reason' => $reason,
                ]);
            }

            return $proof->refresh();
        });
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
     * Prüft einen Nachweis vor dem Hochladen der Datei, damit ein abgewiesener
     * Nachweis keinen Anhang hinterlässt.
     *
     * @param  array<string, mixed>  $payload
     */
    public function assertRegistrable(ProcedureStepRun $stepRun, array $payload): void {
        $this->checked($stepRun, $payload, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: Carbon, 1: int, 2: ProcedureBackupStorageTarget, 3: ProcedureBackupVerifyMethod, 4: ?string, 5: bool}
     */
    private function checked(ProcedureStepRun $stepRun, array $payload, bool $attachmentPending): array {
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
        } elseif (! $attachmentPending && ($payload['attachment_id'] ?? null) === null) {
            throw ProcedureBackupValidationException::for(ProcedureBackupValidationException::REASON_MISSING_ATTACHMENT);
        }

        $verifyMethod = ProcedureBackupVerifyMethod::from($payload['verify_method']);
        $expected = self::normalizeChecksum($payload['checksum_value'] ?? null);
        $fileChecksum = self::normalizeChecksum($payload['file_checksum'] ?? null);
        $automatic = false;
        if ($verifyMethod === ProcedureBackupVerifyMethod::Checksum) {
            if ($expected === null) {
                throw ProcedureBackupValidationException::for(ProcedureBackupValidationException::REASON_CHECKSUM_MISSING);
            }
            if ($fileChecksum !== null) {
                if (! hash_equals($expected, $fileChecksum)) {
                    throw ProcedureBackupValidationException::for(ProcedureBackupValidationException::REASON_CHECKSUM_MISMATCH);
                }
                $automatic = true;
            }
        }

        return [$takenAt, $sizeBytes, $storageTarget, $verifyMethod, $expected, $automatic];
    }

    private function markVerified(ProcedureBackupProof $proof, User $verifier, ?string $note, bool $automatic): ProcedureBackupProof {
        $proof->verified = true;
        $proof->verified_at = Carbon::now();
        $proof->verified_by_user_id = $verifier->id;
        $proof->rejected_at = null;
        if ($note !== null) {
            $proof->verify_note = $note;
        }
        $proof->save();

        $stepRun = $proof->stepRun;
        if ($stepRun instanceof ProcedureStepRun) {
            $this->recordStepEvent($stepRun, ProcedureRunEventType::BackupVerified, $verifier, array_filter([
                'proof_id' => $proof->id,
                'automatic' => $automatic,
            ]));
        }

        return $proof->refresh();
    }

    /** Prüfsummen vergleichen wir ohne Leerraum und ohne Groß-/Kleinschreibung (Hex aus verschiedenen Werkzeugen). */
    private static function normalizeChecksum(mixed $value): ?string {
        $text = is_scalar($value) ? mb_strtolower(trim((string) $value)) : '';

        return $text === '' ? null : $text;
    }
}
