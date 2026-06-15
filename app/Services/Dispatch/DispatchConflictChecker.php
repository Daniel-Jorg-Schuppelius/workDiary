<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DispatchConflictChecker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Dispatch;

use App\Enums\Diary\{Mode, Status};
use App\Enums\Shift\ScheduledShiftStatus;
use App\Models\{DiaryEntry, Organization, ScheduledShift, User};
use App\Services\Compliance\{ComplianceReport, ComplianceViolation, ShiftComplianceService};
use Carbon\CarbonImmutable;

/**
 * Erkennt Disposition-Konflikte für eine geplante Auftragszuweisung
 * (Auftrag + Mitarbeiter + Zeitfenster) VOR der Terminbestätigung.
 *
 * Wiederverwendung statt Parallel-Logik:
 *  - Personal-/Arbeitszeit-/Abwesenheits-Konflikte werden über die
 *    BESTEHENDEN Compliance-Regeln ({@see ShiftComplianceService}) ermittelt.
 *    Dazu wird aus dem geplanten Einsatz eine TRANSIENTE (nicht gespeicherte)
 *    {@see ScheduledShift} gebaut und den Regeln gefüttert. So greifen
 *    OverlapRule (Schicht-Überschneidung), RestPeriodRule, MaxDailyHoursRule,
 *    MaxWeeklyHoursRule, ConsecutiveDaysRule und VacationConflictRule
 *    unverändert. QualificationMatchRule/HolidayDoubleBookRule liefern ohne
 *    shift_type_id sauber keine Verletzung (kein Crash).
 *  - Zusätzlich prüft dieser Checker die Überschneidung mit ANDEREN
 *    Auftrags-Einsätzen desselben Mitarbeiters (diary_entries) — das ist
 *    keine Compliance-Regel-Domäne (die kennt nur ScheduledShift) und
 *    dupliziert daher nichts.
 *
 * Severity-Mapping: error = harter Konflikt (blockiert die Bestätigung bzw.
 * erfordert bewusste Übersteuerung), warning = weicher Konflikt (Hinweis).
 */
final class DispatchConflictChecker {
    public function __construct(
        private readonly ShiftComplianceService $compliance = new ShiftComplianceService,
    ) {}

    /**
     * Prüft eine geplante Zuweisung. Übergeben werden der Auftrag sowie
     * optional ein abweichend zu prüfender Mitarbeiter/Zeitfenster (z. B.
     * vor dem Speichern eines noch nicht persistierten Vorschlags).
     */
    public function check(
        DiaryEntry $entry,
        ?int $userId = null,
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
    ): ComplianceReport {
        $userId ??= (int) ($entry->getAttribute('assigned_user_id') ?? 0);
        [$start, $end] = $this->resolveWindow($entry, $from, $to);

        if ($userId === 0 || $start === null || $end === null) {
            // Ohne Mitarbeiter oder Zeitfenster keine personenbezogene Prüfung.
            return new ComplianceReport([]);
        }

        /** @var Organization|null $organization */
        $organization = $entry->organization
            ?? Organization::query()->find($entry->getAttribute('organization_id'));

        $violations = [];

        // 1) Bestehende Compliance-Regeln über eine transiente ScheduledShift.
        $proxy = $this->buildProxyShift($entry, $userId, $start, $end, $organization?->id);
        foreach ($this->compliance->check($proxy, $organization)->violations as $v) {
            $violations[] = $v;
        }

        // 2) Überschneidung mit anderen Auftrags-Einsätzen desselben MA.
        foreach ($this->overlappingAssignments($entry, $userId, $start, $end) as $v) {
            $violations[] = $v;
        }

        return new ComplianceReport($violations);
    }

    /**
     * Auflösung des effektiven Zeitfensters des Einsatzes.
     *
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private function resolveWindow(
        DiaryEntry $entry,
        ?\DateTimeInterface $from,
        ?\DateTimeInterface $to,
    ): array {
        if ($from !== null) {
            $start = CarbonImmutable::instance(\DateTimeImmutable::createFromInterface($from));
            $end = $to !== null
                ? CarbonImmutable::instance(\DateTimeImmutable::createFromInterface($to))
                : $start->addHour();

            return [$start, $end];
        }

        $startAt = $entry->start_at;
        if ($startAt === null) {
            return [null, null];
        }
        $start = CarbonImmutable::parse($startAt->format('Y-m-d H:i:s'));
        $end = $entry->end_at !== null
            ? CarbonImmutable::parse($entry->end_at->format('Y-m-d H:i:s'))
            : $start->addMinutes((int) ($entry->planned_minutes ?? $entry->service_minutes ?? 60));

        if ($end->lessThanOrEqualTo($start)) {
            $end = $start->addHour();
        }

        return [$start, $end];
    }

    /** Baut eine NICHT gespeicherte ScheduledShift als Eingabe für die Regeln. */
    private function buildProxyShift(
        DiaryEntry $entry,
        int $userId,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?int $organizationId,
    ): ScheduledShift {
        $shift = new ScheduledShift;
        $shift->forceFill([
            'id' => null,
            'organization_id' => $organizationId,
            'duty_plan_id' => null,
            'user_id' => $userId,
            'shift_type_id' => null,
            'date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i:s'),
            'end_time' => $end->format('H:i:s'),
            'status' => ScheduledShiftStatus::Draft->value,
        ]);
        // date-Cast braucht Carbon-Instanz für die Rules.
        $shift->setAttribute('date', \Carbon\Carbon::parse($start->format('Y-m-d')));

        return $shift;
    }

    /**
     * Andere, terminierte Aufträge desselben Mitarbeiters, deren Zeitfenster
     * sich überschneidet. Harter Konflikt (Doppelverplanung).
     *
     * @return list<ComplianceViolation>
     */
    private function overlappingAssignments(
        DiaryEntry $entry,
        int $userId,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $others = DiaryEntry::query()
            ->where('assigned_user_id', $userId)
            ->where('mode', Mode::Fixed->value)
            ->whereNotNull('start_at')
            ->whereNotIn('status', [Status::Done->value, Status::Cancelled->value, Status::Invoiced->value, Status::AcceptedFinal->value])
            ->when($entry->getKey(), fn($q) => $q->where('id', '!=', $entry->getKey()))
            ->where('is_archived', false)
            ->whereDate('start_at', '<=', $end->toDateString())
            ->whereDate('start_at', '>=', $start->copy()->subDay()->toDateString())
            ->get();

        $violations = [];
        /** @var DiaryEntry $other */
        foreach ($others as $other) {
            $oStart = $other->start_at;
            if ($oStart === null) {
                continue;
            }
            $os = CarbonImmutable::parse($oStart->format('Y-m-d H:i:s'));
            $oe = $other->end_at !== null
                ? CarbonImmutable::parse($other->end_at->format('Y-m-d H:i:s'))
                : $os->addMinutes((int) ($other->planned_minutes ?? $other->service_minutes ?? 60));
            if ($oe->lessThanOrEqualTo($os)) {
                $oe = $os->addHour();
            }

            if ($start->lessThan($oe) && $os->lessThan($end)) {
                $violations[] = new ComplianceViolation(
                    code: 'assignment_overlap',
                    severity: ComplianceViolation::SEVERITY_ERROR,
                    message: __('Mitarbeiter ist im Zeitfenster bereits einem anderen Auftrag zugewiesen (ab :date).', [
                        'date' => $os->format('d.m.Y H:i'),
                    ]),
                    relatedShiftIds: [],
                    context: ['diary_entry_id' => (int) $other->getKey()],
                );
            }
        }

        return $violations;
    }

    /**
     * Bequemer Wrapper: harte (blockierende) Konflikte.
     *
     * @return list<ComplianceViolation>
     */
    public function blockingConflicts(ComplianceReport $report): array {
        return $report->bySeverity(ComplianceViolation::SEVERITY_ERROR);
    }

    /**
     * Bequemer Wrapper: weiche (Warn-) Konflikte.
     *
     * @return list<ComplianceViolation>
     */
    public function warnings(ComplianceReport $report): array {
        return array_values(array_filter(
            $report->violations,
            static fn(ComplianceViolation $v): bool => $v->severity !== ComplianceViolation::SEVERITY_ERROR,
        ));
    }

    /** Resolver-Helfer für Tests/Controller: zugewiesener Mitarbeiter. */
    public function assignedUser(DiaryEntry $entry): ?User {
        $id = $entry->getAttribute('assigned_user_id');
        if ($id === null) {
            return null;
        }

        /** @var User|null $user */
        $user = User::query()->find($id);

        return $user;
    }
}
