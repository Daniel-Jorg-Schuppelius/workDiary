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
use App\Models\{Attendance, Organization, User};
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

        // Stempel-Spannen laden (ohne abgesagte/offene). Ruhezeit braucht den
        // Vortag → Fenster um einen Tag nach vorn erweitern.
        /** @var Collection<int, Attendance> $attendances */
        $attendances = Attendance::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$from->copy()->subDay()->toDateString(), $to->toDateString()])
            ->whereNotIn('status', [AttendanceStatus::Cancelled->value, AttendanceStatus::Open->value])
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->orderBy('started_at')
            ->get();

        $tz = Tz::current();

        /** @var array<int, array<string, list<array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int}>>> $spansByUserDate */
        $spansByUserDate = [];
        foreach ($attendances as $a) {
            if (! $a->started_at || ! $a->ended_at) {
                continue;
            }
            // Kalendertag in der Anzeige-Zeitzone (wie Attendance::saving()).
            $dateKey = $a->started_at->copy()->setTimezone($tz)->toDateString();
            $spansByUserDate[(int) $a->user_id][$dateKey][] = [
                'started_at' => CarbonImmutable::parse($a->started_at->toIso8601String()),
                'ended_at' => CarbonImmutable::parse($a->ended_at->toIso8601String()),
                'break_minutes' => $a->break_minutes_total,
            ];
        }

        $fromStr = $from->toDateString();

        /** @var array<int, list<AttendanceComplianceFinding>> $result */
        $result = [];
        foreach ($spansByUserDate as $uid => $byDate) {
            $findings = $checker->checkUser($uid, $byDate);

            // Verstöße ausserhalb des angefragten Zeitraums (Tag −1 nur für die
            // Ruhezeit-Vorlauf) verwerfen.
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
}
