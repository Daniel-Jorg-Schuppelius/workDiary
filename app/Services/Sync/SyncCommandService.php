<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncCommandService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Sync;

use App\Enums\Sync\SyncCommandStatus;
use App\Http\Controllers\FormSubmissionController;
use App\Models\{Attendance, AuditLog, Comment, DiaryEntry, FormSubmission, FormTemplate, SyncCommand, TimeCorrectionRequest, User};
use App\Services\Attendance\AttendanceClockService;
use App\Services\Form\FormService;
use App\Services\TimeApproval\TimeCorrectionService;
use App\Support\{Setting, Sqid};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\{DB, Gate, Validator};
use Illuminate\Validation\{Rule, ValidationException};
use RuntimeException;

/**
 * Führt Offline-Sync-Befehle der Client-Outbox idempotent aus (Feature 035,
 * Phase 1 — offline-sync-architektur.md §3.2). Pro Befehl gilt:
 *
 *  - Ausführung läuft über die BESTEHENDEN Services/Gates (keine zweite
 *    Geschäftslogik): Stempel via {@see AttendanceClockService}, Kommentare
 *    über das Comment-Gate + Relation wie im CommentController.
 *  - Ausführung + Idempotenz-Registrierung ({@see SyncCommand}) in EINER
 *    Transaktion: Crash davor hinterlässt nichts (Retry führt frisch aus),
 *    paralleler Doppel-Submit rollt über die Unique-Verletzung zurück und
 *    wird als `duplicate` beantwortet.
 *  - Fachliche Ablehnungen (Validierung, Gate, bereits offener Stempel)
 *    werden als `rejected` registriert — der Client räumt die Outbox und
 *    zeigt die Meldung; ein blindes Endlos-Retry ist damit ausgeschlossen.
 */
class SyncCommandService {
    /** Unterstützte Befehlstypen (MVP-Scope: append-artige Daten, §3.1). */
    public const TYPES = [
        'attendance.clock-in',
        'attendance.clock-out',
        'comment.diary',
        'form.submission',
        // Erster ÄNDERNDER Befehl (Feature 035 Phase 3; Audit 2026-08, W4.1):
        // vergessene/falsche Stempelzeit offline nachtragen. Läuft NICHT am
        // Genehmigungsweg vorbei — er erzeugt einen TimeCorrectionRequest und
        // wendet ihn nur an, wenn die Organisation Selbstkorrektur erlaubt.
        'attendance.correct',
    ];

    public function __construct(
        private readonly AttendanceClockService $clock,
        private readonly FormService $forms,
        private readonly TimeCorrectionService $corrections,
    ) {}

    /**
     * @param  array{client_uuid: string, type: string, payload?: array<string, mixed>, captured_at?: string|null}  $command
     * @return array{client_uuid: string, status: string, ref: string|null, errors: array<string, mixed>|null}
     */
    public function handle(User $user, array $command): array {
        $clientUuid = $command['client_uuid'];

        $existing = SyncCommand::query()
            ->where('user_id', $user->id)
            ->where('client_uuid', $clientUuid)
            ->first();

        if ($existing !== null) {
            return $this->response($clientUuid, SyncCommandStatus::Duplicate, $existing->result_ref, null);
        }

        try {
            return DB::transaction(function () use ($user, $command, $clientUuid): array {
                $ref = $this->execute($user, $command['type'], $command['payload'] ?? []);

                $this->record($user, $command, SyncCommandStatus::Applied, $ref, null);

                // §3.4: angewendete Sync-Befehle laufen in die Audit-Hash-Kette.
                AuditLog::query()->create([
                    'organization_id' => $user->organization_id,
                    'user_id' => $user->id,
                    'event' => 'sync.applied',
                    'auditable_type' => User::class,
                    'auditable_id' => $user->id,
                    'changes' => [
                        'type' => $command['type'],
                        'client_uuid' => $clientUuid,
                        'ref' => $ref,
                    ],
                ]);

                return $this->response($clientUuid, SyncCommandStatus::Applied, $ref, null);
            });
        } catch (QueryException $e) {
            // Unique (user_id, client_uuid) — paralleler Doppel-Submit.
            if ($this->isDuplicateKey($e)) {
                $row = SyncCommand::query()
                    ->where('user_id', $user->id)
                    ->where('client_uuid', $clientUuid)
                    ->first();

                return $this->response($clientUuid, SyncCommandStatus::Duplicate, $row?->result_ref, null);
            }

            throw $e;
        } catch (ValidationException $e) {
            return $this->reject($user, $command, $e->errors());
        } catch (SyncConflictException $e) {
            // VOR dem RuntimeException-Zweig: der Konflikt ist eine Unterklasse
            // und darf nicht als Ablehnung durchrutschen.
            return $this->conflict($user, $command, $e);
        } catch (RuntimeException $e) {
            return $this->reject($user, $command, ['command' => [$e->getMessage()]]);
        }
    }

    /**
     * Führt den fachlichen Teil aus und liefert die Ergebnis-Referenz
     * (`<tabelle>:<id>`). Wirft ValidationException/RuntimeException zur
     * Ablehnung.
     *
     * @param  array<string, mixed>  $payload
     */
    private function execute(User $user, string $type, array $payload): string {
        return match ($type) {
            'attendance.clock-in' => $this->clockIn($user, $payload),
            'attendance.clock-out' => $this->clockOut($user, $payload),
            'comment.diary' => $this->commentDiary($user, $payload),
            'form.submission' => $this->formSubmission($user, $payload),
            'attendance.correct' => $this->attendanceCorrect($user, $payload),
            default => throw new RuntimeException('Unbekannter Sync-Befehlstyp: ' . $type),
        };
    }

    /** @param  array<string, mixed>  $payload */
    private function clockIn(User $user, array $payload): string {
        if (! Gate::forUser($user)->allows('create', Attendance::class)) {
            throw new RuntimeException((string) __('Keine Berechtigung für Anwesenheits-Stempel.'));
        }

        $data = $this->validatePayload($payload, [
            'started_at' => ['required', 'date'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:' . (int) Setting::get('validation.attendance.note_max', 1000)],
        ]);

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

        $data = $this->validatePayload($payload, [
            'ended_at' => ['required', 'date'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:' . (int) Setting::get('validation.attendance.note_max', 1000)],
        ]);

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

    /** @param  array<string, mixed>  $payload */
    private function commentDiary(User $user, array $payload): string {
        if (! Gate::forUser($user)->allows('create', Comment::class)) {
            throw new RuntimeException((string) __('Keine Berechtigung für Kommentare.'));
        }

        $data = $this->validatePayload($payload, [
            'diary' => ['required', 'string'],
            'body' => ['required', 'string', 'max:' . (int) Setting::get('validation.comment.body_max', 5000)],
        ]);

        // Sqid immer gegen die Zielmodellklasse dekodieren; der
        // OrganizationScope zieht die Mandantengrenze der Auflösung.
        $diary = DiaryEntry::query()
            ->whereKey(Sqid::decode(DiaryEntry::class, $data['diary']))
            ->first();

        if ($diary === null) {
            throw new RuntimeException((string) __('Auftrag nicht gefunden.'));
        }

        $comment = $diary->comments()->create([
            'user_id' => $user->id,
            'body' => $data['body'],
        ]);

        return 'comments:' . $comment->id;
    }

    /**
     * Formular offline ausfüllen (Phase 3, MVP-367): Werte plus — seit dem
     * Audit 2026-08 (W4.1) — angekündigte Foto-/Datei-Felder (`pending_files`).
     * Die Abgabe entsteht sofort und trägt einen Nachreich-Marker; die Inhalte
     * lädt der Client danach über `api.internal.sync.attachments` hoch. Ohne
     * diese Ankündigung würde ein Pflicht-Fotofeld die ganze Abgabe abweisen —
     * genau der Fall, der die Foto-Queue nötig machte.
     *
     * Unterschriften bleiben dem Online-Weg vorbehalten (Konzept §5).
     *
     * @param  array<string, mixed>  $payload
     */
    private function formSubmission(User $user, array $payload): string {
        if (! Gate::forUser($user)->allows('create', FormSubmission::class)) {
            throw new RuntimeException((string) __('Keine Berechtigung für Formulare.'));
        }

        $data = $this->validatePayload($payload, [
            'template' => ['required', 'string'],
            'subject_kind' => ['nullable', 'string', Rule::in(array_keys(FormSubmissionController::SUBJECT_MAP))],
            'subject_id' => ['nullable', 'string', 'required_with:subject_kind'],
            'values' => ['nullable', 'array'],
            // Offline erfasste Foto-/Datei-Felder: der Inhalt kommt separat
            // über `api.internal.sync.attachments` nach (Audit 2026-08, W4.1).
            'pending_files' => ['nullable', 'array'],
            'pending_files.*' => ['string', 'max:64'],
        ]);

        $templateId = Sqid::decodeOrNumeric(FormTemplate::class, $data['template']);
        /** @var FormTemplate|null $template */
        $template = ($templateId !== null && $templateId > 0)
            ? FormTemplate::query()->active()->find($templateId)
            : null;

        if ($template === null) {
            throw new RuntimeException((string) __('Formularvorlage nicht gefunden.'));
        }

        $subject = null;
        if (filled($data['subject_kind'] ?? null)) {
            $subject = $this->resolveFormSubject((string) $data['subject_kind'], (string) ($data['subject_id'] ?? ''));
        }

        $submission = $this->forms->submit(
            $template,
            $subject,
            (array) ($data['values'] ?? []),
            $user,
            deferredKeys: array_values(array_filter((array) ($data['pending_files'] ?? []), 'is_string')),
        );

        return 'form_submissions:' . $submission->id;
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
     *     {@see \App\Models\TimeCorrectionRequest} durch denselben Workflow
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

        $data = $this->validatePayload($payload, [
            'attendance' => ['required', 'string'],
            'base_version' => ['required', 'string', 'max:64'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'reason' => ['required', 'string', 'min:20', 'max:4000'],
        ]);

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
                'target_type' => Attendance::class,
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

    /** Subjekt-Auflösung über die Whitelist des Online-Wegs (org-gescopt). */
    private function resolveFormSubject(string $kind, string $rawId): Model {
        $class = FormSubmissionController::SUBJECT_MAP[$kind] ?? null;
        $id = $class !== null ? Sqid::decodeOrNumeric($class, $rawId) : null;

        /** @var Model|null $subject */
        $subject = ($class !== null && $id !== null && $id > 0)
            ? $class::query()->find($id)
            : null;

        if ($subject === null) {
            throw new RuntimeException((string) __('Bezugsobjekt nicht gefunden.'));
        }

        return $subject;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, array<int, mixed>>  $rules
     * @return array<string, mixed>
     */
    private function validatePayload(array $payload, array $rules): array {
        return Validator::make($payload, $rules)->validate();
    }

    /**
     * @param  array{client_uuid: string, type: string, payload?: array<string, mixed>, captured_at?: string|null}  $command
     * @param  array<string, mixed>  $errors
     * @return array{client_uuid: string, status: string, ref: string|null, errors: array<string, mixed>|null}
     */
    private function reject(User $user, array $command, array $errors): array {
        try {
            DB::transaction(function () use ($user, $command, $errors): void {
                $this->record($user, $command, SyncCommandStatus::Rejected, null, $errors);
            });
        } catch (QueryException $e) {
            if (! $this->isDuplicateKey($e)) {
                throw $e;
            }
            // Paralleler Doppel-Submit derselben Ablehnung — Ergebnis identisch.
        }

        return $this->response($command['client_uuid'], SyncCommandStatus::Rejected, null, $errors);
    }

    /**
     * Konflikt registrieren und den Server-Stand mitgeben — der Client zeigt
     * beide Fassungen nebeneinander (§3.3).
     *
     * @param  array{client_uuid: string, type: string, payload?: array<string, mixed>, captured_at?: string|null}  $command
     * @return array{client_uuid: string, status: string, ref: string|null, errors: array<string, mixed>|null, conflict?: array<string, mixed>}
     */
    private function conflict(User $user, array $command, SyncConflictException $e): array {
        $errors = ['command' => [$e->getMessage()]];

        try {
            DB::transaction(function () use ($user, $command, $errors): void {
                $this->record($user, $command, SyncCommandStatus::Conflict, null, $errors);
            });
        } catch (QueryException $qe) {
            if (! $this->isDuplicateKey($qe)) {
                throw $qe;
            }
        }

        return $this->response($command['client_uuid'], SyncCommandStatus::Conflict, null, $errors) + [
            'conflict' => [
                'server' => $e->serverState,
                'current_version' => $e->currentVersion,
            ],
        ];
    }

    /**
     * @param  array{client_uuid: string, type: string, payload?: array<string, mixed>, captured_at?: string|null}  $command
     * @param  array<string, mixed>|null  $errors
     */
    private function record(User $user, array $command, SyncCommandStatus $status, ?string $ref, ?array $errors): void {
        SyncCommand::query()->create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'client_uuid' => $command['client_uuid'],
            'type' => $command['type'],
            'payload' => $this->sanitizedPayload($command),
            'result_status' => $status,
            'result_ref' => $ref,
            'result_errors' => $errors,
            'captured_at' => $command['captured_at'] ?? null,
        ]);
    }

    /**
     * Diagnose-Payload ohne Freitexte/Inhalte (Kommentar-/Notiz-Texte und
     * Formularwerte liegen im Zielmodell, nicht doppelt im Sync-Register).
     *
     * @param  array{client_uuid: string, type: string, payload?: array<string, mixed>, captured_at?: string|null}  $command
     * @return array<string, mixed>
     */
    private function sanitizedPayload(array $command): array {
        $payload = $command['payload'] ?? [];
        unset($payload['body'], $payload['note'], $payload['values'], $payload['reason']);

        return $payload;
    }

    /**
     * @param  array<string, mixed>|null  $errors
     * @return array{client_uuid: string, status: string, ref: string|null, errors: array<string, mixed>|null}
     */
    private function response(string $clientUuid, SyncCommandStatus $status, ?string $ref, ?array $errors): array {
        return [
            'client_uuid' => $clientUuid,
            'status' => $status->value,
            'ref' => $ref,
            'errors' => $errors,
        ];
    }

    private function isDuplicateKey(QueryException $e): bool {
        // MySQL 1062 / SQLite 2067|1555 / Postgres 23505 — treiberneutral über
        // die Meldung, wie im Bestand (NumberSequence) üblich.
        return str_contains(strtolower($e->getMessage()), 'unique')
            || str_contains($e->getMessage(), '1062')
            || str_contains($e->getMessage(), '23505');
    }
}
