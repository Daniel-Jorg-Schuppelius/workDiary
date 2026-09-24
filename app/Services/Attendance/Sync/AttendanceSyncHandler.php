<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceSyncHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Attendance\Sync;

use App\Models\Platform\User;
use App\Models\Time\{Attendance, TimeCorrectionRequest};
use App\Services\Attendance\{AttendanceClockService, StampPlausibility};
use App\Services\Sync\Contracts\SyncCommandHandler;
use App\Services\Sync\SyncConflictException;
use App\Services\TimeApproval\TimeCorrectionService;
use App\Support\MorphMap;
use App\Support\{Setting, Sqid};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{Gate, Validator};
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Offline-Stempel (Feature 035): Kommen/Gehen sowie `attendance.correct`, der
 * erste ändernde Befehl (Audit 2026-08, W4.1) — er läuft als Zeitkorrektur
 * durch den Genehmigungsweg und wird nur bei erlaubter Selbstkorrektur direkt
 * angewendet.
 */
final class AttendanceSyncHandler implements SyncCommandHandler {
    public function __construct(
        private readonly AttendanceClockService $clock,
        private readonly TimeCorrectionService $corrections,
    ) {}

    /** @return list<string> */
    public function types(): array {
        return ['attendance.clock-in', 'attendance.clock-out', 'attendance.correct'];
    }

    public function handle(User $user, string $type, array $payload): string {
        return match ($type) {
            'attendance.clock-in' => $this->clockIn($user, $payload),
            'attendance.clock-out' => $this->clockOut($user, $payload),
            'attendance.correct' => $this->attendanceCorrect($user, $payload),
            default => throw new RuntimeException('Unbekannter Sync-Befehlstyp: ' . $type),
        };
    }

    /** @param  array<string, mixed>  $payload */
    private function clockIn(User $user, array $payload): string {
        if (! Gate::forUser($user)->allows('create', Attendance::class)) {
            throw new RuntimeException((string) __('Keine Berechtigung für Anwesenheits-Stempel.'));
        }

        $data = Validator::make($payload, [
            'started_at' => ['required', 'date'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:' . (int) Setting::get('validation.attendance.note_max', 1000)],
        ])->validate();

        $startedAt = $this->assertPlausibleStamp($user, (string) $data['started_at'], 'started_at');
        $this->assertNoOverlap($user, $startedAt);

        $attendance = $this->clock->clockIn($user, [
            'started_at' => $data['started_at'],
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
            'device' => 'offline-pwa',
            'note' => $data['note'] ?? null,
        ]);

        return 'attendances:' . $attendance->id;
    }

    /** @param  array<string, mixed>  $payload */
    private function clockOut(User $user, array $payload): string {
        if (! Gate::forUser($user)->allows('create', Attendance::class)) {
            throw new RuntimeException((string) __('Keine Berechtigung für Anwesenheits-Stempel.'));
        }

        $data = Validator::make($payload, [
            'ended_at' => ['required', 'date'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:' . (int) Setting::get('validation.attendance.note_max', 1000)],
        ])->validate();

        $this->assertPlausibleStamp($user, (string) $data['ended_at'], 'ended_at');

        $context = [
            'ended_at' => $data['ended_at'],
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
            'device' => 'offline-pwa',
            'note' => $data['note'] ?? null,
        ];
        if (isset($data['break_minutes'])) {
            $context['break_minutes'] = (int) $data['break_minutes'];
        }

        $attendance = $this->clock->clockOut($user, $context);

        if ($attendance === null) {
            throw new RuntimeException((string) __('Kein offener Anwesenheits-Stempel zum Beenden.'));
        }

        return 'attendances:' . $attendance->id;
    }

    /**
     * Stempelzeit offline korrigieren (Feature 035 Phase 3; Audit 2026-08,
     * W4.1) — der erste ÄNDERNDE Befehl.
     *
     * Zwei Leitplanken, die ihn vom bloßen „Update" unterscheiden:
     *
     *  1. **Optimistische Sperre über `base_version`.** Das Gerät nennt den
     *     Stand, den es gesehen hat. Weicht der aktuelle ab, ist das ein
     *     Konflikt und KEINE Ablehnung — der Nutzer entscheidet.
     *  2. **Kein Vorbeigehen am Genehmigungsweg.** Die Korrektur läuft als
     *     {@see \App\Models\Time\TimeCorrectionRequest} durch denselben Workflow
     *     wie online; direkt angewendet wird sie nur, wenn die Organisation
     *     Selbstkorrektur erlaubt — sonst bleibt sie eingereicht und wartet.
     *     Nur EIGENE Stempel: „im Namen von" braucht eine Rechteprüfung im
     *     Dialog und ist offline nicht sinnvoll abbildbar.
     *
     * @param  array<string, mixed>  $payload
     */
    private function attendanceCorrect(User $user, array $payload): string {
        if (! Gate::forUser($user)->allows('create', TimeCorrectionRequest::class)) {
            throw new RuntimeException((string) __('Keine Berechtigung für Zeitkorrekturen.'));
        }

        $data = Validator::make($payload, [
            'attendance' => ['required', 'string'],
            'base_version' => ['required', 'string', 'max:64'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'reason' => ['required', 'string', 'min:20', 'max:4000'],
        ])->validate();

        /** @var Attendance|null $attendance */
        $attendance = Attendance::query()
            ->whereKey(Sqid::decodeOrNumeric(Attendance::class, (string) $data['attendance']))
            ->where('user_id', $user->id)
            ->first();

        if ($attendance === null) {
            throw new RuntimeException((string) __('Stempelung nicht gefunden.'));
        }

        $current = $attendance->correctionVersion();
        if ($current !== (string) $data['base_version']) {
            throw new SyncConflictException(
                (string) __('Die Stempelung wurde zwischenzeitlich geändert.'),
                [
                    'started_at' => $attendance->started_at?->toIso8601String(),
                    'ended_at' => $attendance->ended_at?->toIso8601String(),
                    'break_minutes' => (int) ($attendance->break_minutes_manual ?? 0),
                ],
                $current,
            );
        }

        $before = [
            'started_at' => $attendance->started_at?->toDateTimeString(),
            'ended_at' => $attendance->ended_at?->toDateTimeString(),
            'break_minutes_manual' => (int) ($attendance->break_minutes_manual ?? 0),
        ];
        $after = $before;
        if (array_key_exists('started_at', $data) && $data['started_at'] !== null) {
            $after['started_at'] = CarbonImmutable::parse((string) $data['started_at'])->toDateTimeString();
        }
        if (array_key_exists('ended_at', $data) && $data['ended_at'] !== null) {
            $after['ended_at'] = CarbonImmutable::parse((string) $data['ended_at'])->toDateTimeString();
        }
        if (array_key_exists('break_minutes', $data) && $data['break_minutes'] !== null) {
            $after['break_minutes_manual'] = (int) $data['break_minutes'];
        }

        if ($after === $before) {
            throw new RuntimeException((string) __('Die Korrektur enthält keine Änderung.'));
        }

        $request = $this->corrections->createDraft(
            $user,
            CarbonImmutable::parse($attendance->date?->toDateString() ?? $attendance->started_at?->toDateString() ?? 'today'),
            (string) $data['reason'],
            [[
                'target_type' => MorphMap::alias(Attendance::class),
                'target_id' => (int) $attendance->id,
                'action' => 'update',
                'before' => $before,
                'after' => $after,
            ]],
            $user,
        );

        $request = $this->corrections->submit($request, $user);
        if ($this->corrections->selfApplicable($request)) {
            $this->corrections->selfApply($request);
        }

        return 'time_correction_requests:' . $request->id;
    }

    /**
     * Ist der vom Gerät gelieferte Zeitstempel glaubwürdig — und der Tag offen?
     *
     * Drei Schranken, die der Online-Weg schon durch die Serverzeit hat:
     * keine Zukunft (bis auf Uhrenversatz), nicht älter als das
     * Offline-Fenster, und der Zieltag darf nicht abgeschlossen oder der
     * Monat freigegeben sein. Für Änderungen an gesperrten Tagen gibt es den
     * Weg über `attendance.correct` — eine Zeitkorrektur mit Begründung und
     * Genehmigung.
     */
    private function assertPlausibleStamp(User $user, string $raw, string $field): CarbonImmutable {
        // Gemeinsam mit dem Terminal-Eingang (Sicherheitsaudit 2026-09-13):
        // dort fehlte die Pruefung, deshalb liegt sie jetzt an einer Stelle.
        return app(StampPlausibility::class)->assert($user, $raw, $field);
    }

    /**
     * Keine zweite Stempelung über eine bestehende legen.
     *
     * Beim Online-Stempeln kann das nicht passieren (es gibt genau eine
     * offene). Rückdatiert schon: zwei Kommen-Stempel auf denselben Tag
     * verdoppeln Arbeitszeit, Zuschläge und Gleitzeitkonto.
     */
    private function assertNoOverlap(User $user, CarbonImmutable $startedAt): void {
        $exists = Attendance::query()
            ->where('user_id', $user->id)
            ->where('started_at', '<=', $startedAt)
            ->where(function ($query) use ($startedAt): void {
                $query->whereNull('ended_at')->orWhere('ended_at', '>', $startedAt);
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'started_at' => (string) __('sync.error.stamp_overlaps'),
            ]);
        }
    }
}
