<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningTimeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Attendance\{AttendanceSource, AttendanceStatus};
use App\Enums\Learning\LearningTimePolicy;
use App\Models\{Attendance, User};
use App\Models\Learning\{LearningEnrollment, LearningTimeSession, LearningUnit};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lernzeit (Feature 149, MVP-749) — einzige Schreibstelle für das
 * Sitzungsjournal und den daraus entstehenden Arbeitszeitnachweis.
 *
 * Die drei Regeln, die hier und nirgendwo sonst durchgesetzt werden:
 *
 *  1. **Pflichtkurse starten nicht außerhalb der Arbeitszeit.** § 12 Abs. 1
 *     ArbSchG verlangt Unterweisung „während ihrer Arbeitszeit" — die
 *     Software verhindert den Verstoß, statt ihn hinterher zu vergüten.
 *  2. **Innerhalb der Arbeitszeit entsteht keine zweite Buchung.** Die Zeit
 *     ist über die Anwesenheit bereits erfasst; alles andere wäre
 *     Doppelzählung.
 *  3. **Außerhalb entsteht eine echte Anwesenheitsspanne** (Quelle
 *     `learning`). Damit greifen die vorhandenen ArbZG-Prüfungen
 *     (Ruhezeit, Höchstarbeitszeit, Nachtarbeit) ohne zweiten Guard —
 *     `ComplianceScanService` liest genau diese Tabelle.
 */
class LearningTimeService {
    /**
     * Nach so langer Stille gilt die Sitzung als verlassen. Ohne diese
     * Grenze zählte ein offener Tab, den niemand benutzt, als gearbeitete
     * Zeit — eine falsche Angabe in den Zeitkonten, nicht bloß eine
     * ungenaue Statistik.
     */
    public const IDLE_CUTOFF_MINUTES = 15;

    public function __construct(
        private readonly LearningWorkTimeClassifier $classifier,
    ) {}

    /**
     * Lebenszeichen des Players. Setzt den Zeitstempel, bis zu dem die
     * Sitzung nachweislich benutzt wurde.
     */
    public function heartbeat(LearningTimeSession $session, ?Carbon $now = null): LearningTimeSession {
        if ($session->ended_at !== null) {
            return $session;
        }

        $session->forceFill(['last_heartbeat_at' => $now ?? Carbon::now()])->save();

        return $session;
    }

    /**
     * Zeitpunkt, bis zu dem die Sitzung als benutzt gilt.
     *
     * Zwei Fälle, die man nicht gleich behandeln darf:
     *
     *  - **Ausdrücklich beendet** (jemand drückt „Stopp"): das ist selbst ein
     *    Anwesenheitsnachweis, also gilt „jetzt". Nur wenn Lebenszeichen
     *    vorliegen und lange ausblieben, wird auf das letzte gekappt — die
     *    Stille dazwischen ist keine Lernzeit.
     *  - **Liegengeblieben** (Browser zu, Kehraus): hier gibt es kein Signal
     *    vom Ende. Dann zählt nur, was belegt ist — das letzte Lebenszeichen,
     *    sonst gar nichts.
     */
    public function effectiveEnd(LearningTimeSession $session, Carbon $now, bool $abandoned = false): Carbon {
        $start = $session->started_at ?? $now;
        $last = $session->last_heartbeat_at;

        if ($abandoned) {
            return $last?->copy() ?? $start->copy();
        }

        if ($last === null) {
            return $now->copy();
        }

        return $last->copy()->lessThan($now->copy()->subMinutes(self::IDLE_CUTOFF_MINUTES))
            ? $last->copy()
            : $now->copy();
    }

    /**
     * Lernsitzung beginnen. Läuft bereits eine, wird sie zurückgegeben —
     * doppeltes Öffnen erzeugt keine zweite Zeitspur.
     */
    public function start(LearningEnrollment $enrollment, ?LearningUnit $unit = null, ?Carbon $now = null): LearningTimeSession {
        $user = $enrollment->user;
        $now ??= Carbon::now();

        if ($user === null) {
            // Externe Lernende stehen in keinem Arbeitsverhältnis — ihre
            // Zeit ist Teilnahmenachweis, nie Arbeitszeit.
            return $this->openSession($enrollment, $unit, null, $now);
        }

        $policy = $enrollment->course->time_policy ?? LearningTimePolicy::WorkTimeRequired;
        $classification = $this->classifier->classify($user, $now, $now);

        if (! $policy->allowsStartOutsideWorkTime() && $classification !== LearningWorkTimeClassifier::INSIDE) {
            throw ValidationException::withMessages([
                'time_policy' => (string) __('learning.errors.start_outside_work_time'),
            ]);
        }

        return $this->openSession($enrollment, $unit, $user, $now);
    }

    /**
     * Sitzung beenden, einordnen und — nur außerhalb der Arbeitszeit — als
     * Anwesenheitsspanne nachweisen.
     */
    public function stop(LearningTimeSession $session, ?int $activeSeconds = null, ?Carbon $now = null, bool $abandoned = false): LearningTimeSession {
        if ($session->ended_at !== null) {
            return $session;
        }

        $now ??= Carbon::now();
        $start = $session->started_at ?? $now;
        // Gebucht wird bis zum letzten Lebenszeichen, nicht bis „jetzt".
        $end = $this->effectiveEnd($session, $now, $abandoned);

        if ($end->lessThan($start)) {
            $end = $start->copy();
        }

        return DB::transaction(function () use ($session, $start, $end, $activeSeconds): LearningTimeSession {
            $elapsed = max(0, $start->diffInSeconds($end, false));
            $active = $activeSeconds !== null ? max(0, min($activeSeconds, $elapsed)) : $elapsed;

            $user = $session->user;
            $classification = $user !== null
                ? $this->classifier->classify($user, $start, $end)
                : LearningWorkTimeClassifier::UNKNOWN;

            $session->fill([
                'ended_at' => $end,
                'active_seconds' => $active,
                'classification' => $classification,
            ]);

            $session->approval_status = $this->approvalStatusFor($session, $classification);

            if ($user !== null && $this->shouldRecordWorkTime($session, $classification)) {
                $session->attendance_id = $this->recordAttendance($session, $user, $start, $end)->id;
            }

            $session->save();

            return $session->refresh();
        });
    }

    /** Offene Sitzung einer Einschreibung (höchstens eine). */
    public function openSessionFor(LearningEnrollment $enrollment): ?LearningTimeSession {
        return LearningTimeSession::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->whereNull('ended_at')
            ->latest('id')
            ->first();
    }

    /**
     * Summen für die Auswertung: Lernzeit getrennt nach innerhalb und
     * außerhalb der Arbeitszeit — genau die Zahl, die eine
     * Betriebsvereinbarung nach § 98 BetrVG braucht.
     *
     * @return array{inside: int, outside: int}
     */
    public function secondsByClassification(LearningEnrollment $enrollment): array {
        $rows = LearningTimeSession::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->whereNotNull('ended_at')
            ->get(['classification', 'active_seconds']);

        $inside = 0;
        $outside = 0;
        foreach ($rows as $row) {
            if ($row->classification === LearningWorkTimeClassifier::INSIDE) {
                $inside += (int) $row->active_seconds;
            } else {
                $outside += (int) $row->active_seconds;
            }
        }

        return ['inside' => $inside, 'outside' => $outside];
    }

    private function openSession(LearningEnrollment $enrollment, ?LearningUnit $unit, ?User $user, Carbon $now): LearningTimeSession {
        $open = $this->openSessionFor($enrollment);

        if ($open !== null) {
            return $open;
        }

        return LearningTimeSession::query()->create([
            'organization_id' => $enrollment->organization_id,
            'learning_enrollment_id' => $enrollment->id,
            'learning_unit_id' => $unit?->id,
            'user_id' => $user?->id,
            'started_at' => $now,
            'source' => 'web',
        ]);
    }

    /** Zählt diese Sitzung als (noch nicht erfasste) Arbeitszeit? */
    private function shouldRecordWorkTime(LearningTimeSession $session, string $classification): bool {
        if (! $this->classifier->createsWorkTime($classification)) {
            return false;
        }

        // Eine Spanne von null Minuten ist kein Nachweis, sondern Rauschen
        // in den Zeitkonten.
        if ((int) $session->active_seconds <= 0) {
            return false;
        }

        $policy = $session->enrollment->course->time_policy ?? LearningTimePolicy::WorkTimeRequired;

        // Freiwillige, unbezahlte Angebote erzeugen keine Arbeitszeit — die
        // Sitzung bleibt trotzdem im Journal.
        if (! $policy->countsOutsideWorkTime()) {
            return false;
        }

        // „Freigabe nötig": erst die Zusage macht daraus Arbeitszeit. Vorher
        // zu buchen und später zurückzunehmen wäre ein Eingriff in die
        // Zeitkonten für etwas, das noch niemand entschieden hat.
        return $policy !== LearningTimePolicy::ApprovalRequired;
    }

    /**
     * Braucht die Sitzung eine Freigabe?
     *
     * Nur außerhalb der Arbeitszeit und nur bei der entsprechenden
     * Zeitpolitik — innerhalb ist die Zeit ohnehin erfasst.
     */
    private function approvalStatusFor(LearningTimeSession $session, string $classification): ?string {
        $policy = $session->enrollment->course->time_policy ?? LearningTimePolicy::WorkTimeRequired;

        if ($policy !== LearningTimePolicy::ApprovalRequired) {
            return null;
        }

        return $this->classifier->createsWorkTime($classification)
            ? LearningTimeSession::APPROVAL_PENDING
            : null;
    }

    /**
     * Freigabe erteilen: **erst jetzt** entsteht die Anwesenheitsspanne.
     */
    public function approve(LearningTimeSession $session, User $actor, ?string $note = null, ?Carbon $now = null): LearningTimeSession {
        if ($session->approval_status !== LearningTimeSession::APPROVAL_PENDING) {
            throw ValidationException::withMessages([
                'approval' => (string) __('learning.errors.approval_decided'),
            ]);
        }

        $now ??= Carbon::now();
        $user = $session->user;

        return DB::transaction(function () use ($session, $actor, $note, $now, $user): LearningTimeSession {
            if ($user !== null && $session->attendance_id === null) {
                $start = $session->started_at ?? $now;
                $end = $session->ended_at ?? $now;
                $session->attendance_id = $this->recordAttendance($session, $user, $start, $end)->id;
            }

            $session->forceFill([
                'approval_status' => LearningTimeSession::APPROVAL_APPROVED,
                'approved_by_user_id' => $actor->id,
                'approved_at' => $now,
                'approval_note' => $note,
            ])->save();

            return $session->refresh();
        });
    }

    /**
     * Freigabe versagen. Die Sitzung bleibt im Journal — gelöscht wird
     * nichts, sonst wäre die Ablehnung nicht nachvollziehbar.
     */
    public function reject(LearningTimeSession $session, User $actor, string $reason, ?Carbon $now = null): LearningTimeSession {
        if ($session->approval_status !== LearningTimeSession::APPROVAL_PENDING) {
            throw ValidationException::withMessages([
                'approval' => (string) __('learning.errors.approval_decided'),
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => (string) __('learning.errors.approval_reason_required'),
            ]);
        }

        $session->forceFill([
            'approval_status' => LearningTimeSession::APPROVAL_REJECTED,
            'approved_by_user_id' => $actor->id,
            'approved_at' => $now ?? Carbon::now(),
            'approval_note' => $reason,
        ])->save();

        return $session->refresh();
    }

    /**
     * Liegengebliebene Sitzungen schließen (Browser zu, Gerät aus).
     *
     * Ohne diesen Kehraus liefe eine Sitzung ewig weiter und würde beim
     * nächsten Öffnen als riesige Spanne gebucht.
     *
     * @return int Zahl der geschlossenen Sitzungen
     */
    public function closeStaleSessions(?Carbon $now = null): int {
        $now ??= Carbon::now();
        $cutoff = $now->copy()->subMinutes(self::IDLE_CUTOFF_MINUTES);

        $sessions = LearningTimeSession::query()
            ->whereNull('ended_at')
            ->where(function ($query) use ($cutoff): void {
                $query->where('last_heartbeat_at', '<', $cutoff)
                    ->orWhere(function ($q) use ($cutoff): void {
                        $q->whereNull('last_heartbeat_at')->where('started_at', '<', $cutoff);
                    });
            })
            ->get();

        foreach ($sessions as $session) {
            // Liegengeblieben: gebucht wird nur, was Lebenszeichen belegen.
            $this->stop($session, null, $now, abandoned: true);
        }

        return $sessions->count();
    }

    /**
     * Anwesenheitsspanne mit Quelle `learning`. Bewusst KEIN Stempelvorgang
     * über den AttendanceClockService: es ist ein nachgelagerter Nachweis
     * für einen abgeschlossenen Zeitraum, kein Kommen/Gehen.
     */
    private function recordAttendance(LearningTimeSession $session, User $user, Carbon $start, Carbon $end): Attendance {
        return Attendance::query()->create([
            'organization_id' => $session->organization_id,
            'user_id' => $user->id,
            'started_at' => $start,
            'ended_at' => $end,
            'date' => $start->toDateString(),
            'duration_minutes' => (int) ceil(max(0, $start->diffInSeconds($end, false)) / 60),
            'source' => AttendanceSource::Learning->value,
            'status' => AttendanceStatus::Closed->value,
        ]);
    }
}
