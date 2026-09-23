<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubEligibilityService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\{ClubAttendanceSheetStatus, ClubAttendanceStatus, ClubCountingBasis, ClubProofKind};
use App\Models\Club\{ClubAttendanceRecord, ClubGradeRequirement, ClubMember, ClubMemberProof};
use App\Support\Query\DateRange;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Database\Eloquent\Builder;

/**
 * Zulassungsprüfung (MVP-846): bewertet die UND-Liste eines Zielgrads für ein
 * Mitglied zum Prüfungsstichtag. Alter und Wartefrist beziehen sich auf den
 * Stichtag, Anwesenheit zählt nur bestätigt und vor Prüfungsbeginn; der
 * Zählzeitraum ist fachlich und unabhängig vom Header-Filter. Disziplinen
 * bleiben getrennt.
 */
class ClubEligibilityService {
    public function __construct(
        private readonly ClubGradingService $grading,
    ) {}

    public function evaluate(ClubMember $member, ClubGradeRequirement $requirement, CarbonInterface $examAt, bool $approved = false): ClubEligibilityReport {
        $requirement->loadMissing(['version.system', 'grade', 'previousGrade']);
        $version = $requirement->version;
        $system = $version?->system;
        if ($version === null || $system === null) {
            throw new \RuntimeException('Voraussetzung ohne Regelversion.');
        }

        $examAt = CarbonImmutable::instance($examAt);
        $examDay = $examAt->startOfDay();
        $current = $this->grading->currentGrade($member, $system, $examDay);
        $items = [];

        // Vorgrad: benannter gültiger Grad derselben Ordnung.
        if ($requirement->previous_grade_id !== null) {
            $items[] = $this->item('previous_grade', $current !== null && $current->club_grade_id === $requirement->previous_grade_id,
                (string) ($requirement->previousGrade->name ?? ''), (string) ($current?->grade->name ?? __('club.grading.label.no_grade')));
        }

        // Zählzeitraum.
        $from = match ($requirement->counting_basis) {
            ClubCountingBasis::SincePreviousGrade => $current !== null ? CarbonImmutable::instance($current->obtained_on)->addDay()->startOfDay() : CarbonImmutable::instance($member->joined_on)->startOfDay(),
            ClubCountingBasis::SinceMembership => CarbonImmutable::instance($member->joined_on)->startOfDay(),
            ClubCountingBasis::WindowMonths => $examDay->subMonthsNoOverflow(max(1, (int) $requirement->window_months)),
        };

        // Anwesenheit: bestätigt, anrechenbar, passende Disziplin/Terminart/Gruppe, vor Prüfungsbeginn.
        $records = $this->countableRecords($member, $requirement, $system->discipline, $from, $examAt)->get(['club_attendance_records.id', 'club_attendance_records.minutes']);
        $minutes = (int) $records->sum('minutes');
        $sessionMin = $requirement->min_minutes_per_session ?? 1;
        $sessions = $records->filter(fn($record): bool => (int) $record->minutes >= $sessionMin)->count();
        if ($version->accepts_external_credits) {
            foreach ($this->externalProofs($member, $system->discipline, $from, $examDay) as $proof) {
                $minutes += (int) $proof->minutes;
                $sessions += (int) $proof->sessions;
            }
        }
        if ($requirement->min_minutes !== null) {
            $items[] = $this->item('minutes', $minutes >= $requirement->min_minutes, ClubEligibilityReport::hoursMinutes($requirement->min_minutes), ClubEligibilityReport::hoursMinutes($minutes));
        }
        if ($requirement->min_sessions !== null) {
            $items[] = $this->item('sessions', $sessions >= $requirement->min_sessions, (string) $requirement->min_sessions, (string) $sessions);
        }

        // Wartezeit in Kalendermonaten seit Vorgrad, ohne Monatsüberlauf.
        if ($requirement->wait_months !== null) {
            $due = $current !== null ? CarbonImmutable::instance($current->obtained_on)->addMonthsNoOverflow($requirement->wait_months)->startOfDay() : null;
            $items[] = $this->item('wait', $due !== null && $examDay->greaterThanOrEqualTo($due),
                (string) trans_choice('club.grading.months', $requirement->wait_months, ['count' => $requirement->wait_months]),
                $due !== null ? (string) __('club.grading.label.wait_until', ['date' => $due->format('d.m.Y')]) : (string) __('club.grading.label.no_grade'));
        }

        // Mindestalter am Prüfungstag.
        if ($requirement->min_age !== null) {
            $age = $member->ageOn($examDay);
            $items[] = $this->item('age', $age !== null && $age >= $requirement->min_age, (string) $requirement->min_age, $age !== null ? (string) $age : (string) __('club.label.without_birth_date'));
        }

        // Pflichtlehrgang: bestätigter Nachweis, am Stichtag gültig.
        if ($requirement->required_proof_label !== null) {
            $proof = ClubMemberProof::query()
                ->where('club_member_id', $member->id)
                ->where('kind', ClubProofKind::Course->value)
                ->whereRaw('LOWER(label) = ?', [mb_strtolower($requirement->required_proof_label)])
                ->where('obtained_on', '<', DateRange::dayAfter($examDay))
                ->where(fn(Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', DateRange::day($examDay)))
                ->orderByDesc('obtained_on')
                ->first();
            $items[] = $this->item('proof', $proof !== null, $requirement->required_proof_label, $proof !== null ? $proof->obtained_on->format('d.m.Y') : (string) __('club.grading.label.missing'));
        }

        // Fachliche Freigabe der Leitung (MVP-847 setzt sie am Kandidaten).
        if ($requirement->requires_approval) {
            $items[] = $this->item('approval', $approved, (string) __('club.grading.label.required'), $approved ? (string) __('club.grading.label.granted') : (string) __('club.grading.label.missing'));
        }

        $met = array_reduce($items, static fn(bool $carry, array $item): bool => $carry && $item['met'], true);

        return new ClubEligibilityReport(
            met: $met,
            items: $items,
            examDay: $examDay,
            countingFrom: $from,
            minutes: $minutes,
            sessions: $sessions,
            requiredMinutes: $requirement->min_minutes,
            requiredSessions: $requirement->min_sessions,
            unitMinutes: $version->unit_minutes,
            usedRecordIds: array_values(array_map('intval', $records->pluck('id')->all())),
        );
    }

    /**
     * Bestätigte, anrechenbare Nachweise im Zählzeitraum: Terminart freigegeben,
     * Disziplin des Termins oder seiner Zielgruppen gleich der Ordnung, optional
     * nur bestimmte Gruppen, Termin nicht abgesagt und vor Prüfungsbeginn.
     *
     * @return Builder<ClubAttendanceRecord>
     */
    public function countableRecords(ClubMember $member, ClubGradeRequirement $requirement, string $discipline, CarbonInterface $from, CarbonInterface $examAt): Builder {
        $groupIds = array_values(array_filter(array_map('intval', (array) ($requirement->counted_group_ids ?? []))));

        return ClubAttendanceRecord::query()
            ->join('club_attendance_sheets', 'club_attendance_sheets.id', '=', 'club_attendance_records.club_attendance_sheet_id')
            ->join('events', 'events.id', '=', 'club_attendance_records.event_id')
            ->join('club_event_details', 'club_event_details.event_id', '=', 'events.id')
            ->where('club_attendance_records.club_member_id', $member->id)
            ->where('club_attendance_sheets.status', ClubAttendanceSheetStatus::Confirmed->value)
            ->whereIn('club_attendance_records.status', [ClubAttendanceStatus::Present->value, ClubAttendanceStatus::Partial->value])
            ->where(fn(Builder $q) => $q->whereNull('club_attendance_records.overlap_event_id')->orWhereNotNull('club_attendance_records.overlap_cleared_at'))
            ->whereNull('events.cancelled_at')
            ->where('events.started_at', '>=', CarbonImmutable::instance($from)->utc())
            ->where('events.started_at', '<', CarbonImmutable::instance($examAt)->utc())
            ->whereIn('club_event_details.kind', $requirement->countedKinds())
            ->where(fn(Builder $q) => $q
                ->where('club_event_details.discipline', $discipline)
                ->orWhere(fn(Builder $inner) => $inner
                    ->whereNull('club_event_details.discipline')
                    ->whereExists(fn($sub) => $sub->selectRaw('1')->from('club_event_groups')
                        ->join('club_groups', 'club_groups.id', '=', 'club_event_groups.club_group_id')
                        ->whereColumn('club_event_groups.event_id', 'events.id')
                        ->where('club_groups.discipline', $discipline))))
            ->when($groupIds !== [], fn(Builder $q) => $q->whereExists(fn($sub) => $sub->selectRaw('1')->from('club_event_groups')
                ->whereColumn('club_event_groups.event_id', 'events.id')
                ->whereIn('club_event_groups.club_group_id', $groupIds)))
            ->orderBy('events.started_at');
    }

    /** @return \Illuminate\Support\Collection<int, ClubMemberProof> */
    private function externalProofs(ClubMember $member, string $discipline, CarbonInterface $from, CarbonInterface $examDay) {
        return ClubMemberProof::query()
            ->where('club_member_id', $member->id)
            ->where('kind', ClubProofKind::ExternalTraining->value)
            ->where(fn(Builder $q) => $q->whereNull('discipline')->orWhere('discipline', $discipline))
            ->where('obtained_on', '>=', DateRange::day($from))
            ->where('obtained_on', '<', DateRange::day($examDay))
            ->get();
    }

    /** @return array{key: string, met: bool, required: string, actual: string} */
    private function item(string $key, bool $met, string $required, string $actual): array {
        return ['key' => $key, 'met' => $met, 'required' => $required, 'actual' => $actual];
    }
}
