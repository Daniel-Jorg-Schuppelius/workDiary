<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureRunBlockTracker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procedure;

use App\Enums\Procedure\{ProcedureRunEventType, ProcedureRunStatus, ProcedureStepRunStatus};
use App\Exceptions\ProcedureStepBlockedException;
use App\Models\Platform\User;
use App\Models\Procedure\{ProcedureRun, ProcedureRunEvent, ProcedureStepRun};
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Pflegt den Laufstatus „Blocked“ (MVP-897). Gesperrt ist ein Lauf nur, wenn
 * er auf etwas außerhalb der ausführenden Person wartet: offene kritische
 * Abweichung, laufende Wartezeit, angeforderte Zweitperson. Fehlende Rolle,
 * Qualifikation oder Backup-Nachweis sind Arbeit am Schritt und sperren den
 * Lauf nicht. Jeder Wechsel schreibt runBlocked/runUnblocked ins Journal;
 * runUnblocked trägt die Dauer für den Bericht.
 */
final class ProcedureRunBlockTracker {
    use Concerns\RecordsProcedureRunEvents;

    public const REASON_CRITICAL_DEVIATION = 'criticalDeviationOpen';

    public const REASONS = [
        self::REASON_CRITICAL_DEVIATION,
        ProcedureStepBlockedException::REASON_WAIT_NOT_ELAPSED,
        ProcedureStepBlockedException::REASON_SECOND_PERSON_REQUIRED,
    ];

    public function __construct(
        private readonly ProcedureExecutionService $execution,
        private readonly DeviationRecorder $deviations,
        private readonly SecondPersonGate $secondPerson,
    ) {}

    public function refresh(ProcedureRun $run, ?User $actor = null): ProcedureRun {
        $run->refresh();
        [$reason, $waitUntil] = $run->status->isActive() ? $this->reasonFor($run) : [null, null];
        $current = $run->status === ProcedureRunStatus::Blocked ? $run->blocked_reason : null;
        if ($reason === $current && ($reason !== null || $run->blocked_at === null)) {
            return $run;
        }

        if ($run->blocked_at !== null) {
            $this->recordUnblocked($run, $actor);
        }

        if ($reason !== null) {
            $run->forceFill(['status' => ProcedureRunStatus::Blocked, 'blocked_reason' => $reason, 'blocked_at' => Carbon::now()])->save();
            $this->recordRunEvent($run, ProcedureRunEventType::RunBlocked, $actor, null, array_filter([
                'reason' => $reason,
                'wait_until' => $waitUntil?->toIso8601String(),
            ]));

            return $run;
        }

        $attributes = ['blocked_reason' => null, 'blocked_at' => null];
        if ($run->status === ProcedureRunStatus::Blocked) {
            $started = $run->stepRuns()->whereNotIn('status', [ProcedureStepRunStatus::Pending->value, ProcedureStepRunStatus::Blocked->value])->exists();
            $attributes['status'] = $started ? ProcedureRunStatus::InProgress : ProcedureRunStatus::Open;
        }
        $run->forceFill($attributes)->save();

        return $run;
    }

    /** Läufe, deren Wartezeit inzwischen abgelaufen ist, freigeben — die Zeit selbst löst kein Ereignis aus. */
    public function releaseElapsedWaits(): void {
        ProcedureRun::query()
            ->where('status', ProcedureRunStatus::Blocked->value)
            ->where('blocked_reason', ProcedureStepBlockedException::REASON_WAIT_NOT_ELAPSED)
            ->whereDoesntHave('stepRuns', fn ($q) => $q->where('status', ProcedureStepRunStatus::Blocked->value)->where('wait_until', '>', Carbon::now()))
            ->get()
            ->each(fn (ProcedureRun $run): ProcedureRun => $this->refresh($run));
    }

    /** @return array{0: ?string, 1: ?CarbonImmutable} Sperrgrund und ggf. Ende der Wartezeit */
    private function reasonFor(ProcedureRun $run): array {
        if ($this->deviations->blockingDeviationIdsFor($run) !== []) {
            return [self::REASON_CRITICAL_DEVIATION, null];
        }

        $stepRuns = $run->stepRuns()->with('stepDef')->get()->sortBy(fn (ProcedureStepRun $s): int => (int) ($s->stepDef->sort_order ?? 0));
        $byCode = [];
        foreach ($stepRuns as $stepRun) {
            if (($code = (string) ($stepRun->stepDef->code ?? '')) !== '') {
                $byCode[$code] = $stepRun;
            }
        }

        foreach ($stepRuns as $stepRun) {
            if ($stepRun->status->isFinal() || ! $this->execution->isStepApplicable($stepRun, $byCode)) {
                continue;
            }
            if ($stepRun->status === ProcedureStepRunStatus::Blocked && $stepRun->wait_until !== null && Carbon::now()->lessThan($stepRun->wait_until)) {
                return [ProcedureStepBlockedException::REASON_WAIT_NOT_ELAPSED, CarbonImmutable::parse($stepRun->wait_until)];
            }
            if ($this->awaitsSecondPerson($stepRun)) {
                return [ProcedureStepBlockedException::REASON_SECOND_PERSON_REQUIRED, null];
            }
            if ($stepRun->stepDef?->blocking) {
                break; // Nachfolger sind ohnehin noch nicht erreichbar.
            }
        }

        return [null, null];
    }

    private function awaitsSecondPerson(ProcedureStepRun $stepRun): bool {
        if (! $this->secondPerson->requiresSecondPerson($stepRun) || $stepRun->second_person_signed_at !== null) {
            return false;
        }
        if ($stepRun->second_person_user_id !== null) {
            return true;
        }
        $last = ProcedureRunEvent::query()
            ->where('procedure_step_run_id', $stepRun->id)
            ->whereIn('event_type', [ProcedureRunEventType::SecondPersonRequested->value, ProcedureRunEventType::SecondPersonRevoked->value])
            ->orderByDesc('id')
            ->value('event_type');

        return ($last instanceof ProcedureRunEventType ? $last->value : $last) === ProcedureRunEventType::SecondPersonRequested->value;
    }

    private function recordUnblocked(ProcedureRun $run, ?User $actor): void {
        $since = CarbonImmutable::parse($run->blocked_at);
        $end = CarbonImmutable::now();
        if ($run->blocked_reason === ProcedureStepBlockedException::REASON_WAIT_NOT_ELAPSED) {
            $blocked = ProcedureRunEvent::query()->where('procedure_run_id', $run->id)->where('event_type', ProcedureRunEventType::RunBlocked->value)->orderByDesc('id')->first();
            $until = data_get($blocked?->payload, 'wait_until');
            if (is_string($until) && CarbonImmutable::parse($until)->lessThan($end)) {
                $end = CarbonImmutable::parse($until);
            }
        }
        $this->recordRunEvent($run, ProcedureRunEventType::RunUnblocked, $actor, null, [
            'reason' => $run->blocked_reason,
            'blocked_at' => $since->toIso8601String(),
            'seconds' => max(0, (int) $since->diffInSeconds($end)),
        ]);
    }
}
