<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComplianceScanService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Enums\Attendance\AttendanceStatus;
use App\Models\{Attendance, Organization, TravelLog, User, Vehicle};
use App\Services\HolidayService;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ermittelt die ArbZG-Ist-Verstöße je Mitarbeiter für einen Zeitraum — die
 * bisher inline im {@see \App\Http\Controllers\Reporting\ArbZgComplianceReportController}
 * gebaute Attendance-Aggregation, herausgelöst zur Wiederverwendung durch
 * Report (Anzeige, on-the-fly) UND Scan-Command (Persistenz). Das Verhalten
 * (Datenauswahl, Tageskennung, Zeitraumfilter) ist identisch zum bisherigen
 * Report; die reine Schwellenprüfung bleibt im {@see AttendanceComplianceChecker}.
 */
final class ComplianceScanService {
    /**
     * @return array<int, list<AttendanceComplianceFinding>>  Befunde je user_id (nur User mit ≥1 Befund im Zeitraum)
     */
    public function findingsForRange(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $checker = AttendanceComplianceChecker::forOrganization($organization);
        if (! $checker->enabled()) {
            return [];
        }

        // Org-Grenze: User hat KEINEN globalen OrganizationScope — daher expliziter Filter.
        $userIds = User::query()
            ->where('organization_id', $organization->getKey())
            ->pluck('id')
            ->map(static fn ($v): int => (int) $v)
            ->all();
        if ($userIds === []) {
            return [];
        }

        // Stempel-Spannen laden (ohne abgesagte/offene). Vorlauf 24 Wochen:
        // der §3-Sechs-Monats-Durchschnitt braucht das volle Rückfenster,
        // der Ruhezeit-Vortag ist damit mit abgedeckt. Befunde vor `from`
        // werden unten weiterhin verworfen.
        $loadFrom = $from->subDays(AttendanceComplianceChecker::AVERAGE_WINDOW_DAYS);

        /** @var Collection<int, Attendance> $attendances */
        $attendances = Attendance::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$loadFrom->toDateString(), $to->toDateString()])
            ->whereNotIn('status', [AttendanceStatus::Cancelled->value, AttendanceStatus::Open->value])
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->orderBy('started_at')
            ->get();

        $tz = Tz::current();

        /** @var array<int, array<string, list<array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int, recorded_at?: ?CarbonImmutable}>>> $spansByUserDate */
        $spansByUserDate = [];
        foreach ($attendances as $a) {
            if (! $a->started_at || ! $a->ended_at) {
                continue;
            }
            // Kalendertag in der Anzeige-Zeitzone (wie Attendance::saving()).
            $dateKey = $a->started_at->copy()->setTimezone($tz)->toDateString();
            // Wandzeit der Anzeige-Zeitzone: das Nachtfenster 23–6 (§6) muss
            // auf lokale Uhrzeiten treffen; Dauer-Differenzen bleiben identisch.
            $spansByUserDate[(int) $a->user_id][$dateKey][] = [
                'started_at' => CarbonImmutable::parse($a->started_at->toIso8601String())->setTimezone($tz),
                'ended_at' => CarbonImmutable::parse($a->ended_at->toIso8601String())->setTimezone($tz),
                'break_minutes' => $a->break_minutes_total,
                // MiLoG §17 (MVP-695): Erfassungszeitpunkt = created_at.
                'recorded_at' => $a->created_at ? CarbonImmutable::parse($a->created_at->toIso8601String())->setTimezone($tz) : null,
            ];
        }

        // Feiertage (§11): Rechtsraum der Org; Fenster reicht bis 8 Wochen
        // hinter das Scan-Ende (Ersatzruhetag-Suche für Feiertagsarbeit).
        $holidayService = app(HolidayService::class);
        $holidays = [];
        $holidayTo = $to->addDays(AttendanceComplianceChecker::HOLIDAY_REST_WINDOW_DAYS);
        for ($year = $loadFrom->year; $year <= $holidayTo->year; $year++) {
            foreach (array_keys($holidayService->forYear($year)) as $day) {
                $holidays[] = (string) $day;
            }
        }

        $fromStr = $from->toDateString();

        /** @var array<int, list<AttendanceComplianceFinding>> $result */
        $result = [];
        foreach ($spansByUserDate as $uid => $byDate) {
            $findings = $checker->checkUser($uid, $byDate, holidays: $holidays);

            // Verstöße ausserhalb des angefragten Zeitraums (Vorlauf-Fenster
            // dient nur Ruhezeit/§3-Durchschnitt) verwerfen.
            $findings = array_values(array_filter(
                $findings,
                static fn (AttendanceComplianceFinding $f): bool => $f->date >= $fromStr,
            ));
            if ($findings === []) {
                continue;
            }
            $result[$uid] = $findings;
        }

        return $result;
    }

    /**
     * Lenk-/Ruhezeit-Befunde je Fahrer (Feature 144, MVP-719): nur wenn die
     * Org die Regeln anwendet UND nur Fahrten mit Fahrzeugen, die das Flag
     * `subject_to_driving_time_rules` tragen; stornierte Originale bleiben
     * außen vor (effective). Vorlauf 21 Tage für Doppelwoche/Wochenruhezeit —
     * Befunde vor `from` werden verworfen.
     *
     * @return array<int, list<AttendanceComplianceFinding>>  Befunde je user_id (nur Fahrer mit ≥1 Befund)
     */
    public function drivingTimeFindingsForRange(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        if (! $organization->drivingTimeRulesEnabled()) {
            return [];
        }

        $vehicleIds = Vehicle::query()
            ->where('organization_id', $organization->getKey())
            ->subjectToDrivingTimeRules()
            ->pluck('id')
            ->map(static fn ($v): int => (int) $v)
            ->all();
        if ($vehicleIds === []) {
            return [];
        }

        $loadFrom = $from->subDays(DrivingTimeComplianceChecker::LOOKBACK_DAYS);
        $tz = Tz::current();

        /** @var array<int, list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable}>> $tripsByUser */
        $tripsByUser = [];
        TravelLog::query()
            ->where('organization_id', $organization->getKey())
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->whereBetween('date', [$loadFrom->toDateString(), $to->toDateString()])
            ->effective()
            ->orderBy('started_at')
            ->get(['id', 'user_id', 'started_at', 'ended_at'])
            ->each(function (TravelLog $t) use (&$tripsByUser, $tz): void {
                if (! $t->started_at || ! $t->ended_at) {
                    return;
                }
                // Wandzeit der Anzeige-Zeitzone: Kalendertag/ISO-Woche müssen lokal stimmen.
                $tripsByUser[(int) $t->user_id][] = [
                    'started_at' => CarbonImmutable::parse($t->started_at->toIso8601String())->setTimezone($tz),
                    'ended_at' => CarbonImmutable::parse($t->ended_at->toIso8601String())->setTimezone($tz),
                ];
            });

        $checker = new DrivingTimeComplianceChecker;
        $fromStr = $from->toDateString();

        /** @var array<int, list<AttendanceComplianceFinding>> $result */
        $result = [];
        foreach ($tripsByUser as $uid => $trips) {
            $findings = array_values(array_filter(
                $checker->checkUser($uid, $trips),
                static fn (AttendanceComplianceFinding $f): bool => $f->date >= $fromStr,
            ));
            if ($findings !== []) {
                $result[$uid] = $findings;
            }
        }

        return $result;
    }
}
