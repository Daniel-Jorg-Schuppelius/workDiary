<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonthClosureService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\TimeApproval;

use App\Enums\TimeApproval\MonthClosureStatus;
use App\Models\{MonthClosure, MonthClosureEvent, User};
use App\Services\Concerns\ResolvesActorId;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Statusmaschine für Monatsfreigaben (MVP-016).
 *
 * Implementiert die in ../WorkDiary-Architecture/monatsfreigabe.md §4 definierten Übergänge:
 * draft → submitted → approved|rejected → reopened|locked.
 * Jede Methode kapselt
 *  - die Vorbedingungen (offene Tage, Begründungslänge, …),
 *  - das Schreiben des neuen Status,
 *  - das Schreiben eines append-only MonthClosureEvent-Audits,
 *  - das (Re-)Einfrieren des Totals-Snapshots.
 *
 * Fehler werden als {@see MonthClosureWorkflowException} mit reasonCode
 * geworfen, damit Controller fachlich sprechende Antworten erzeugen können.
 */
class MonthClosureService {
    use ResolvesActorId;

    public const REASON_MIN_LENGTH = 20;

    public function __construct(
        private readonly MonthTotalsSnapshotter $snapshotter,
    ) {}

    /**
     * Holt die Monatsfreigabe für (User, Jahr, Monat) oder legt sie als
     * Entwurf an (idempotent).
     */
    public function getOrCreate(User $user, int $year, int $month): MonthClosure {
        $this->assertValidPeriod($year, $month);

        /** @var MonthClosure|null $existing */
        $existing = MonthClosure::query()
            ->where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        if ($existing instanceof MonthClosure) {
            return $existing;
        }

        $counts = $this->snapshotter->counts($user, $year, $month);

        /** @var MonthClosure $closure */
        $closure = MonthClosure::query()->create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'period_year' => $year,
            'period_month' => $month,
            'status' => MonthClosureStatus::Draft,
            'days_total' => $counts['days_total'],
            'days_with_attendance' => $counts['days_with_attendance'],
            'days_closed' => $counts['days_closed'],
            'days_open' => $counts['days_open'],
            'warnings_count' => $counts['warnings_count'],
        ]);

        $this->logEvent($closure, 'month.draftStarted', $this->actorId());

        return $closure;
    }

    /**
     * draft|reopened → submitted.
     * Vorbedingung: keine offenen Tage, keine ⛔-Warnungen.
     * Friert den Totals-Snapshot ein.
     */
    public function submit(MonthClosure $closure, ?User $actor = null): MonthClosure {
        $this->assertStatus($closure, [MonthClosureStatus::Draft, MonthClosureStatus::Reopened, MonthClosureStatus::Rejected]);

        $owner = $this->ownerOf($closure);
        $snapshot = $this->snapshotter->build($owner, $closure->period_year, $closure->period_month);
        $counts = $this->snapshotter->counts($owner, $closure->period_year, $closure->period_month);

        if ($counts['days_open'] > 0) {
            throw new MonthClosureWorkflowException(
                'daysOpen',
                __('Der Monat enthält noch :count offene Tage und kann nicht eingereicht werden.', ['count' => $counts['days_open']]),
                ['days_open' => $counts['days_open']],
            );
        }
        if ($snapshot['warnings']['blocking'] > 0) {
            throw new MonthClosureWorkflowException(
                'blockingWarnings',
                __('Der Monat hat :count blockierende Warnungen und kann nicht eingereicht werden.', ['count' => $snapshot['warnings']['blocking']]),
                ['blocking' => $snapshot['warnings']['blocking']],
            );
        }

        $actorId = $this->resolveActorId($actor);

        $closure = DB::transaction(function () use ($closure, $snapshot, $counts, $actorId): MonthClosure {
            $closure = $this->lockAndAssert($closure, [MonthClosureStatus::Draft, MonthClosureStatus::Reopened, MonthClosureStatus::Rejected]);
            $closure->fill([
                'status' => MonthClosureStatus::Submitted,
                'submitted_at' => CarbonImmutable::now(),
                'submitted_by_user_id' => $actorId,
                'decided_at' => null,
                'decided_by_user_id' => null,
                'decision_note' => null,
                'totals' => $snapshot,
                'days_total' => $counts['days_total'],
                'days_with_attendance' => $counts['days_with_attendance'],
                'days_closed' => $counts['days_closed'],
                'days_open' => $counts['days_open'],
                'warnings_count' => $counts['warnings_count'],
            ])->save();

            $this->logEvent($closure, 'month.submitted', $actorId, payload: ['snapshot_minutes' => $snapshot['minutes']]);

            return $closure->refresh();
        });

        // Benachrichtigung (MVP-018, additiv) erst nach Commit: Entscheider
        // (Default-Regel: Rolle Teamleitung) über die Einreichung informieren.
        app(\App\Services\Notification\NotificationDispatcher::class)->notify(
            \App\Enums\Notification\NotificationEvent::MonthClosureSubmitted,
            $closure,
            $owner,
            [
                'title' => (string) __('notification.message.month_submitted_title', [
                    'user' => (string) $owner->name,
                    'period' => sprintf('%02d/%d', (int) $closure->period_month, (int) $closure->period_year),
                ]),
                'title_key' => 'notification.message.month_submitted_title',
                'title_params' => [
                    'user' => (string) $owner->name,
                    'period' => sprintf('%02d/%d', (int) $closure->period_month, (int) $closure->period_year),
                ],
                'message' => null,
                'url' => route('admin.month-approval.index'),
            ],
        );

        return $closure;
    }

    /**
     * submitted → approved.
     * Speichert finalen, immutable Snapshot.
     */
    public function approve(MonthClosure $closure, ?User $actor = null, ?string $note = null): MonthClosure {
        $this->assertStatus($closure, [MonthClosureStatus::Submitted]);

        // Snapshot beim Approval erneut bauen — der zuletzt eingefrorene
        // Zustand ist exakt das, was offiziell genehmigt wurde.
        $owner = $this->ownerOf($closure);
        $snapshot = $this->snapshotter->build($owner, $closure->period_year, $closure->period_month);
        $actorId = $this->resolveActorId($actor);

        $closure = DB::transaction(function () use ($closure, $snapshot, $actorId, $note): MonthClosure {
            $closure = $this->lockAndAssert($closure, [MonthClosureStatus::Submitted]);
            $closure->fill([
                'status' => MonthClosureStatus::Approved,
                'decided_at' => CarbonImmutable::now(),
                'decided_by_user_id' => $actorId,
                'decision_note' => $note,
                'totals' => $snapshot,
            ])->save();

            $this->logEvent($closure, 'month.approved', $actorId, $note);

            return $closure->refresh();
        });

        // Vollaudit 2026-07 (N4): Entscheidung an die betroffene Person.
        $this->notifyDecision($closure, 'month_approved_title');

        return $closure;
    }

    /**
     * submitted → rejected. Pflicht-Begründung ≥ {@see REASON_MIN_LENGTH} Zeichen.
     */
    public function reject(MonthClosure $closure, string $reason, ?User $actor = null): MonthClosure {
        $this->assertStatus($closure, [MonthClosureStatus::Submitted]);
        $this->assertReason($reason);

        $actorId = $this->resolveActorId($actor);

        $closure = DB::transaction(function () use ($closure, $reason, $actorId): MonthClosure {
            $closure = $this->lockAndAssert($closure, [MonthClosureStatus::Submitted]);
            $closure->fill([
                'status' => MonthClosureStatus::Rejected,
                'decided_at' => CarbonImmutable::now(),
                'decided_by_user_id' => $actorId,
                'decision_note' => $reason,
            ])->save();

            $this->logEvent($closure, 'month.rejected', $actorId, $reason);

            return $closure->refresh();
        });

        // Vollaudit 2026-07 (N4): Ablehnung inkl. Begründung an die betroffene Person.
        $this->notifyDecision($closure, 'month_rejected_title');

        return $closure;
    }

    /**
     * rejected → draft (durch Mitarbeitenden, ohne Pflicht-Begründung)
     * oder approved|locked → reopened (durch Admin, Pflicht-Begründung).
     */
    public function reopen(MonthClosure $closure, ?User $actor = null, ?string $reason = null): MonthClosure {
        $current = $closure->status;
        $actorId = $this->resolveActorId($actor);

        $isSelfReopen = $current === MonthClosureStatus::Rejected
            && $actorId !== null
            && $actorId === $closure->user_id;

        if ($isSelfReopen) {
            return DB::transaction(function () use ($closure, $actorId): MonthClosure {
                $closure->fill([
                    'status' => MonthClosureStatus::Draft,
                    'decided_at' => null,
                    'decided_by_user_id' => null,
                    'decision_note' => null,
                ])->save();

                $this->logEvent($closure, 'month.reopenedBySelf', $actorId);

                return $closure->refresh();
            });
        }

        // Admin-Reopen
        $this->assertStatus($closure, [MonthClosureStatus::Approved, MonthClosureStatus::Locked, MonthClosureStatus::Rejected]);
        $this->assertReason($reason ?? '');

        $closure = DB::transaction(function () use ($closure, $reason, $actorId): MonthClosure {
            $closure = $this->lockAndAssert($closure, [MonthClosureStatus::Approved, MonthClosureStatus::Locked, MonthClosureStatus::Rejected]);
            $wasLocked = $closure->status === MonthClosureStatus::Locked;

            $closure->fill([
                'status' => MonthClosureStatus::Reopened,
                'decision_note' => $reason,
                'locked_at' => null,
                'locked_by_user_id' => null,
            ])->save();

            $this->logEvent(
                $closure,
                'month.reopenedByAdmin',
                $actorId,
                $reason,
                ['was_locked' => $wasLocked],
            );

            return $closure->refresh();
        });

        // Vollaudit 2026-07 (N4): Wiedereröffnung durch Admin an die betroffene Person.
        $this->notifyDecision($closure, 'month_reopened_title');

        return $closure;
    }

    /**
     * Vollaudit 2026-07 (N4): Entscheidung (Genehmigung/Ablehnung/Wieder-
     * eröffnung) an die betroffene Person melden — nach Commit, Muster wie
     * die Einreichungs-Benachrichtigung an die Entscheider.
     */
    private function notifyDecision(MonthClosure $closure, string $titleKey): void {
        $owner = $this->ownerOf($closure);
        $period = sprintf('%02d/%d', (int) $closure->period_month, (int) $closure->period_year);

        app(\App\Services\Notification\NotificationDispatcher::class)->notify(
            \App\Enums\Notification\NotificationEvent::MonthClosureDecided,
            $closure,
            $owner,
            [
                'title' => (string) __('notification.message.' . $titleKey, ['period' => $period]),
                'title_key' => 'notification.message.' . $titleKey,
                'title_params' => ['period' => $period],
                'message' => $closure->decision_note,
                'url' => route('month-approval.show', ['year' => $closure->period_year, 'month' => $closure->period_month]),
            ],
        );
    }

    /**
     * approved → locked. Wird typischerweise vom Export-Job (MVP-019) gesetzt.
     */
    public function lock(MonthClosure $closure, ?User $actor = null): MonthClosure {
        $this->assertStatus($closure, [MonthClosureStatus::Approved]);

        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($closure, $actorId): MonthClosure {
            $closure = $this->lockAndAssert($closure, [MonthClosureStatus::Approved]);
            $closure->fill([
                'status' => MonthClosureStatus::Locked,
                'locked_at' => CarbonImmutable::now(),
                'locked_by_user_id' => $actorId,
            ])->save();

            $this->logEvent($closure, 'month.locked', $actorId);

            return $closure->refresh();
        });
    }

    /**
     * Prüft, ob der angegebene Tag in einem fachlich gesperrten Monat liegt
     * (submitted|approved|locked). Wird vom TimeCorrectionService (MVP-017)
     * konsumiert, um Direkt-Apply gegen gesperrte Monate zu verhindern.
     */
    public function isPeriodLockedForUser(User $user, CarbonImmutable $day): bool {
        /** @var MonthClosure|null $closure */
        $closure = MonthClosure::query()
            ->where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->where('period_year', (int) $day->year)
            ->where('period_month', (int) $day->month)
            ->first();

        return $closure instanceof MonthClosure && $closure->status->isLocked();
    }

    // ── intern ─────────────────────────────────────────────────────────

    /** @param  list<MonthClosureStatus>  $allowed */
    private function assertStatus(MonthClosure $closure, array $allowed): void {
        if (! in_array($closure->status, $allowed, true)) {
            throw new MonthClosureWorkflowException(
                'illegalTransition',
                __('Aktion nicht erlaubt: Monatsstatus ist :status.', ['status' => $closure->status->value]),
                ['from' => $closure->status->value, 'allowed' => array_map(fn(MonthClosureStatus $s) => $s->value, $allowed)],
            );
        }
    }

    /**
     * Sperrt die Closure-Zeile in der laufenden Transaktion und prüft den
     * Status ERNEUT gegen den frischen Wert. Die assertStatus()-Vorprüfung läuft
     * auf dem (evtl. veralteten) übergebenen Modell — ohne dieses Re-Lock würden
     * zwei parallele submit()/approve() beide durchlaufen und doppelte
     * Audit-Events + Benachrichtigungen erzeugen.
     *
     * @param  list<MonthClosureStatus>  $allowed
     */
    private function lockAndAssert(MonthClosure $closure, array $allowed): MonthClosure {
        /** @var MonthClosure $fresh */
        $fresh = MonthClosure::query()->whereKey($closure->getKey())->lockForUpdate()->firstOrFail();
        $this->assertStatus($fresh, $allowed);

        return $fresh;
    }

    private function assertReason(string $reason): void {
        if (mb_strlen(trim($reason)) < self::REASON_MIN_LENGTH) {
            throw new MonthClosureWorkflowException(
                'reasonTooShort',
                __('Eine Begründung von mindestens :n Zeichen ist erforderlich.', ['n' => self::REASON_MIN_LENGTH]),
                ['min' => self::REASON_MIN_LENGTH],
            );
        }
    }

    /** Liegt die Periode im zulässigen Bereich (Jahr 2000–2999, Monat 1–12)? */
    public function isValidPeriod(int $year, int $month): bool {
        return $year >= 2000 && $year <= 2999 && $month >= 1 && $month <= 12;
    }

    private function assertValidPeriod(int $year, int $month): void {
        if (! $this->isValidPeriod($year, $month)) {
            throw new MonthClosureWorkflowException(
                'invalidPeriod',
                __('Ungültige Periode :year-:month.', ['year' => $year, 'month' => $month]),
                ['year' => $year, 'month' => $month],
            );
        }
    }

    /** @param  array<string, mixed>|null  $payload */
    private function logEvent(MonthClosure $closure, string $event, ?int $actorId, ?string $note = null, ?array $payload = null): void {
        MonthClosureEvent::query()->create([
            'month_closure_id' => $closure->id,
            'event' => $event,
            'actor_user_id' => $actorId ?? 0,
            'note' => $note,
            'payload' => $payload,
        ]);
    }

    private function actorId(): ?int {
        return $this->resolveActorId(null);
    }

    private function ownerOf(MonthClosure $closure): User {
        $owner = $closure->user;
        if (! $owner instanceof User) {
            throw new MonthClosureWorkflowException(
                'ownerMissing',
                __('Monatsfreigabe ohne gültigen Eigentümer.'),
                ['month_closure_id' => $closure->id],
            );
        }

        return $owner;
    }
}
