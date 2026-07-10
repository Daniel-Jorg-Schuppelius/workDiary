<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DayCloseService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\TimeApproval;

use App\Enums\TimeApproval\{DayClosureStatus, DayCorrectionStatus};
use App\Enums\TimeEntry\{TimeEntryActivityType, TimeEntryKind};
use App\Models\{Attendance, DayClosure, DayCorrectionRequest, TimeEntry, User};
use App\Services\Flextime\FlexCalculator;
use App\Services\Timekeeping\BreakRuleEvaluator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * Statusmaschine für Tagesabschlüsse (MVP-015, ../WorkDiary-Architecture/tagesabschluss.md §3/§5).
 *
 * Übergänge: open → closed → correction → open; `locked` ist abgeleitet
 * (Monat submitted|approved|locked, MVP-016). Jede Methode kapselt die
 * Vorbedingungen (Status, Zukunftstag, Monats-Sperre, ⛔-Checks,
 * Begründungslänge), den Statuswechsel in einer DB-Transaktion und das
 * Audit-Event (§6) über den bestehenden audit()-Mechanismus.
 *
 * Fehler werden als {@see DayCloseWorkflowException} mit reasonCode
 * geworfen, damit Controller fachlich sprechende Antworten erzeugen.
 */
class DayCloseService {
    public const REASON_MIN_LENGTH = 20;

    public function __construct(
        private readonly FlexCalculator $flex,
        private readonly BreakRuleEvaluator $breakRules,
        private readonly MonthClosureService $monthClosures,
    ) {}

    /** Validator mit den konfigurierten gesetzlichen Pausenregeln. */
    public function makeValidator(): DayClosureValidator {
        return new DayClosureValidator(array_values($this->breakRules->rules()));
    }

    /**
     * Holt den Tagesabschluss für (User, Tag) oder legt ihn beim ersten
     * Öffnen an (idempotent; Audit `dayClose.opened` genau 1×/Tag).
     */
    public function getOrCreate(User $user, CarbonImmutable $day): DayClosure {
        $existing = $this->find($user, $day);
        if ($existing instanceof DayClosure) {
            return $existing;
        }

        /** @var DayClosure $closure */
        $closure = DayClosure::query()->create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'day' => $day->toDateString(),
            'status' => DayClosureStatus::Open,
        ]);

        $closure->audit('dayClose.opened', ['day' => $day->toDateString()]);

        return $closure;
    }

    public function find(User $user, CarbonImmutable $day): ?DayClosure {
        /** @var DayClosure|null $closure */
        $closure = DayClosure::query()
            ->where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->whereDate('day', $day->toDateString())
            ->first();

        return $closure;
    }

    /**
     * Tag liegt in einem fachlich gesperrten Monat (MVP-016:
     * submitted|approved|locked) → Anzeige-Status `locked` (§3).
     */
    public function isMonthLocked(User $user, CarbonImmutable $day): bool {
        return $this->monthClosures->isPeriodLockedForUser($user, $day);
    }

    /** Effektiver Anzeige-Status inkl. abgeleitetem `locked` (§3). */
    public function effectiveStatus(DayClosure $closure, bool $monthLocked): DayClosureStatus {
        return $monthLocked ? DayClosureStatus::Locked : $closure->status;
    }

    /**
     * Sammelt alle Anzeigedaten der Tagesabschluss-Seite (§2): Stempel,
     * Buchungen, Checks (§4) und Bilanz (§2.5) inkl. Monats-Saldo.
     *
     * @return array{
     *     attendances: Collection<int, Attendance>,
     *     entries: Collection<int, TimeEntry>,
     *     issues: list<array{code:string, severity:string, meta:array<string, int|string>}>,
     *     hasBlocking: bool,
     *     aggregates: array{target:int, gross:int, breaks:int, net:int, booked:int,
     *         required_break:int, diff:int, day_balance:int, month_balance:int},
     *     monthLocked: bool
     * }
     */
    public function context(User $user, CarbonImmutable $day): array {
        $attendances = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $day->toDateString())
            ->orderBy('started_at')
            ->get();

        $entries = TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $day->toDateString())
            ->with(['project', 'activityCategory'])
            ->orderBy('id')
            ->get();

        $attendanceData = $this->attendanceData($attendances);
        $entryData = $this->entryData($entries);
        $target = $this->flex->targetMinutes($user, $day);

        $validator = $this->makeValidator();
        $issues = $validator->validate($attendanceData, $entryData, $target);
        $agg = $validator->aggregate($attendanceData, $entryData);

        return [
            'attendances' => $attendances,
            'entries' => $entries,
            'issues' => $issues,
            'hasBlocking' => $validator->hasBlocking($issues),
            'aggregates' => [
                'target' => $target,
                'gross' => $agg['gross'],
                'breaks' => $agg['breaks'],
                'net' => $agg['net'],
                'booked' => $agg['booked'],
                'required_break' => $this->breakRules->requiredMinutes($agg['gross']),
                'diff' => $agg['net'] - $agg['booked'],
                'day_balance' => $agg['net'] - $target,
                'month_balance' => $this->monthBalanceToDate($user, $day),
            ],
            'monthLocked' => $this->isMonthLocked($user, $day),
        ];
    }

    /**
     * day.save — speichert keine eigenen Daten (Buchungen laufen über das
     * bestehende Buchungs-Modal), schreibt aber das Audit
     * `dayClose.entrySaved` (§6, kein Status-Wechsel).
     */
    public function save(DayClosure $closure, ?User $actor = null): DayClosure {
        $closure->audit('dayClose.entrySaved', [
            'day' => $closure->day->toDateString(),
            'actor_user_id' => $this->resolveActorId($actor),
        ]);

        return $closure;
    }

    /**
     * day.close: open → closed. Vorbedingungen: kein Zukunftstag, Monat
     * nicht gesperrt, keine ⛔-Warnung (§2.6/§4).
     */
    public function close(DayClosure $closure, ?User $actor = null): DayClosure {
        $this->assertStatus($closure, [DayClosureStatus::Open]);

        $day = CarbonImmutable::instance($closure->day);
        if ($day->startOfDay()->greaterThan(CarbonImmutable::now()->endOfDay())) {
            throw new DayCloseWorkflowException(
                'futureDay',
                __('day-close.errors.future_day'),
                ['day' => $day->toDateString()],
            );
        }

        $owner = $this->ownerOf($closure);
        $this->assertMonthOpen($owner, $day);

        $context = $this->context($owner, $day);
        if ($context['hasBlocking']) {
            throw new DayCloseWorkflowException(
                'blockingWarnings',
                __('day-close.errors.blocking_warnings'),
                ['issues' => $context['issues']],
            );
        }

        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($closure, $actorId): DayClosure {
            $closure = $this->lockAndAssert($closure, [DayClosureStatus::Open]);
            $closure->fill([
                'status' => DayClosureStatus::Closed,
                'closed_at' => CarbonImmutable::now(),
                'closed_by_user_id' => $actorId,
                'attendance_locked' => false,
            ])->save();

            $closure->audit('dayClose.closed', [
                'day' => $closure->day->toDateString(),
                'actor_user_id' => $actorId,
            ]);

            return $closure->refresh();
        });
    }

    /**
     * §5 Schritt 2/3: closed → correction. Pflicht-Begründung ≥ 20 Zeichen,
     * erzeugt einen day_correction_requests-Datensatz und das Audit
     * `dayClose.correctionRequested`. Tage in gesperrten Monaten (§3
     * `locked`) sind ausgenommen — dort zuerst Monats-Reopen (MVP-016).
     */
    public function requestCorrection(DayClosure $closure, string $reason, ?User $actor = null): DayCorrectionRequest {
        $this->assertStatus($closure, [DayClosureStatus::Closed]);
        $this->assertReason($reason);

        $owner = $this->ownerOf($closure);
        $this->assertMonthOpen($owner, CarbonImmutable::instance($closure->day));

        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($closure, $reason, $actorId): DayCorrectionRequest {
            /** @var DayCorrectionRequest $request */
            $request = DayCorrectionRequest::query()->create([
                'organization_id' => $closure->organization_id,
                'day_closure_id' => $closure->id,
                'requested_by_user_id' => $actorId ?? $closure->user_id,
                'reason' => $reason,
                'status' => DayCorrectionStatus::Pending,
            ]);

            $closure->fill(['status' => DayClosureStatus::Correction])->save();

            $closure->audit('dayClose.correctionRequested', [
                'day' => $closure->day->toDateString(),
                'request_id' => $request->id,
                'reason' => $reason,
            ]);

            return $request;
        });
    }

    /**
     * §5 Schritt 4/5 (Freigabe): correction → open. Anwesenheits-Stempel
     * bleiben gesperrt (attendance_locked) bis zum erneuten close — nur
     * Buchungen sind änderbar.
     */
    public function approveCorrection(DayCorrectionRequest $request, ?User $actor = null, ?string $note = null): DayCorrectionRequest {
        $this->assertRequestPending($request);

        $closure = $this->closureOf($request);
        $this->assertStatus($closure, [DayClosureStatus::Correction]);

        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($request, $closure, $actorId, $note): DayCorrectionRequest {
            $request->fill([
                'status' => DayCorrectionStatus::Approved,
                'decided_at' => CarbonImmutable::now(),
                'decided_by_user_id' => $actorId,
                'decision_note' => $note,
            ])->save();

            $closure->fill([
                'status' => DayClosureStatus::Open,
                'closed_at' => null,
                'closed_by_user_id' => null,
                'attendance_locked' => true,
            ])->save();

            $closure->audit('dayClose.correctionApproved', [
                'day' => $closure->day->toDateString(),
                'request_id' => $request->id,
                'note' => $note,
            ]);

            return $request->refresh();
        });
    }

    /** §5 Schritt 4 (Ablehnung): correction → closed (Antrag rejected). */
    public function rejectCorrection(DayCorrectionRequest $request, ?User $actor = null, ?string $note = null): DayCorrectionRequest {
        $this->assertRequestPending($request);

        $closure = $this->closureOf($request);
        $this->assertStatus($closure, [DayClosureStatus::Correction]);

        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($request, $closure, $actorId, $note): DayCorrectionRequest {
            $request->fill([
                'status' => DayCorrectionStatus::Rejected,
                'decided_at' => CarbonImmutable::now(),
                'decided_by_user_id' => $actorId,
                'decision_note' => $note,
            ])->save();

            $closure->fill(['status' => DayClosureStatus::Closed])->save();

            $closure->audit('dayClose.correctionRejected', [
                'day' => $closure->day->toDateString(),
                'request_id' => $request->id,
                'note' => $note,
            ]);

            return $request->refresh();
        });
    }

    /**
     * day.reopen (Admin, ohne Antrag): closed → open mit Pflicht-Begründung
     * (§2.6/§6). Anders als die Korrektur-Freigabe hebt das Admin-Reopen
     * auch die Stempel-Sperre auf.
     */
    public function reopen(DayClosure $closure, string $reason, ?User $actor = null): DayClosure {
        $this->assertStatus($closure, [DayClosureStatus::Closed]);
        $this->assertReason($reason);

        $owner = $this->ownerOf($closure);
        $this->assertMonthOpen($owner, CarbonImmutable::instance($closure->day));

        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($closure, $reason, $actorId): DayClosure {
            $closure = $this->lockAndAssert($closure, [DayClosureStatus::Closed]);
            $closure->fill([
                'status' => DayClosureStatus::Open,
                'closed_at' => null,
                'closed_by_user_id' => null,
                'reopened_at' => CarbonImmutable::now(),
                'reopened_by_user_id' => $actorId,
                'reopen_reason' => $reason,
                'attendance_locked' => false,
            ])->save();

            $closure->audit('dayClose.reopened', [
                'day' => $closure->day->toDateString(),
                'reason' => $reason,
            ]);

            return $closure->refresh();
        });
    }

    /**
     * Stempel-Sperre für eine Anwesenheit (konsumiert von
     * {@see \App\Policies\AttendancePolicy}): gesperrt, sobald der Tag
     * nicht mehr offen ist, nach Korrektur-Freigabe (attendance_locked)
     * oder wenn der Monat freigegeben/gesperrt ist.
     */
    public function attendanceEditLocked(Attendance $attendance): bool {
        $owner = $attendance->user;
        if (! $owner instanceof User || $attendance->date === null) {
            return false;
        }
        $day = CarbonImmutable::instance($attendance->date);

        $closure = $this->find($owner, $day);
        if ($closure instanceof DayClosure && (! $closure->isOpen() || $closure->attendance_locked)) {
            return true;
        }

        return $this->isMonthLocked($owner, $day);
    }

    // ── intern ─────────────────────────────────────────────────────────

    /**
     * @param  Collection<int, Attendance>  $attendances
     * @return list<array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int}>
     */
    private function attendanceData(Collection $attendances): array {
        $data = [];
        foreach ($attendances as $a) {
            if ($a->started_at === null) {
                continue;
            }
            $data[] = [
                'started_at' => CarbonImmutable::instance($a->started_at),
                'ended_at' => $a->ended_at !== null ? CarbonImmutable::instance($a->ended_at) : null,
                'break_minutes' => (int) $a->break_minutes_total,
            ];
        }

        return $data;
    }

    /**
     * @param  Collection<int, TimeEntry>  $entries
     * @return list<array{minutes:int, billable:bool, has_comment:bool, counted:bool}>
     */
    private function entryData(Collection $entries): array {
        $kinds = $this->countedKinds();
        $excluded = $this->excludedActivityTypes();

        $data = [];
        foreach ($entries as $entry) {
            $data[] = [
                'minutes' => (int) $entry->minutes,
                'billable' => (bool) $entry->billable,
                'has_comment' => filled($entry->description),
                'counted' => in_array($entry->kind->value, $kinds, true)
                    && ! in_array($entry->activity_type->value, $excluded, true),
            ];
        }

        return $data;
    }

    /** @return array<int, string> */
    private function countedKinds(): array {
        $kinds = (array) config('timesheet.flex.count_kinds', [TimeEntryKind::Work->value, TimeEntryKind::Travel->value]);

        return array_values(array_map('strval', $kinds));
    }

    /** @return array<int, string> */
    private function excludedActivityTypes(): array {
        $excl = (array) config('timesheet.flex.exclude_activity_types', [TimeEntryActivityType::Break_->value, TimeEntryActivityType::Absence->value]);

        return array_values(array_map('strval', $excl));
    }

    /** Gleitzeit-Saldo vom Monatsersten bis einschließlich des Tages (§2.5). */
    private function monthBalanceToDate(User $user, CarbonImmutable $day): int {
        $balance = 0;
        for ($d = $day->startOfMonth(); $d->lte($day); $d = $d->addDay()) {
            $b = $this->flex->dailyBalance($user, $d);
            $balance += (int) $b['balance'];
        }

        return $balance;
    }

    /** @param  list<DayClosureStatus>  $allowed */
    private function assertStatus(DayClosure $closure, array $allowed): void {
        if (! in_array($closure->status, $allowed, true)) {
            throw new DayCloseWorkflowException(
                'illegalTransition',
                __('day-close.errors.illegal_day_status', ['status' => $closure->status->value]),
                ['from' => $closure->status->value, 'allowed' => array_map(static fn(DayClosureStatus $s) => $s->value, $allowed)],
            );
        }
    }

    /**
     * Sperrt die Tagesabschluss-Zeile in der Transaktion und prüft den Status
     * erneut gegen den frischen Wert (verhindert doppelte Übergänge/Events bei
     * parallelen close()/reopen()-Aufrufen).
     *
     * @param  list<DayClosureStatus>  $allowed
     */
    private function lockAndAssert(DayClosure $closure, array $allowed): DayClosure {
        /** @var DayClosure $fresh */
        $fresh = DayClosure::query()->whereKey($closure->getKey())->lockForUpdate()->firstOrFail();
        $this->assertStatus($fresh, $allowed);

        return $fresh;
    }

    private function assertRequestPending(DayCorrectionRequest $request): void {
        if ($request->status !== DayCorrectionStatus::Pending) {
            throw new DayCloseWorkflowException(
                'illegalTransition',
                __('day-close.errors.illegal_request_status', ['status' => $request->status->value]),
                ['from' => $request->status->value],
            );
        }
    }

    private function assertReason(string $reason): void {
        if (mb_strlen(trim($reason)) < self::REASON_MIN_LENGTH) {
            throw new DayCloseWorkflowException(
                'reasonTooShort',
                __('day-close.errors.reason_too_short', ['n' => self::REASON_MIN_LENGTH]),
                ['min' => self::REASON_MIN_LENGTH],
            );
        }
    }

    private function assertMonthOpen(User $owner, CarbonImmutable $day): void {
        if ($this->isMonthLocked($owner, $day)) {
            throw new DayCloseWorkflowException(
                'monthLocked',
                __('day-close.errors.month_locked'),
                ['day' => $day->toDateString()],
            );
        }
    }

    private function ownerOf(DayClosure $closure): User {
        $owner = $closure->user;
        if (! $owner instanceof User) {
            throw new DayCloseWorkflowException(
                'ownerMissing',
                __('day-close.errors.owner_missing'),
                ['day_closure_id' => $closure->id],
            );
        }

        return $owner;
    }

    private function resolveActorId(?User $actor): ?int {
        if ($actor instanceof User) {
            return (int) $actor->id;
        }

        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }

    private function closureOf(DayCorrectionRequest $request): DayClosure {
        $closure = $request->dayClosure;
        if (! $closure instanceof DayClosure) {
            throw new DayCloseWorkflowException(
                'closureMissing',
                __('day-close.errors.closure_missing'),
                ['day_correction_request_id' => $request->id],
            );
        }

        return $closure;
    }
}
