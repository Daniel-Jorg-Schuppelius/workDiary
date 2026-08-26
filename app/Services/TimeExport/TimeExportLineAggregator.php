<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeExportLineAggregator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\TimeExport;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Attendance, OnCallShift, SickLeave, TimeEntry, TimeExport, TimeExportLine, User, Vacation};
use App\Models\Scopes\OrganizationScope;
use App\Models\Surcharge\SurchargeRule;
use App\Services\Flextime\FlexCalculator;
use App\Services\HolidayService;
use App\Services\Surcharge\TimeRuleEngine;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;

/**
 * Lohn-Aggregation des Zeitexports (Vollscan 2026-08, B13: aus dem
 * TimeExportService herausgelöst): baut aus Attendances, Abwesenheiten,
 * Bereitschaften, Reisezeiten, Zuschlagsregeln und externen Positionen die
 * TimeExportLines je User × Lohnart. Der Service behält Ablauf, Preflight
 * und Persistenz des Exports.
 *
 * Aggregation im MVP:
 *   - work.normal aus Attendance.duration_minutes (Stunden, 4 Nachkommastellen)
 *   - Erweiterungen (Nacht/Sonn/Feiertag/Urlaub/Krank/Bereitschaft/Reise)
 *     sind im ../WorkDiary-Architecture/zeit-export.md vorgesehen und greifen via gleicher Pipeline.
 */
class TimeExportLineAggregator {
    public function __construct(
        private readonly HolidayService $holidays,
        private readonly FlexCalculator $flex,
        // MVP-513: Zerlegung + Kontext + Ergebnis-Persistenz laufen über die
        // Engine (die intern den SurchargeCalculator nutzt).
        private readonly TimeRuleEngine $timeRuleEngine,
    ) {}

    /**
     * Aggregiert alle Exportzeilen des Laufs (idempotent, bestehende Zeilen
     * werden verworfen) und liefert die Anzahl erzeugter Zeilen.
     *
     * @param  array<int,int>  $userIds
     */
    public function aggregate(TimeExport $export, array $userIds): int {
        $start = CarbonImmutable::create($export->period_year, $export->period_month, 1);
        if (! $start instanceof CarbonImmutable) {
            throw new TimeExportException('invalidPeriod', __('Ungültige Periode :y-:m.', ['y' => $export->period_year, 'm' => $export->period_month]));
        }
        $start = $start->startOfMonth();
        $end = $start->endOfMonth();

        // Bestehende Zeilen verwerfen (idempotente Re-Aggregation).
        $export->lines()->delete();

        // Zuschlagsregeln der Organisation einmalig laden (Feature 005).
        $surchargeRules = SurchargeRule::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $export->organization_id)
            ->where('active', true)
            ->orderBy('id')
            ->get();

        // Kostenstellen-Regeln (Rang 35): Benutzer > Team > Org-Default.
        $costCenters = new CostCenterResolver((int) $export->organization_id);

        $rows = 0;
        foreach ($userIds as $uid) {
            $costCenter = $costCenters->forUser((int) $uid);

            // Vollaudit 2026-07 (H6/M4): Abwesenheits-, Bereitschafts-, Reise-
            // und Überstunden-Lohnarten — unabhängig von Attendance (ein voller
            // Krankheitsmonat hat 0 Arbeitsminuten, gehört aber in die Übergabe).
            $rows += $this->aggregateAbsenceLines($export, (int) $uid, $start, $end, $costCenter);
            $rows += $this->aggregateOnCallAndTravelLines($export, (int) $uid, $start, $end, $costCenter);
            $rows += $this->aggregateNonIntervalSurchargeLines($export, (int) $uid, $start, $end, $surchargeRules, $costCenter);
            // Feature-103-Delta: externe vergütungsrelevante Positionen
            // (Essensgeld, Kilometer, Zulagen) je Lohnart aggregieren.
            $rows += $this->aggregateExternalWageItems($export, (int) $uid, $start, $end, $costCenter);

            $minutes = (int) Attendance::query()
                ->where('user_id', $uid)
                ->whereBetween('date', DateRange::days($start, $end))
                ->sum('duration_minutes');

            if ($minutes <= 0) {
                continue;
            }

            $hours = round($minutes / 60, 4);

            TimeExportLine::query()->create([
                'time_export_id' => $export->id,
                'user_id' => $uid,
                'wage_type' => 'work.normal',
                'cost_center' => $costCenter,
                'quantity' => $hours,
                'unit' => 'h',
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'note' => null,
                'source_refs' => ['attendance_minutes' => $minutes],
            ]);

            $rows++;

            $rows += $this->aggregateSurchargeLines($export, $uid, $start, $end, $surchargeRules, $costCenter);
        }

        return $rows;
    }

    /**
     * Urlaubs- und Krankheitstage als Lohnarten-Zeilen (Vollaudit 2026-07, H6):
     * genehmigte Urlaube bzw. nicht stornierte Krankmeldungen, geclippt auf den
     * Zeitraum, gezählt in anspruchsrelevanten Werktagen (Mo–Fr ohne Feiertage —
     * gleiche Semantik wie VacationBalanceService, MVP-413).
     */
    private function aggregateAbsenceLines(TimeExport $export, int $uid, CarbonImmutable $start, CarbonImmutable $end, ?string $costCenter): int {
        $rows = 0;

        $vacationDays = 0.0;
        $vacations = Vacation::query()
            ->where('user_id', $uid)
            ->approved()
            ->where('start_date', '<=', DateRange::day($end))
            ->where('end_date', '>=', DateRange::day($start))
            ->get(['id', 'start_date', 'end_date']);
        foreach ($vacations as $vacation) {
            $vacationDays += $this->workingDaysInPeriod(CarbonImmutable::parse((string) $vacation->start_date), CarbonImmutable::parse((string) $vacation->end_date), $start, $end);
        }
        if ($vacationDays > 0) {
            TimeExportLine::query()->create([
                'time_export_id' => $export->id,
                'user_id' => $uid,
                'wage_type' => 'absence.vacation',
                'cost_center' => $costCenter,
                'quantity' => $vacationDays,
                'unit' => 'd',
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'note' => null,
                'source_refs' => ['vacation_ids' => $vacations->pluck('id')->all()],
            ]);
            $rows++;
        }

        $sickDays = 0.0;
        $sickLeaves = SickLeave::query()
            ->where('user_id', $uid)
            ->whereNull('cancelled_at')
            ->where('start_date', '<=', DateRange::day($end))
            ->where('end_date', '>=', DateRange::day($start))
            ->get(['id', 'start_date', 'end_date']);
        foreach ($sickLeaves as $sickLeave) {
            $sickDays += $this->workingDaysInPeriod(CarbonImmutable::parse((string) $sickLeave->start_date), CarbonImmutable::parse((string) $sickLeave->end_date), $start, $end);
        }
        if ($sickDays > 0) {
            TimeExportLine::query()->create([
                'time_export_id' => $export->id,
                'user_id' => $uid,
                'wage_type' => 'absence.sick',
                'cost_center' => $costCenter,
                'quantity' => $sickDays,
                'unit' => 'd',
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'note' => null,
                'source_refs' => ['sick_leave_ids' => $sickLeaves->pluck('id')->all()],
            ]);
            $rows++;
        }

        return $rows;
    }

    /**
     * Bereitschafts- und Reisezeit-Stunden (Vollaudit 2026-07, H6):
     * work.oncall aus OnCallShift-Intervallen (auf den Zeitraum geclippt),
     * travel.time aus TimeEntries mit kind=travel.
     */
    private function aggregateOnCallAndTravelLines(TimeExport $export, int $uid, CarbonImmutable $start, CarbonImmutable $end, ?string $costCenter): int {
        $rows = 0;

        $oncall = $this->onCallMinutes($uid, $start, $end);
        if ($oncall['minutes'] > 0) {
            TimeExportLine::query()->create([
                'time_export_id' => $export->id,
                'user_id' => $uid,
                'wage_type' => 'work.oncall',
                'cost_center' => $costCenter,
                'quantity' => round($oncall['minutes'] / 60, 4),
                'unit' => 'h',
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'note' => null,
                'source_refs' => ['on_call_shift_ids' => $oncall['ids']],
            ]);
            $rows++;
        }

        $travel = TimeEntry::query()
            ->where('user_id', $uid)
            ->where('kind', TimeEntryKind::Travel)
            ->whereBetween('date', DateRange::days($start, $end))
            ->get(['id', 'minutes']);
        $travelMinutes = (int) $travel->sum('minutes');
        if ($travelMinutes > 0) {
            TimeExportLine::query()->create([
                'time_export_id' => $export->id,
                'user_id' => $uid,
                'wage_type' => 'travel.time',
                'cost_center' => $costCenter,
                'quantity' => round($travelMinutes / 60, 4),
                'unit' => 'h',
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'note' => null,
                'source_refs' => ['time_entry_ids' => $travel->pluck('id')->all()],
            ]);
            $rows++;
        }

        return $rows;
    }

    /**
     * Externe vergütungsrelevante Positionen (Feature-103-Delta, Q1 „Import
     * von Bewegungsdaten"): Essensgeld, Kilometer, Zulagen — je Lohnart und
     * Einheit im Zeitraum aggregiert; die Lohnarten-Codes kommen aus dem
     * Import und werden wie alle Zeilen über WageTypeMapping abgebildet.
     */
    private function aggregateExternalWageItems(TimeExport $export, int $uid, CarbonImmutable $start, CarbonImmutable $end, ?string $costCenter): int {
        $items = \App\Models\ExternalWageItem::query()
            ->where('user_id', $uid)
            ->whereBetween('item_date', DateRange::days($start, $end))
            ->get(['id', 'wage_type_code', 'quantity', 'unit']);
        if ($items->isEmpty()) {
            return 0;
        }

        $rows = 0;
        foreach ($items->groupBy(fn ($i): string => $i->wage_type_code . '|' . $i->unit) as $group) {
            $first = $group->first();
            if ($first === null) {
                continue;
            }
            TimeExportLine::query()->create([
                'time_export_id' => $export->id,
                'user_id' => $uid,
                'wage_type' => (string) $first->wage_type_code,
                'cost_center' => $costCenter,
                'quantity' => round((float) $group->sum('quantity'), 4),
                'unit' => (string) $first->unit,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'note' => null,
                'source_refs' => ['external_wage_item_ids' => $group->pluck('id')->all()],
            ]);
            $rows++;
        }

        return $rows;
    }

    /**
     * Zuschlagszeilen der Nicht-Intervall-Arten (Vollaudit 2026-07, M4):
     * oncall (OnCallShift-Minuten), standby (TimeEntries kind=standby) und
     * overtime (Monatssoll-Überschreitung laut FlexCalculator). Bewusst ohne
     * Stacking mit Nacht/Wochenende/Feiertag — eigene Quellzeiten (Doku im
     * SurchargeKind-Enum).
     *
     * @param  \Illuminate\Support\Collection<int, SurchargeRule>  $rules
     */
    private function aggregateNonIntervalSurchargeLines(
        TimeExport $export,
        int $uid,
        CarbonImmutable $start,
        CarbonImmutable $end,
        \Illuminate\Support\Collection $rules,
        ?string $costCenter,
    ): int {
        $rows = 0;
        foreach ($rules as $rule) {
            if ($rule->kind->isIntervalBased() || ! $rule->active) {
                continue;
            }

            [$minutes, $refs] = match ($rule->kind) {
                \App\Enums\Surcharge\SurchargeKind::OnCall => (function () use ($uid, $start, $end): array {
                    $oncall = $this->onCallMinutes($uid, $start, $end);

                    return [$oncall['minutes'], ['on_call_shift_ids' => $oncall['ids']]];
                })(),
                \App\Enums\Surcharge\SurchargeKind::Standby => (function () use ($uid, $start, $end): array {
                    $entries = TimeEntry::query()
                        ->where('user_id', $uid)
                        ->where('kind', TimeEntryKind::Standby)
                        ->whereBetween('date', DateRange::days($start, $end))
                        ->get(['id', 'minutes']);

                    return [(int) $entries->sum('minutes'), ['time_entry_ids' => $entries->pluck('id')->all()]];
                })(),
                \App\Enums\Surcharge\SurchargeKind::Overtime => (function () use ($uid, $export): array {
                    $user = User::query()->withoutGlobalScopes()->find($uid);
                    if (! $user instanceof User) {
                        return [0, []];
                    }
                    $balance = $this->flex->monthlyBalance($user, (int) $export->period_year, (int) $export->period_month);

                    return [max(0, (int) $balance['actual'] - (int) $balance['target']), ['flex' => ['target' => $balance['target'], 'actual' => $balance['actual']]]];
                })(),
                default => [0, []],
            };

            if ($minutes <= 0) {
                continue;
            }

            TimeExportLine::query()->create([
                'time_export_id' => $export->id,
                'user_id' => $uid,
                'wage_type' => $rule->wageType(),
                'cost_center' => $costCenter,
                'quantity' => round($minutes / 60, 4),
                'unit' => 'h',
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'note' => $rule->label ?? null,
                'source_refs' => [...$refs, 'rule_id' => $rule->id, 'percentage' => (float) $rule->percentage],
            ]);
            $rows++;
        }

        return $rows;
    }

    /**
     * Bereitschaftsminuten aus OnCallShifts, geclippt auf [start, end].
     *
     * @return array{minutes: int, ids: list<int>}
     */
    private function onCallMinutes(int $uid, CarbonImmutable $start, CarbonImmutable $end): array {
        $periodStart = $start->startOfDay();
        $periodEnd = $end->endOfDay();

        $shifts = OnCallShift::query()
            ->where('user_id', $uid)
            ->where('is_archived', false)
            ->where('start_at', '<=', $periodEnd)
            ->where('end_at', '>=', $periodStart)
            ->get(['id', 'start_at', 'end_at']);

        $minutes = 0;
        foreach ($shifts as $shift) {
            $from = CarbonImmutable::parse((string) $shift->start_at)->max($periodStart);
            $to = CarbonImmutable::parse((string) $shift->end_at)->min($periodEnd);
            if ($to->greaterThan($from)) {
                $minutes += (int) $from->diffInMinutes($to);
            }
        }

        /** @var list<int> $ids */
        $ids = $shifts->pluck('id')->map(fn($id): int => (int) $id)->values()->all();

        return ['minutes' => $minutes, 'ids' => $ids];
    }

    /**
     * Anspruchsrelevante Werktage (Mo–Fr ohne Feiertage) eines Zeitraums,
     * geclippt auf den Exportmonat — Zählweise wie VacationBalanceService.
     */
    private function workingDaysInPeriod(CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): float {
        $from = $rangeStart->max($periodStart)->startOfDay();
        $to = $rangeEnd->min($periodEnd)->startOfDay();

        return (float) $this->holidays->workingDaysBetween($from, $to);
    }

    /**
     * Zuschlagszeilen je User × Kalendertag × Regel (Feature 005, additiv).
     *
     * Grundlage sind die Attendance-Intervalle (started_at→ended_at) des
     * Monats; der {@see \App\Services\Surcharge\SurchargeCalculator} zerlegt
     * sie in zuschlagsfähige Segmente (Stacking: höchster Prozentsatz gewinnt,
     * kein Addieren). Hinweis (MVP): Pausen sind zeitlich nicht verortet und
     * werden daher im Zuschlagsfenster nicht abgezogen — gerechnet wird auf
     * dem Brutto-Intervall der Anwesenheit.
     *
     * @param  \Illuminate\Support\Collection<int, SurchargeRule>  $rules
     */
    private function aggregateSurchargeLines(
        TimeExport $export,
        int $uid,
        CarbonImmutable $start,
        CarbonImmutable $end,
        \Illuminate\Support\Collection $rules,
        ?string $costCenter = null,
    ): int {
        if ($rules->isEmpty()) {
            return 0;
        }

        // MVP-513 (Feature 103): Zerlegung + Kontext (Team/Standort/Schichttyp,
        // Feiertags-Region des Einsatzorts) laufen über die TimeRuleEngine, die
        // je Zeitdatensatz ein TimeRuleResult mit Berechnungs-Snapshot
        // persistiert. Die Aggregation je (Regel, Kalendertag) ist identisch
        // zur bisherigen Logik — ohne Bedingungen ändert sich kein Ergebnis.
        $acc = $this->timeRuleEngine->evaluateUserPeriod(
            (int) $export->organization_id,
            $uid,
            $start,
            $end,
            $rules,
            (int) $export->id,
        );

        $rows = 0;
        foreach ($acc as $row) {
            if ($row['minutes'] <= 0) {
                continue;
            }

            $base = [
                'time_export_id' => $export->id,
                'user_id' => $uid,
                'wage_type' => $row['rule']->wageType(),
                'cost_center' => $costCenter,
                'quantity' => round($row['minutes'] / 60, 4),
                'unit' => 'h',
                'period_start' => $row['date'],
                'period_end' => $row['date'],
                'source_refs' => ['attendance_ids' => array_values(array_unique($row['sources']))],
                'surcharge_rule_id' => $row['rule']->id,
            ];

            // Steuerfrei/-pflichtig-Split (Rang 36): über der steuerfreien
            // Obergrenze in zwei Zeilen mit getrennten Lohnarten aufgeteilt
            // (gleiche Stunden; €-Deckel bleibt in der externen Lohnrechnung).
            $split = $row['rule']->taxSplit();
            if ($split === null) {
                TimeExportLine::query()->create($base + [
                    'note' => $row['rule']->label,
                    'wage_type_code' => $row['rule']->wage_type_code,
                    'percentage' => $row['rule']->percentage,
                ]);

                $rows++;

                continue;
            }

            TimeExportLine::query()->create($base + [
                'note' => $row['rule']->label . ' — ' . __('steuerfrei'),
                'wage_type_code' => $row['rule']->wage_type_code,
                'percentage' => $split['free_pct'],
            ]);
            TimeExportLine::query()->create($base + [
                'note' => $row['rule']->label . ' — ' . __('steuerpflichtig'),
                'wage_type_code' => $row['rule']->taxable_wage_type_code ?? $row['rule']->wage_type_code,
                'percentage' => $split['taxable_pct'],
            ]);

            $rows += 2;
        }

        return $rows;
    }

}
