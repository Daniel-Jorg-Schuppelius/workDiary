<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubAttendanceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\{ClubAttendanceSheetStatus, ClubAttendanceStatus, ClubParticipationStatus};
use App\Models\Calendar\Event;
use App\Models\Club\{ClubAttendanceConfirmation, ClubAttendanceRecord, ClubAttendanceRevision, ClubAttendanceSheet, ClubEventParticipation, ClubMember};
use App\Models\Platform\User;
use App\Support\Query\DateRange;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Einzige Schreibstelle für Vereinsnachweise (Feature 159, MVP-844).
 * Eine Anmeldung ist nie ein Nachweis; Trainingszeit entsteht erst mit der
 * bestätigten Liste, in ganzen Minuten und nie länger als die durchgeführte
 * Dauer. Jede Änderung erhöht den Sperrzähler der Liste; ein Formular mit
 * veraltetem Stand wird abgewiesen statt still überschrieben. Nach der
 * ersten Bestätigung braucht jede Korrektur einen Grund. Überlappende
 * bestätigte Intervalle eines Mitglieds werden erkannt und bis zur Klärung
 * nicht angerechnet. Keine Kopplung an Arbeitszeitkonten.
 */
class ClubAttendanceService {
    public function __construct(
        private readonly ClubEventService $clubEvents,
    ) {}

    /** Liste zum Termin (wird beim ersten Zugriff angelegt); Kalenderdauer als Vorgabe bei eintägigen Terminen. */
    public function sheetFor(Event $event): ClubAttendanceSheet {
        /** @var ClubAttendanceSheet|null $sheet */
        $sheet = ClubAttendanceSheet::query()->where('event_id', $event->id)->first();
        if ($sheet !== null) {
            return $sheet;
        }
        if ($event->clubDetails()->doesntExist()) {
            throw ValidationException::withMessages(['event' => __('club.events.error.not_club_event')]);
        }

        $start = CarbonImmutable::instance($event->started_at);
        $end = CarbonImmutable::instance($event->ended_at);
        $singleDay = $this->clubEvents->localDay($event)->equalTo($this->clubEvents->localDay($this->endAsEvent($event)));

        return ClubAttendanceSheet::query()->create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'status' => ClubAttendanceSheetStatus::Open->value,
            // Mehrtägige Lehrgänge: Blöcke ausdrücklich eintragen, keine Gutschrift aller Übernachtungsstunden.
            'conducted_minutes' => $singleDay ? (int) $start->diffInMinutes($end, true) : null,
            'version' => 0,
            'changed_since_confirmation' => true,
        ]);
    }

    /**
     * Soll-Liste der Anwesenheitsliste: Zielgruppen am Termintag, dazu
     * Angemeldete/Wartende und bereits erfasste (spontane) Mitglieder — jedes
     * Mitglied einmal.
     *
     * @return Collection<int, ClubMember>
     */
    public function rosterFor(ClubAttendanceSheet $sheet, Event $event): Collection {
        $members = $this->clubEvents->targetMembers($event)->keyBy('id');

        $extraIds = ClubEventParticipation::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [ClubParticipationStatus::Registered->value, ClubParticipationStatus::Waitlisted->value])
            ->pluck('club_member_id')
            ->merge($sheet->records()->pluck('club_member_id'))
            ->unique()
            ->diff($members->keys());
        if ($extraIds->isNotEmpty()) {
            ClubMember::query()->whereIn('id', $extraIds)->get()->each(function (ClubMember $member) use ($members): void {
                $members->put($member->id, $member);
            });
        }

        return $members->sortBy(fn(ClubMember $member): string => $member->last_name . ' ' . $member->first_name)->values();
    }

    /**
     * Sammelerfassung der offenen Liste. Zeilen ohne Stand bleiben offen;
     * unveränderte Zeilen erzeugen keinen Schreibvorgang.
     *
     * @param  array<int, array{status?: string|null, minutes?: int|string|null, arrived_at?: string|null, left_at?: string|null}>  $rows  Schlüssel: Mitglieds-ID
     */
    public function saveRows(ClubAttendanceSheet $sheet, array $rows, User $actor, int $expectedVersion, ?int $conductedMinutes = null): ClubAttendanceSheet {
        return DB::transaction(function () use ($sheet, $rows, $actor, $expectedVersion, $conductedMinutes): ClubAttendanceSheet {
            $sheet = $this->lockSheet($sheet, $expectedVersion);
            /** @var Event $event */
            $event = $sheet->event()->firstOrFail();
            if ($sheet->isConfirmed()) {
                throw ValidationException::withMessages(['version' => __('club.attendance.error.sheet_confirmed')]);
            }

            if ($conductedMinutes !== null) {
                $this->assertConductedMinutes($sheet, $event, $conductedMinutes);
                $sheet->conducted_minutes = $conductedMinutes;
            }

            $changed = $sheet->isDirty();
            foreach ($rows as $memberId => $row) {
                $status = ClubAttendanceStatus::tryFrom((string) ($row['status'] ?? ''));
                if ($status === null) {
                    continue;
                }
                $member = $this->memberFor($sheet, (int) $memberId);
                $values = $this->recordValues($sheet, $event, $status, $row);
                /** @var ClubAttendanceRecord|null $record */
                $record = $sheet->records()->where('club_member_id', $member->id)->first();
                if ($record !== null && $this->unchanged($record, $values)) {
                    continue;
                }
                $changed = true;
                if ($record === null) {
                    $sheet->records()->create([
                        'organization_id' => $sheet->organization_id,
                        'event_id' => $event->id,
                        'club_member_id' => $member->id,
                        'recorded_by_user_id' => $actor->id,
                        'recorded_at' => now(),
                    ] + $values);
                } else {
                    $record->update($values + ['recorded_by_user_id' => $actor->id, 'recorded_at' => now()]);
                }
            }

            if ($changed) {
                $this->bump($sheet);
            }

            return $sheet->refresh();
        });
    }

    /**
     * Einzelkorrektur — nach der ersten Bestätigung nur mit Grund; die
     * Korrektur wird als Revision festgehalten, der bestätigte Schnappschuss
     * bleibt unverändert.
     *
     * @param  array{status: string, minutes?: int|string|null, arrived_at?: string|null, left_at?: string|null}  $row
     */
    public function correct(ClubAttendanceRecord $record, array $row, User $actor, ?string $reason, int $expectedVersion): ClubAttendanceRecord {
        return DB::transaction(function () use ($record, $row, $actor, $reason, $expectedVersion): ClubAttendanceRecord {
            $sheet = $this->lockSheet($record->sheet()->firstOrFail(), $expectedVersion);
            /** @var Event $event */
            $event = $sheet->event()->firstOrFail();
            $status = ClubAttendanceStatus::tryFrom($row['status']);
            if ($status === null) {
                throw ValidationException::withMessages(['status' => __('club.attendance.error.status_required')]);
            }
            $reason = $this->nullableString($reason);
            if ($sheet->requiresReason() && $reason === null) {
                throw ValidationException::withMessages(['reason' => __('club.attendance.error.reason_required')]);
            }

            $values = $this->recordValues($sheet, $event, $status, $row);
            if ($this->unchanged($record, $values)) {
                return $record;
            }

            if ($sheet->requiresReason()) {
                ClubAttendanceRevision::query()->create([
                    'organization_id' => $sheet->organization_id,
                    'club_attendance_record_id' => $record->id,
                    'sheet_version' => $sheet->version,
                    'previous_status' => $record->status->value,
                    'previous_minutes' => $record->minutes,
                    'status' => $status->value,
                    'minutes' => $values['minutes'],
                    'reason' => $reason,
                    'actor_user_id' => $actor->id,
                ]);
            }
            $record->update($values + ['recorded_by_user_id' => $actor->id, 'recorded_at' => now()]);
            $record->audit('club.attendance.corrected', ['reason' => $reason, 'status' => $status->value, 'minutes' => $values['minutes']]);
            $this->bump($sheet);
            // Korrigierter Nachweis → betroffene Prüfungskandidaten zur fachlichen Überprüfung (MVP-847); erteilte Grade bleiben.
            app(ClubExamService::class)->flagReviewForRecord($record);

            return $record->refresh();
        });
    }

    /** Spontane Teilnahme durch die Leitung — anwesend mit voller Dauer, nach Bestätigung nur mit Grund. */
    public function addSpontaneous(ClubAttendanceSheet $sheet, ClubMember $member, User $actor, int $expectedVersion, ?string $reason = null): ClubAttendanceRecord {
        return DB::transaction(function () use ($sheet, $member, $actor, $expectedVersion, $reason): ClubAttendanceRecord {
            $sheet = $this->lockSheet($sheet, $expectedVersion);
            /** @var Event $event */
            $event = $sheet->event()->firstOrFail();
            if ($member->organization_id !== $sheet->organization_id) {
                throw ValidationException::withMessages(['club_member_id' => __('club.error.member_foreign')]);
            }
            $reason = $this->nullableString($reason);
            if ($sheet->requiresReason() && $reason === null) {
                throw ValidationException::withMessages(['reason' => __('club.attendance.error.reason_required')]);
            }
            /** @var ClubAttendanceRecord|null $existing */
            $existing = $sheet->records()->where('club_member_id', $member->id)->first();
            if ($existing !== null) {
                return $existing;
            }

            $record = $sheet->records()->create([
                'organization_id' => $sheet->organization_id,
                'event_id' => $event->id,
                'club_member_id' => $member->id,
                'status' => ClubAttendanceStatus::Present->value,
                'minutes' => $sheet->maxMinutes($event),
                'spontaneous' => true,
                'recorded_by_user_id' => $actor->id,
                'recorded_at' => now(),
            ]);
            if ($sheet->requiresReason()) {
                ClubAttendanceRevision::query()->create([
                    'organization_id' => $sheet->organization_id,
                    'club_attendance_record_id' => $record->id,
                    'sheet_version' => $sheet->version,
                    'previous_status' => null,
                    'previous_minutes' => null,
                    'status' => ClubAttendanceStatus::Present->value,
                    'minutes' => $record->minutes,
                    'reason' => $reason,
                    'actor_user_id' => $actor->id,
                ]);
            }
            $record->audit('club.attendance.spontaneousAdded', ['reason' => $reason]);
            $this->bump($sheet);

            return $record;
        });
    }

    /**
     * Bestätigung: friert den Stand als Schnappschuss ein (laufende Nummer),
     * prüft Obergrenzen und erkennt Überschneidungen mit anderen bestätigten
     * Nachweisen. Erneutes Bestätigen ohne Änderung erzeugt keine zweite Version.
     */
    public function confirm(ClubAttendanceSheet $sheet, User $actor, int $expectedVersion): ClubAttendanceSheet {
        return DB::transaction(function () use ($sheet, $actor, $expectedVersion): ClubAttendanceSheet {
            $sheet = $this->lockSheet($sheet, $expectedVersion);
            /** @var Event $event */
            $event = $sheet->event()->firstOrFail();
            if ($sheet->isConfirmed() && ! $sheet->changed_since_confirmation) {
                return $sheet;
            }
            if ($sheet->conducted_minutes === null) {
                throw ValidationException::withMessages(['conducted_minutes' => __('club.attendance.error.conducted_required')]);
            }

            $max = $sheet->maxMinutes($event);
            $records = $sheet->records()->get();
            foreach ($records as $record) {
                if ($record->status->isCreditable() && (int) $record->minutes > $max) {
                    throw ValidationException::withMessages(['minutes' => __('club.attendance.error.minutes_exceed', ['max' => $max])]);
                }
            }

            $version = (int) $sheet->confirmations()->max('version') + 1;
            $now = now();
            $sheet->forceFill([
                'status' => ClubAttendanceSheetStatus::Confirmed->value,
                'confirmed_at' => $now,
                'confirmed_by_user_id' => $actor->id,
                'first_confirmed_at' => $sheet->first_confirmed_at ?? $now,
                'changed_since_confirmation' => false,
                'version' => $sheet->version + 1,
            ])->save();

            ClubAttendanceConfirmation::query()->create([
                'organization_id' => $sheet->organization_id,
                'club_attendance_sheet_id' => $sheet->id,
                'version' => $version,
                'conducted_minutes' => $sheet->conducted_minutes,
                'snapshot' => $records->map(fn(ClubAttendanceRecord $record): array => [
                    'member_id' => $record->club_member_id,
                    'status' => $record->status->value,
                    'minutes' => $record->minutes,
                ])->values()->all(),
                'confirmed_by_user_id' => $actor->id,
                'confirmed_at' => $now,
            ]);

            $this->detectOverlaps($sheet, $event, $records);
            $sheet->audit('club.attendance.confirmed', ['version' => $version, 'records' => $records->count()]);

            return $sheet->refresh();
        });
    }

    /** Wieder öffnen (mit Grund) — der bestätigte Schnappschuss bleibt. */
    public function reopen(ClubAttendanceSheet $sheet, User $actor, string $reason, int $expectedVersion): ClubAttendanceSheet {
        return DB::transaction(function () use ($sheet, $actor, $reason, $expectedVersion): ClubAttendanceSheet {
            $sheet = $this->lockSheet($sheet, $expectedVersion);
            if (! $sheet->isConfirmed()) {
                return $sheet;
            }
            $sheet->forceFill(['status' => ClubAttendanceSheetStatus::Open->value, 'changed_since_confirmation' => true, 'version' => $sheet->version + 1])->save();
            $sheet->audit('club.attendance.reopened', ['reason' => $reason, 'actor_id' => $actor->id]);

            return $sheet->refresh();
        });
    }

    /** Überschneidung geklärt — ab jetzt zählt der Nachweis. */
    public function clearOverlap(ClubAttendanceRecord $record, User $actor, string $reason): ClubAttendanceRecord {
        if (! $record->hasUnresolvedOverlap()) {
            return $record;
        }
        $record->update(['overlap_cleared_at' => now(), 'overlap_cleared_by_user_id' => $actor->id]);
        ClubAttendanceRevision::query()->create([
            'organization_id' => $record->organization_id,
            'club_attendance_record_id' => $record->id,
            'sheet_version' => (int) $record->sheet()->value('version'),
            'previous_status' => $record->status->value,
            'previous_minutes' => $record->minutes,
            'status' => $record->status->value,
            'minutes' => $record->minutes,
            'reason' => $reason,
            'actor_user_id' => $actor->id,
        ]);
        $record->audit('club.attendance.overlapCleared', ['reason' => $reason]);

        return $record->refresh();
    }

    /** Angerechnete Trainingsminuten eines Mitglieds im Zeitraum (Kalendertage). */
    public function creditableMinutes(ClubMember $member, CarbonInterface $from, CarbonInterface $to): int {
        return (int) ClubAttendanceRecord::query()
            ->join('club_attendance_sheets', 'club_attendance_sheets.id', '=', 'club_attendance_records.club_attendance_sheet_id')
            ->join('events', 'events.id', '=', 'club_attendance_records.event_id')
            ->where('club_attendance_records.club_member_id', $member->id)
            ->where('club_attendance_sheets.status', ClubAttendanceSheetStatus::Confirmed->value)
            ->whereIn('club_attendance_records.status', [ClubAttendanceStatus::Present->value, ClubAttendanceStatus::Partial->value])
            ->where(fn($query) => $query->whereNull('club_attendance_records.overlap_event_id')->orWhereNotNull('club_attendance_records.overlap_cleared_at'))
            ->where('events.started_at', '>=', DateRange::dayStart($from))
            ->where('events.started_at', '<', DateRange::dayAfter($to))
            ->sum('club_attendance_records.minutes');
    }

    /**
     * Überschneidung: ein anderer bestätigter, anrechenbarer Nachweis desselben
     * Mitglieds, dessen Termin zeitlich mit diesem überlappt.
     *
     * @param  Collection<int, ClubAttendanceRecord>  $records
     */
    private function detectOverlaps(ClubAttendanceSheet $sheet, Event $event, Collection $records): void {
        foreach ($records as $record) {
            if (! $record->status->isCreditable()) {
                continue;
            }
            $other = ClubAttendanceRecord::query()
                ->join('club_attendance_sheets', 'club_attendance_sheets.id', '=', 'club_attendance_records.club_attendance_sheet_id')
                ->join('events', 'events.id', '=', 'club_attendance_records.event_id')
                ->where('club_attendance_records.club_member_id', $record->club_member_id)
                ->where('club_attendance_records.event_id', '!=', $event->id)
                ->where('club_attendance_sheets.status', ClubAttendanceSheetStatus::Confirmed->value)
                ->whereIn('club_attendance_records.status', [ClubAttendanceStatus::Present->value, ClubAttendanceStatus::Partial->value])
                ->where('events.started_at', '<', $event->ended_at)
                ->where('events.ended_at', '>', $event->started_at)
                ->whereNull('events.cancelled_at')
                ->orderBy('events.started_at')
                ->value('club_attendance_records.event_id');

            $overlapId = $other === null ? null : (int) $other;
            if ($overlapId === $record->overlap_event_id) {
                continue;
            }
            $record->update([
                'overlap_event_id' => $overlapId,
                'overlap_cleared_at' => null,
                'overlap_cleared_by_user_id' => null,
            ]);
            if ($overlapId !== null) {
                $record->audit('club.attendance.overlapDetected', ['other_event_id' => $overlapId]);
            }
        }
        unset($sheet);
    }

    /**
     * Minuten je Stand: anwesend = volle Dauer (kürzbar), teilweise aus
     * Ankunft/Abgang innerhalb des Terminfensters oder direkt, entschuldigt/
     * abwesend null. Ganze Minuten, nie negativ, nie über der Obergrenze.
     *
     * @param  array{minutes?: int|string|null, arrived_at?: string|null, left_at?: string|null}  $row
     * @return array{status: string, minutes: int|null, arrived_at: CarbonImmutable|null, left_at: CarbonImmutable|null}
     */
    private function recordValues(ClubAttendanceSheet $sheet, Event $event, ClubAttendanceStatus $status, array $row): array {
        $max = $sheet->maxMinutes($event);
        if (! $status->isCreditable()) {
            return ['status' => $status->value, 'minutes' => null, 'arrived_at' => null, 'left_at' => null];
        }

        $arrived = $this->timeOnEventDay($event, $this->nullableString($row['arrived_at'] ?? null));
        $left = $this->timeOnEventDay($event, $this->nullableString($row['left_at'] ?? null));
        if ($arrived !== null && $left !== null && $left->lessThanOrEqualTo($arrived)) {
            $left = $left->addDay();
        }

        $minutes = $this->nullableString($row['minutes'] ?? null);
        if ($minutes !== null) {
            $minutes = (int) $minutes;
        } elseif ($arrived !== null && $left !== null) {
            $windowStart = max($arrived, CarbonImmutable::instance($event->started_at));
            $windowEnd = min($left, CarbonImmutable::instance($event->ended_at));
            $minutes = $windowEnd->greaterThan($windowStart) ? (int) $windowStart->diffInMinutes($windowEnd, true) : 0;
        } else {
            $minutes = $status === ClubAttendanceStatus::Present ? $max : null;
        }
        if ($minutes === null) {
            throw ValidationException::withMessages(['minutes' => __('club.attendance.error.minutes_required')]);
        }
        if ($minutes < 0 || $minutes > $max) {
            throw ValidationException::withMessages(['minutes' => __('club.attendance.error.minutes_exceed', ['max' => $max])]);
        }

        return ['status' => $status->value, 'minutes' => $minutes, 'arrived_at' => $arrived, 'left_at' => $left];
    }

    /** Uhrzeit „HH:MM" am Kalendertag des Termins (Zeitzone des Termins) → UTC. */
    private function timeOnEventDay(Event $event, ?string $time): ?CarbonImmutable {
        if ($time === null) {
            return null;
        }
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            throw ValidationException::withMessages(['arrived_at' => __('club.attendance.error.time_format')]);
        }

        return $this->clubEvents->localDay($event)->setTimeFromTimeString($time)->utc();
    }

    /** @param array{status: string, minutes: int|null, arrived_at: CarbonImmutable|null, left_at: CarbonImmutable|null} $values */
    private function unchanged(ClubAttendanceRecord $record, array $values): bool {
        return $record->status->value === $values['status']
            && $record->minutes === $values['minutes']
            && ($record->arrived_at?->format('Y-m-d H:i') ?? null) === ($values['arrived_at']?->format('Y-m-d H:i') ?? null)
            && ($record->left_at?->format('Y-m-d H:i') ?? null) === ($values['left_at']?->format('Y-m-d H:i') ?? null);
    }

    private function assertConductedMinutes(ClubAttendanceSheet $sheet, Event $event, int $minutes): void {
        if ($minutes < 0 || $minutes > $sheet->eventMinutes($event)) {
            throw ValidationException::withMessages(['conducted_minutes' => __('club.attendance.error.conducted_exceed', ['max' => $sheet->eventMinutes($event)])]);
        }
    }

    private function memberFor(ClubAttendanceSheet $sheet, int $memberId): ClubMember {
        $member = ClubMember::query()->whereKey($memberId)->where('organization_id', $sheet->organization_id)->first();
        if (! $member instanceof ClubMember) {
            throw ValidationException::withMessages(['club_member_id' => __('club.error.member_foreign')]);
        }

        return $member;
    }

    /** Sperre + Sperrzähler: veralteter Formularstand wird abgewiesen, nichts wird still überschrieben. */
    private function lockSheet(ClubAttendanceSheet $sheet, int $expectedVersion): ClubAttendanceSheet {
        /** @var ClubAttendanceSheet $locked */
        $locked = ClubAttendanceSheet::query()->whereKey($sheet->id)->lockForUpdate()->firstOrFail();
        if ($locked->version !== $expectedVersion) {
            throw ValidationException::withMessages(['version' => __('club.attendance.error.stale', ['version' => $locked->version])]);
        }

        return $locked;
    }

    private function bump(ClubAttendanceSheet $sheet): void {
        $sheet->forceFill(['version' => $sheet->version + 1, 'changed_since_confirmation' => true])->save();
    }

    /** Hilfskonstrukt: Termin-Ende als Beginn eines Klons, um den lokalen Kalendertag des Endes zu bestimmen. */
    private function endAsEvent(Event $event): Event {
        $clone = $event->replicate(['id']);
        $clone->started_at = $event->ended_at;
        $clone->setRelation('organization', $event->organization);

        return $clone;
    }

    private function nullableString(mixed $value): ?string {
        if ($value === null) {
            return null;
        }
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
