<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StaffingSuggester.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Enums\Shift\{AvailabilityKind, ScheduledShiftStatus, ShiftPreference};
use App\Models\{AvailabilityWindow, DesiredShift, Organization, ScheduledShift, ShiftType, User};
use App\Services\Compliance\{ComplianceViolation, ShiftComplianceService};
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Schlägt für eine offene/unterbesetzte Schicht (Datum + Schichttyp) Kandidaten
 * vor, gerankt nach Qualifikation, Verfügbarkeit/Wunsch und Compliance.
 *
 * KEINE parallele Compliance-Logik: für jeden Kandidaten wird eine transiente
 * {@see ScheduledShift} gebaut und durch den {@see ShiftComplianceService}
 * geprüft. Kandidaten mit ERROR-Verstoß (Überschneidung, Ruhezeit,
 * Tageshöchstarbeitszeit, Urlaub) werden ausgeschlossen. Vorschläge sind reine
 * Assistenz — der Planer entscheidet und es findet vor der Zuweisung ein
 * Re-Check statt.
 */
class StaffingSuggester {
    public function __construct(private readonly ShiftComplianceService $compliance) {}

    /**
     * @return list<array{
     *     user_id:int,
     *     user_sqid:string,
     *     name:string,
     *     score:int,
     *     reasons:list<string>,
     *     warnings:list<string>,
     *     qualified:bool,
     *     available:bool,
     *     preferred:bool
     * }>
     */
    public function suggest(
        \DateTimeInterface $date,
        ShiftType $shiftType,
        int $organizationId,
        ?string $startTime = null,
        ?string $endTime = null,
        int $limit = 5,
    ): array {
        $day = Carbon::parse($date->format('Y-m-d'));
        $start = $startTime ?? $shiftType->default_start_time;
        $end = $endTime ?? $shiftType->default_end_time;

        /** @var list<int> $requiredQualificationIds */
        $requiredQualificationIds = array_values(array_map('intval', $shiftType->qualifications()->pluck('qualifications.id')->all()));

        $candidates = $this->candidateUsers($organizationId, $day, $shiftType->id);
        if ($candidates->isEmpty()) {
            return [];
        }

        /** @var list<int> $candidateIds */
        $candidateIds = array_values(array_map('intval', $candidates->pluck('id')->all()));
        $availability = $this->loadAvailability($candidateIds, $day);
        $desired = $this->loadDesired($candidateIds, $day, $shiftType->id);
        $hoursByUser = $this->plannedHours($candidateIds, $day);
        $organization = Organization::query()->find($organizationId);

        $results = [];
        foreach ($candidates as $user) {
            $report = $this->compliance->check(
                $this->buildProxyShift($organizationId, $shiftType->id, $user->id, $day, $start, $end),
                $organization,
            );

            // ERROR-Verstöße disqualifizieren (Überschneidung/Ruhezeit/Urlaub …).
            if ($report->hasErrors()) {
                continue;
            }

            $qualified = $this->isQualified($user, $requiredQualificationIds);
            $avail = $availability->get($user->id);
            $pref = $desired->get($user->id);

            // Explizit als nicht verfügbar markiert → ausschließen.
            if ($avail === AvailabilityKind::Unavailable) {
                continue;
            }
            // Explizite Abneigung (avoid) → ausschließen.
            if ($pref === ShiftPreference::Avoid) {
                continue;
            }

            $score = 0;
            $reasons = [];

            if ($qualified) {
                $score += 40;
                $reasons[] = (string) __('schedule.suggest.reason_qualified');
            } elseif ($requiredQualificationIds !== []) {
                // Pflicht-Qualifikation fehlt: stark abwerten, aber nicht hart raus
                // (Planer entscheidet; QualificationMatchRule erzeugt nur WARNING).
                $score -= 30;
            }

            $available = false;
            if ($avail === AvailabilityKind::Preferred) {
                $score += 25;
                $available = true;
                $reasons[] = (string) __('schedule.suggest.reason_preferred_window');
            } elseif ($avail === AvailabilityKind::Available) {
                $score += 15;
                $available = true;
                $reasons[] = (string) __('schedule.suggest.reason_available');
            }

            $preferred = false;
            if ($pref === ShiftPreference::Want) {
                $score += 30;
                $preferred = true;
                $reasons[] = (string) __('schedule.suggest.reason_wished');
            }

            // Fairness: weniger geplante Stunden → leichter Bonus.
            $hours = $hoursByUser[$user->id] ?? 0.0;
            $score += (int) max(0, 10 - (int) round($hours));

            $warnings = array_map(
                static fn(ComplianceViolation $v): string => $v->message,
                $report->bySeverity(ComplianceViolation::SEVERITY_WARNING),
            );

            $results[] = [
                'user_id' => (int) $user->id,
                'user_sqid' => (string) $user->sqid,
                'name' => (string) $user->name,
                'score' => $score,
                'reasons' => $reasons,
                'warnings' => $warnings,
                'qualified' => $qualified,
                'available' => $available,
                'preferred' => $preferred,
            ];
        }

        usort($results, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($results, 0, max(1, $limit));
    }

    /**
     * Kandidaten = Mitarbeitende der Organisation, die an dem Tag noch keine
     * Schicht dieses Typs haben.
     *
     * @return Collection<int, User>
     */
    private function candidateUsers(int $organizationId, Carbon $day, ?int $shiftTypeId): Collection {
        $alreadyAssigned = ScheduledShift::query()
            ->where('organization_id', $organizationId)
            ->whereDate('date', $day->toDateString())
            ->where('shift_type_id', $shiftTypeId)
            ->where('status', '!=', ScheduledShiftStatus::Cancelled->value)
            ->pluck('user_id')
            ->all();

        return User::query()
            ->where('organization_id', $organizationId)
            ->when($alreadyAssigned !== [], fn($q) => $q->whereNotIn('id', $alreadyAssigned))
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  list<int>  $userIds
     * @return Collection<int, AvailabilityKind> user_id → effektive Art (preferred > available > unavailable)
     */
    private function loadAvailability(array $userIds, Carbon $day): Collection {
        if ($userIds === []) {
            return collect();
        }
        $windows = AvailabilityWindow::query()
            ->whereIn('user_id', $userIds)
            ->forDate($day)
            ->get();

        $out = collect();
        foreach ($windows as $window) {
            $current = $out->get($window->user_id);
            $out->put($window->user_id, $this->strongerKind($current, $window->kind));
        }

        return $out;
    }

    private function strongerKind(?AvailabilityKind $current, AvailabilityKind $incoming): AvailabilityKind {
        // Unavailable hat Vorrang (Sperre), dann preferred, dann available.
        if ($current === AvailabilityKind::Unavailable || $incoming === AvailabilityKind::Unavailable) {
            return AvailabilityKind::Unavailable;
        }
        if ($current === AvailabilityKind::Preferred || $incoming === AvailabilityKind::Preferred) {
            return AvailabilityKind::Preferred;
        }

        return AvailabilityKind::Available;
    }

    /**
     * @param  list<int>  $userIds
     * @return Collection<int, ShiftPreference> user_id → Wunsch (avoid gewinnt über want)
     */
    private function loadDesired(array $userIds, Carbon $day, ?int $shiftTypeId): Collection {
        if ($userIds === []) {
            return collect();
        }
        $rows = DesiredShift::query()
            ->whereIn('user_id', $userIds)
            ->forDate($day)
            ->where(function ($q) use ($shiftTypeId): void {
                $q->whereNull('shift_type_id');
                if ($shiftTypeId !== null) {
                    $q->orWhere('shift_type_id', $shiftTypeId);
                }
            })
            ->get();

        $out = collect();
        foreach ($rows as $row) {
            $current = $out->get($row->user_id);
            if ($current === ShiftPreference::Avoid || $row->preference === ShiftPreference::Avoid) {
                $out->put($row->user_id, ShiftPreference::Avoid);

                continue;
            }
            $out->put($row->user_id, ShiftPreference::Want);
        }

        return $out;
    }

    /**
     * Bereits in derselben ISO-Woche geplante Stunden je Kandidat (Fairness).
     *
     * @param  list<int>  $userIds
     * @return array<int, float>
     */
    private function plannedHours(array $userIds, Carbon $day): array {
        if ($userIds === []) {
            return [];
        }
        $from = $day->copy()->startOfWeek();
        $to = $day->copy()->endOfWeek();

        $shifts = ScheduledShift::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('status', '!=', ScheduledShiftStatus::Cancelled->value)
            ->with('shiftType')
            ->get();

        $out = [];
        foreach ($shifts as $shift) {
            $start = $shift->resolvedStartTime();
            $end = $shift->resolvedEndTime();
            $hours = 8.0;
            if ($start !== null && $end !== null) {
                $s = Carbon::parse($start);
                $e = Carbon::parse($end);
                if ($e->lessThanOrEqualTo($s)) {
                    $e->addDay();
                }
                $hours = $s->diffInMinutes($e) / 60;
            }
            $out[$shift->user_id] = ($out[$shift->user_id] ?? 0.0) + $hours;
        }

        return $out;
    }

    /**
     * @param  list<int>  $requiredQualificationIds
     */
    private function isQualified(User $user, array $requiredQualificationIds): bool {
        if ($requiredQualificationIds === []) {
            return true;
        }
        $userQualificationIds = $user->qualifications()->pluck('qualifications.id')->all();

        return array_diff($requiredQualificationIds, $userQualificationIds) === [];
    }

    /**
     * Transiente Schicht für die Compliance-Prüfung (id=null, date als Carbon).
     */
    private function buildProxyShift(int $organizationId, ?int $shiftTypeId, int $userId, Carbon $day, ?string $start, ?string $end): ScheduledShift {
        $shift = new ScheduledShift;
        $shift->forceFill([
            'id' => null,
            'organization_id' => $organizationId,
            'duty_plan_id' => null,
            'user_id' => $userId,
            'shift_type_id' => $shiftTypeId,
            'date' => $day->toDateString(),
            'start_time' => $start,
            'end_time' => $end,
            'status' => ScheduledShiftStatus::Draft->value,
        ]);
        // ResolvesShiftTiming::resolveInterval erwartet ein Carbon-Datum.
        $shift->setAttribute('date', $day->copy());

        return $shift;
    }
}
