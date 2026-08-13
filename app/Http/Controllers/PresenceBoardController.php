<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PresenceBoardController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Enums\Vacation\VacationStatus;
use App\Models\{Organization, ScheduledShift, SickLeave, User, Vacation};
use App\Services\Attendance\EmergencyAttendanceService;
use App\Services\Flextime\WorkScheduleResolver;
use App\Services\HolidayService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Aktuelle Personal-Belegung (MVP-524) — Alltagssicht für Empfang/Zentrale:
 * wer ist im Haus, wer außer Haus, wer abwesend. Bewusst datensparsam:
 * Fehlgründe werden NIE angezeigt (neutral „abwesend"), Feature ist je
 * Organisation Opt-in (`settings.presence.board_enabled`, Default AUS).
 * Nutzt dieselbe Momentaufnahme wie die Notfallliste (MVP-518), aber ohne
 * deren sensible Detailtiefe und ohne eigene Berechtigungsstufe — sichtbar
 * für alle angemeldeten Mitglieder der Organisation.
 */
class PresenceBoardController extends Controller {
    public function index(EmergencyAttendanceService $service): View {
        /** @var User $user */
        $user = Auth::user();

        $org = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        if (! $org instanceof Organization || ! (bool) data_get($org->settings, 'presence.board_enabled', false)) {
            abort(404);
        }

        $snapshot = $service->snapshot((int) $user->organization_id);

        return view('presence.board', [
            'snapshot' => $snapshot,
            'returns' => $this->returnTimes($snapshot),
        ]);
    }

    /**
     * Planmäßige Rückkehr je Nutzer (MVP-527, Q1 „wieder im Hause um"):
     * Anwesende → geplantes Dienstende heute; Abwesende → erster Arbeitstag
     * nach dem Abwesenheitsende (Arbeitszeitmodell + Feiertage). Bewusst nur
     * Zeiten, nie Gründe.
     *
     * @param  array{present: list<array{user: User, since: ?CarbonImmutable, on_break: bool, site_id: ?int, site_name: ?string}>, present_unmapped: list<array{user: User, since: ?CarbonImmutable, on_break: bool, site_id: ?int, site_name: ?string}>, absent: list<array{user: User, reason: string}>, off_site: mixed, unaccounted: mixed, at: CarbonImmutable, is_live: bool}  $snapshot
     * @return array<int, string> user_id → Anzeigetext
     */
    private function returnTimes(array $snapshot): array {
        $returns = [];
        $today = CarbonImmutable::now(\App\Support\Tz::current())->toDateString();

        // Anwesende: geplantes Dienstende des heutigen Dienstes.
        $presentIds = [];
        foreach ([...$snapshot['present'], ...$snapshot['present_unmapped']] as $row) {
            $presentIds[] = (int) $row['user']->id;
        }
        if ($presentIds !== []) {
            ScheduledShift::query()
                ->whereIn('user_id', $presentIds)
                ->whereDate('date', $today)
                ->where('status', '!=', ScheduledShiftStatus::Cancelled->value)
                ->whereNotNull('end_time')
                ->get(['user_id', 'end_time'])
                ->each(function (ScheduledShift $shift) use (&$returns): void {
                    $returns[(int) $shift->user_id] = (string) __('bis :time', [
                        'time' => substr((string) $shift->end_time, 0, 5),
                    ]);
                });
        }

        // Abwesende: erster Arbeitstag nach dem Abwesenheitsende.
        foreach ($snapshot['absent'] as $row) {
            $userId = (int) $row['user']->id;
            $end = $this->absenceEnd($userId, $today);
            if ($end === null) {
                continue;
            }
            $back = $this->nextWorkingDay($row['user'], $end->addDay());
            if ($back !== null) {
                $returns[$userId] = (string) __('wieder ab :date', ['date' => $back->format('d.m.')]);
            }
        }

        return $returns;
    }

    /** Ende der heute laufenden Abwesenheit (Urlaub oder Krankmeldung). */
    private function absenceEnd(int $userId, string $today): ?CarbonImmutable {
        $ends = [];
        $vacation = Vacation::query()
            ->where('user_id', $userId)
            ->where('status', VacationStatus::Approved->value)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('end_date')
            ->first(['end_date']);
        if ($vacation !== null) {
            $ends[] = CarbonImmutable::parse($vacation->end_date->toDateString());
        }
        $sick = SickLeave::query()
            ->where('user_id', $userId)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('end_date')
            ->first(['end_date']);
        if ($sick !== null) {
            $ends[] = CarbonImmutable::parse($sick->end_date->toDateString());
        }

        return $ends === [] ? null : max($ends);
    }

    /** Erster Arbeitstag ab $from laut Arbeitszeitmodell + Feiertagen (max. 21 Tage Suche). */
    private function nextWorkingDay(User $user, CarbonImmutable $from): ?CarbonImmutable {
        $resolver = app(WorkScheduleResolver::class);
        $holidays = app(HolidayService::class);

        for ($i = 0; $i < 21; $i++) {
            $day = $from->addDays($i);
            try {
                $schedule = $resolver->for($user, $day);
            } catch (\Throwable) {
                // Ohne Arbeitszeitmodell: Wochentage als Arbeitstage annehmen.
                return $day->isWeekend() ? $from->next('Monday') : $day;
            }
            if ($schedule->appliesOnWeekday((int) $day->isoWeekday()) && ! $holidays->isHoliday($day)) {
                return $day;
            }
        }

        return null;
    }
}
