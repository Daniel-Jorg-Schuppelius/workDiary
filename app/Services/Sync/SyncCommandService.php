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
use App\Models\Audit\AuditLog;
use App\Models\Integration\SyncCommand;
use App\Models\Platform\User;
use App\Modules\ModuleRegistry;
use App\Services\Sync\Contracts\SyncCommandHandler;
use App\Support\MorphMap;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
    /** @var array<string, SyncCommandHandler>|null Befehlstyp → Handler, lazy aus den Manifesten */
    private ?array $handlers = null;

    public function __construct(private readonly ModuleRegistry $modules) {}

    /** @return list<string> Bekannte Befehlstypen (für die Request-Validierung) */
    public function types(): array {
        return array_keys($this->handlers());
    }

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
                    'auditable_type' => MorphMap::stableKey(User::class),
                    'auditable_id' => $user->id,
                    'changes' => [
                        'type' => $command['type'],
                        'client_uuid' => $clientUuid,
                        'ref' => $ref,
                    ],
                ]);

                return $this->response($clientUuid, SyncCommandStatus::Applied, $ref, null);
            });
        } catch (UniqueConstraintViolationException) {
            // Unique (user_id, client_uuid) — paralleler Doppel-Submit.
            $row = SyncCommand::query()
                ->where('user_id', $user->id)
                ->where('client_uuid', $clientUuid)
                ->first();

            return $this->response($clientUuid, SyncCommandStatus::Duplicate, $row?->result_ref, null);
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
     * Führt den fachlichen Teil über den Handler des Moduls aus und liefert die
     * Ergebnis-Referenz (`<tabelle>:<id>`). Wirft ValidationException/
     * RuntimeException zur Ablehnung.
     *
     * @param  array<string, mixed>  $payload
     */
    private function execute(User $user, string $type, array $payload): string {
        $handler = $this->handlers()[$type] ?? throw new RuntimeException('Unbekannter Sync-Befehlstyp: ' . $type);

        return $handler->handle($user, $type, $payload);
    }

    /** @return array<string, SyncCommandHandler> */
    private function handlers(): array {
        if ($this->handlers === null) {
            $this->handlers = [];
            foreach ($this->modules->extensions(SyncCommandHandler::class) as $class) {
                /** @var SyncCommandHandler $handler */
                $handler = app($class);
                foreach ($handler->types() as $type) {
                    $this->handlers[$type] = $handler;
                }
            }
        }

        return $this->handlers;
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
        } catch (UniqueConstraintViolationException) {
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
        } catch (UniqueConstraintViolationException) {
            // Paralleler Doppel-Submit desselben Konflikts — Ergebnis identisch.
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

}
