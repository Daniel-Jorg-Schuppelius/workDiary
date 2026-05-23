<?php

/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecondPersonGate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Procedure;

use App\Enums\Procedure\ProcedureRunEventType;
use App\Enums\Procedure\ProcedureStepRunStatus;
use App\Enums\Procedure\ProcedureStepType;
use App\Exceptions\ProcedureSecondPersonException;
use App\Models\ProcedureRunEvent;
use App\Models\ProcedureStepRun;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Steuert das Vier-Augen-Prinzip (MVP-028). Wird sowohl als
 * Pre-Check vor `execute()` als auch als eigenstaendiges Service
 * fuer Request/Take/Sign/Revoke-Aktionen genutzt.
 */
class SecondPersonGate {
    /**
     * Wirft eine {@see ProcedureSecondPersonException}, wenn der
     * Schritt nicht final werden darf.
     */
    public function ensure(ProcedureStepRun $stepRun, ?User $actor = null): void {
        if (! $this->requiresSecondPerson($stepRun)) {
            return;
        }

        if ($stepRun->second_person_user_id === null) {
            throw ProcedureSecondPersonException::missing(
                ProcedureSecondPersonException::REASON_NOT_ASSIGNED,
                $stepRun,
            );
        }

        if ($stepRun->second_person_signed_at === null) {
            throw ProcedureSecondPersonException::missing(
                ProcedureSecondPersonException::REASON_NOT_SIGNED,
                $stepRun,
            );
        }

        if ($actor !== null && $this->selfExclusion($stepRun) && $stepRun->second_person_user_id === $actor->id) {
            throw ProcedureSecondPersonException::selfNotAllowed($stepRun);
        }
    }

    public function request(ProcedureStepRun $stepRun, User $actor): ProcedureStepRun {
        $this->assertRequiresSecondPerson($stepRun);

        $this->recordEvent($stepRun, ProcedureRunEventType::SecondPersonRequested, $actor, [
            'requested_by' => $actor->id,
        ]);

        return $stepRun->refresh();
    }

    public function take(ProcedureStepRun $stepRun, User $taker): ProcedureStepRun {
        $this->assertRequiresSecondPerson($stepRun);

        if ($stepRun->second_person_user_id !== null && $stepRun->second_person_user_id !== $taker->id) {
            throw new ProcedureSecondPersonException(
                ProcedureSecondPersonException::CODE_MISSING,
                ProcedureSecondPersonException::REASON_ALREADY_ASSIGNED,
                $stepRun,
            );
        }

        $config = $this->stepConfig($stepRun);

        if (
            $this->selfExclusion($stepRun)
            && $stepRun->executed_by_user_id !== null
            && $stepRun->executed_by_user_id === $taker->id
        ) {
            throw ProcedureSecondPersonException::selfNotAllowed($stepRun);
        }

        $requiredRole = $config['second_person_role'] ?? null;
        if (is_string($requiredRole) && $requiredRole !== '' && $requiredRole !== 'any' && ! $taker->hasRole($requiredRole)) {
            throw new ProcedureSecondPersonException(
                ProcedureSecondPersonException::CODE_MISSING,
                ProcedureSecondPersonException::REASON_ROLE_MISMATCH,
                $stepRun,
            );
        }

        $requiredQualification = $config['second_person_min_qualification'] ?? null;
        if (is_string($requiredQualification) && $requiredQualification !== '') {
            // Qualifikationsmodell ist noch nicht modelliert (Feature 013).
            // Wir signalisieren das ausdruecklich, damit Aufrufer wissen,
            // dass diese Vorlage aktuell keine Freigabe vergeben kann.
            throw new ProcedureSecondPersonException(
                ProcedureSecondPersonException::CODE_MISSING,
                ProcedureSecondPersonException::REASON_QUALIFICATION_MISSING,
                $stepRun,
            );
        }

        return DB::transaction(function () use ($stepRun, $taker) {
            $stepRun->second_person_user_id = $taker->id;
            $stepRun->save();

            $this->recordEvent($stepRun, ProcedureRunEventType::SecondPersonAssigned, $taker, [
                'taker_id' => $taker->id,
            ]);

            return $stepRun->refresh();
        });
    }

    public function sign(ProcedureStepRun $stepRun, User $signer): ProcedureStepRun {
        $this->assertRequiresSecondPerson($stepRun);

        if ($stepRun->second_person_user_id === null) {
            throw ProcedureSecondPersonException::missing(
                ProcedureSecondPersonException::REASON_NOT_ASSIGNED,
                $stepRun,
            );
        }

        if ($stepRun->second_person_user_id !== $signer->id) {
            throw new ProcedureSecondPersonException(
                ProcedureSecondPersonException::CODE_MISSING,
                ProcedureSecondPersonException::REASON_NOT_TAKER,
                $stepRun,
            );
        }

        return DB::transaction(function () use ($stepRun, $signer) {
            $stepRun->second_person_signed_at = Carbon::now();
            $stepRun->save();

            $this->recordEvent($stepRun, ProcedureRunEventType::SecondPersonSigned, $signer, [
                'signer_id' => $signer->id,
            ]);

            return $stepRun->refresh();
        });
    }

    public function revoke(ProcedureStepRun $stepRun, User $actor, string $reason): ProcedureStepRun {
        $this->assertRequiresSecondPerson($stepRun);

        return DB::transaction(function () use ($stepRun, $actor, $reason) {
            $previousSigner = $stepRun->second_person_user_id;
            $stepRun->second_person_signed_at = null;
            if ($stepRun->status === ProcedureStepRunStatus::Done) {
                $stepRun->status = ProcedureStepRunStatus::Pending;
                $stepRun->executed_by_user_id = null;
                $stepRun->executed_at = null;
            }
            $stepRun->save();

            $this->recordEvent($stepRun, ProcedureRunEventType::SecondPersonRevoked, $actor, [
                'previous_signer_id' => $previousSigner,
                'reason' => $reason,
            ]);

            return $stepRun->refresh();
        });
    }

    public function requiresSecondPerson(ProcedureStepRun $stepRun): bool {
        $def = $stepRun->stepDef;
        if ($def === null) {
            return false;
        }
        if ($def->requires_second_person) {
            return true;
        }

        return $def->step_type === ProcedureStepType::Freigabe;
    }

    private function selfExclusion(ProcedureStepRun $stepRun): bool {
        $config = $this->stepConfig($stepRun);

        return (bool) ($config['second_person_self_exclusion'] ?? true);
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

    private function assertRequiresSecondPerson(ProcedureStepRun $stepRun): void {
        if (! $this->requiresSecondPerson($stepRun)) {
            throw new ProcedureSecondPersonException(
                ProcedureSecondPersonException::CODE_MISSING,
                ProcedureSecondPersonException::REASON_NOT_REQUIRED,
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
