<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeRuleEngine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Surcharge;

use App\Enums\Attendance\AttendanceSource;
use App\Enums\Shift\ScheduledShiftStatus;
use App\Models\{Attendance, AttendanceTerminal, ScheduledShift, User};
use App\Models\Surcharge\{SurchargeRule, TimeRuleResult};
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Zeitregel-Engine (MVP-513, Feature 103): bewertet abgeschlossene
 * Anwesenheiten eines Users gegen die Zuschlagsregeln der Organisation —
 * unter Berücksichtigung der Regel-Bedingungen (Team/Standort/Schichttyp)
 * und des Feiertags-Rechtsraums des Einsatz-Standorts — und persistiert
 * die Einzelergebnisse als {@see TimeRuleResult} mit Berechnungs-Snapshot.
 *
 * Bewusst KEINE Parallel-Logik: die Zerlegung bleibt im
 * {@see SurchargeCalculator}; die Engine liefert Kontext, filtert Regeln
 * und persistiert. Die Aggregation je (Regel, Kalendertag) ist identisch
 * zur bisherigen Export-Logik — ohne konfigurierte Bedingungen ändert
 * sich kein Exportergebnis.
 *
 * Kontext-Auflösung je Anwesenheit:
 *  - team_ids: Teams des Users (team_user).
 *  - shift_type_id: veröffentlichte/zugewiesene Schicht am Kalendertag.
 *  - site_id + Feiertags-Provider: Terminal-Stempel tragen den
 *    Terminalnamen in `started_device` (Feature 061) → Standort des
 *    Terminals; Browser-/manuelle Stempel haben keinen Standort.
 */
class TimeRuleEngine {
    public function __construct(private readonly SurchargeCalculator $calculator) {}

    /** @var array<int, array<string, array{site_id: ?int, holiday_provider: ?string}>> */
    private array $terminalSiteCache = [];

    /**
     * Bewertet den Zeitraum eines Users, ersetzt vorhandene Ergebnisse des
     * Zeitraums und liefert die Aggregation je (Regel, Kalendertag) im
     * Format der Export-Aggregation.
     *
     * @param  Collection<int, SurchargeRule>  $rules
     * @return array<string, array{rule: SurchargeRule, date: string, minutes: int, sources: list<int>}>
     */
    public function evaluateUserPeriod(
        int $organizationId,
        int $userId,
        CarbonImmutable $start,
        CarbonImmutable $end,
        Collection $rules,
        ?int $timeExportId = null,
    ): array {
        $attendances = Attendance::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->orderBy('started_at')
            ->get(['id', 'started_at', 'ended_at', 'source', 'started_device']);

        // Ergebnisse des Zeitraums ersetzen (idempotente Neubewertung).
        TimeRuleResult::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->delete();

        if ($attendances->isEmpty() || $rules->isEmpty()) {
            return [];
        }

        $teamIds = $this->teamIdsFor($organizationId, $userId);
        $shiftTypeByDate = $this->shiftTypesByDate($organizationId, $userId, $start, $end);
        $terminalSites = $this->terminalSiteMap($organizationId);
        $tz = Tz::current();

        /** @var array<string, array{rule: SurchargeRule, date: string, minutes: int, sources: list<int>}> $acc */
        $acc = [];
        foreach ($attendances as $attendance) {
            $site = $this->resolveSite($attendance, $terminalSites);
            // In die lokale Zeitzone umrechnen VOR dem Mitternachts-Split —
            // Zuschlagsfenster und Wochentage sind lokale Begriffe (§ 3b EStG).
            $localStart = CarbonImmutable::parse((string) $attendance->started_at)->setTimezone($tz);
            $context = [
                'team_ids' => $teamIds,
                'site_id' => $site['site_id'],
                'shift_type_id' => $shiftTypeByDate[$localStart->toDateString()] ?? null,
            ];

            $applicable = $rules->filter(fn (SurchargeRule $rule): bool => $rule->matchesContext($context));
            if ($applicable->isEmpty()) {
                continue;
            }

            $shares = $this->calculator->calculate(
                $localStart,
                CarbonImmutable::parse((string) $attendance->ended_at)->setTimezone($tz),
                $applicable,
                $site['holiday_provider'],
            );

            foreach ($shares as $share) {
                TimeRuleResult::query()->create([
                    'organization_id' => $organizationId,
                    'user_id' => $userId,
                    'attendance_id' => (int) $attendance->id,
                    'surcharge_rule_id' => (int) $share->rule->id,
                    'time_export_id' => $timeExportId,
                    'date' => $share->date,
                    'minutes' => $share->minutes,
                    'wage_type_code' => $share->rule->wage_type_code ?? $share->rule->wageType(),
                    'percentage' => $share->rule->percentage,
                    'calculation_snapshot' => $this->snapshot($share->rule, $context, $site['holiday_provider']),
                ]);

                $key = $share->date . '|' . $share->rule->id;
                if (! isset($acc[$key])) {
                    $acc[$key] = [
                        'rule' => $share->rule,
                        'date' => $share->date,
                        'minutes' => 0,
                        'sources' => [],
                    ];
                }
                $acc[$key]['minutes'] += $share->minutes;
                $acc[$key]['sources'][] = (int) $attendance->id;
            }
        }

        ksort($acc);

        return $acc;
    }

    /**
     * Snapshot des angewandten Regelstands — macht das Ergebnis auch nach
     * späteren Regeländerungen nachvollziehbar (Versionierungs-Anforderung).
     *
     * @param  array{team_ids: list<int>, site_id: ?int, shift_type_id: ?int}  $context
     * @return array<string, mixed>
     */
    private function snapshot(SurchargeRule $rule, array $context, ?string $holidayProvider): array {
        return [
            'engine' => 1,
            'rule' => [
                'code' => $rule->code,
                'kind' => $rule->kind->value,
                'percentage' => (string) $rule->percentage,
                'window_start' => $rule->window_start,
                'window_end' => $rule->window_end,
                'priority' => $rule->priority,
                'valid_from' => $rule->valid_from?->toDateString(),
                'valid_until' => $rule->valid_until?->toDateString(),
                'conditions' => $rule->conditions,
            ],
            'context' => $context,
            'holiday_provider' => $holidayProvider,
        ];
    }

    /** @return list<int> */
    private function teamIdsFor(int $organizationId, int $userId): array {
        $user = User::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->find($userId);
        if (! $user instanceof User) {
            return [];
        }

        return array_values(array_map('intval', $user->teams()->pluck('teams.id')->all()));
    }

    /** @return array<string, int> Kalendertag → shift_type_id (nicht stornierte Schicht) */
    private function shiftTypesByDate(int $organizationId, int $userId, CarbonImmutable $start, CarbonImmutable $end): array {
        $out = [];
        $shifts = ScheduledShift::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('status', '!=', ScheduledShiftStatus::Cancelled->value)
            ->whereNotNull('shift_type_id')
            ->get(['date', 'shift_type_id']);
        foreach ($shifts as $shift) {
            $out[$shift->date->toDateString()] = (int) $shift->shift_type_id;
        }

        return $out;
    }

    /**
     * Terminalname → Standort + Feiertags-Provider (mehrdeutige Namen gelten
     * als nicht zuordenbar — Muster EmergencyAttendanceService).
     *
     * @return array<string, array{site_id: ?int, holiday_provider: ?string}>
     */
    private function terminalSiteMap(int $organizationId): array {
        if (isset($this->terminalSiteCache[$organizationId])) {
            return $this->terminalSiteCache[$organizationId];
        }

        $map = [];
        $terminals = AttendanceTerminal::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereNotNull('site_id')
            ->with('site:id,name,holiday_provider')
            ->get();
        foreach ($terminals as $terminal) {
            $name = (string) $terminal->name;
            if (array_key_exists($name, $map) && $map[$name]['site_id'] !== $terminal->site_id) {
                $map[$name] = ['site_id' => null, 'holiday_provider' => null];

                continue;
            }
            $map[$name] = [
                'site_id' => (int) $terminal->site_id,
                'holiday_provider' => $terminal->site?->holiday_provider,
            ];
        }

        return $this->terminalSiteCache[$organizationId] = $map;
    }

    /** @param array<string, array{site_id: ?int, holiday_provider: ?string}> $terminalSites
     * @return array{site_id: ?int, holiday_provider: ?string} */
    private function resolveSite(Attendance $attendance, array $terminalSites): array {
        if ($attendance->source === AttendanceSource::Terminal && $attendance->started_device !== null) {
            return $terminalSites[$attendance->started_device] ?? ['site_id' => null, 'holiday_provider' => null];
        }

        return ['site_id' => null, 'holiday_provider' => null];
    }
}
