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

use App\Enums\Notification\NotificationEvent;
use App\Enums\Procedure\{ProcedureRunEventType, ProcedureStepRunStatus, ProcedureStepType};
use App\Exceptions\ProcedureSecondPersonException;
use App\Models\Platform\User;
use App\Models\Procedure\ProcedureStepRun;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Steuert das Vier-Augen-Prinzip (MVP-028). Wird sowohl als
 * Pre-Check vor `execute()` als auch als eigenstaendiges Service
 * fuer Request/Take/Sign/Revoke-Aktionen genutzt.
 */
class SecondPersonGate {
    // Zentraler Event-Schreiber + stepConfig (Vollaudit 2026-07, N38).
    use \App\Services\Procedure\Concerns\RecordsProcedureRunEvents;

    public function __construct(
        private readonly NotificationDispatcher $notifications,
    ) {}

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

    /** Fordert die zweite Person an; eine offene Anforderung wird nicht erneut gemeldet. */
    public function request(ProcedureStepRun $stepRun, User $actor): ProcedureStepRun {
        $this->assertRequiresSecondPerson($stepRun);

        if ($stepRun->second_person_requested_at !== null) {
            return $stepRun;
        }

        $stepRun->second_person_requested_at = Carbon::now();
        $stepRun->second_person_requester_user_id = $actor->id;
        $stepRun->save();

        $this->recordStepEvent($stepRun, ProcedureRunEventType::SecondPersonRequested, $actor, [
            'requested_by' => $actor->id,
        ]);
        $this->announceProgress($stepRun, $actor);
        $this->notifyRequested($stepRun, $actor);

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

        // Vor dem Erledigen steht die ausführende Person nur als Anfordernde fest.
        if (
            $this->selfExclusion($stepRun)
            && in_array($taker->id, [$stepRun->executed_by_user_id, $stepRun->second_person_requester_user_id], true)
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

            $this->recordStepEvent($stepRun, ProcedureRunEventType::SecondPersonAssigned, $taker, [
                'taker_id' => $taker->id,
            ]);
            $this->announceProgress($stepRun, $taker);

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

            $this->recordStepEvent($stepRun, ProcedureRunEventType::SecondPersonSigned, $signer, [
                'signer_id' => $signer->id,
            ]);
            $this->announceProgress($stepRun, $signer);

            return $stepRun->refresh();
        });
    }

    public function revoke(ProcedureStepRun $stepRun, User $actor, string $reason): ProcedureStepRun {
        $this->assertRequiresSecondPerson($stepRun);

        // Die Anforderung bleibt offen: eine andere Person kann übernehmen.
        return DB::transaction(function () use ($stepRun, $actor, $reason) {
            $previousSigner = $stepRun->second_person_user_id;
            $stepRun->second_person_user_id = null;
            $stepRun->second_person_signed_at = null;
            if ($stepRun->status === ProcedureStepRunStatus::Done) {
                $stepRun->status = ProcedureStepRunStatus::Pending;
                $stepRun->executed_by_user_id = null;
                $stepRun->executed_at = null;
            }
            $stepRun->save();

            $this->recordStepEvent($stepRun, ProcedureRunEventType::SecondPersonRevoked, $actor, [
                'previous_signer_id' => $previousSigner,
                'reason' => $reason,
            ]);
            $this->announceProgress($stepRun, $actor);

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

    private function notifyRequested(ProcedureStepRun $stepRun, User $actor): void {
        $run = $stepRun->run;
        if ($run === null) {
            return;
        }

        $step = (string) $stepRun->stepDef?->label;
        $this->notifications->notify(NotificationEvent::ProcedureSecondPersonRequested, $run, null, [
            'title' => (string) ($run->templateVersion?->template->name ?? $step),
            'message' => (string) __('notification.message.procedure_second_person_requested', ['step' => $step, 'name' => $actor->name]),
            'message_key' => 'notification.message.procedure_second_person_requested',
            'message_params' => ['step' => $step, 'name' => $actor->name],
            'url' => route('procedure-runs.show', $run),
        ]);
    }

    private function selfExclusion(ProcedureStepRun $stepRun): bool {
        $config = $this->stepConfig($stepRun);

        return (bool) ($config['second_person_self_exclusion'] ?? true);
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
}
