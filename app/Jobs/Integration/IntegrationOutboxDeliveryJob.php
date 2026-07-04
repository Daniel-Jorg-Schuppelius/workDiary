<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationOutboxDeliveryJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Integration;

use App\Models\{IntegrationInboxItem, IntegrationOutboxEntry};
use App\Services\Integration\{IntegrationOutboxDispatcherResolver, IntegrationOutboxService};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use RuntimeException;
use Throwable;

/**
 * Stellt einen generischen Outbox-Eintrag an das externe System zu
 * (Feature 055, MVP-114). Idempotent über den `idempotency_key`; Wiederholung
 * mit Backoff über die Queue. Nach Aufbrauchen aller Versuche wird der Eintrag
 * als kompensationspflichtig markiert und als sichtbarer Fall in die
 * Integrations-Inbox gestellt — der lokale Stand bleibt bestehen, der
 * Ausgleich erfolgt fachlich (kein Rollback).
 */
class IntegrationOutboxDeliveryJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly int $entryId) {}

    public function handle(IntegrationOutboxService $outbox, IntegrationOutboxDispatcherResolver $resolver): void {
        $entry = IntegrationOutboxEntry::query()->withoutGlobalScopes()->find($this->entryId);
        if ($entry === null || $entry->status->isTerminal()) {
            return;
        }

        $dispatcher = $resolver->for($entry->plugin_id);
        if ($dispatcher === null) {
            // Kein Plugin registriert → kann nicht zugestellt werden. Nicht
            // endlos wiederholen; die Zustellung erfolgt mit dem Plugin.
            $outbox->markFailed($entry, 'kein Dispatcher für Plugin: ' . $entry->plugin_id);

            return;
        }

        $outbox->markProcessing($entry);

        try {
            if ($dispatcher->dispatch($entry)) {
                $outbox->markConfirmed($entry);

                return;
            }

            throw new RuntimeException('extern nicht bestätigt');
        } catch (Throwable $e) {
            if ($this->attempts() < $this->tries) {
                $outbox->markFailed($entry, $e->getMessage());

                throw $e; // Queue-Wiederholung auslösen
            }

            $this->compensate($entry, $outbox, $e->getMessage());
        }
    }

    /** Sicherheitsnetz der Queue nach Aufbrauchen aller Versuche. */
    public function failed(?Throwable $e): void {
        $entry = IntegrationOutboxEntry::query()->withoutGlobalScopes()->find($this->entryId);
        if ($entry === null || $entry->status->isTerminal()) {
            return;
        }

        $this->compensate($entry, app(IntegrationOutboxService::class), $e?->getMessage() ?? 'Zustellung fehlgeschlagen');
    }

    private function compensate(IntegrationOutboxEntry $entry, IntegrationOutboxService $outbox, string $reason): void {
        $outbox->markCompensationRequired($entry, $reason);

        // Sichtbarer Inbox-Fall statt stillem Verlust: der lokale Stand gilt,
        // die externe Seite ist veraltet — manuelle Auflösung über die Inbox.
        IntegrationInboxItem::query()->withoutGlobalScopes()->firstOrCreate([
            'organization_id' => $entry->organization_id,
            'plugin_id' => $entry->plugin_id,
            'dedupe_key' => 'outbox-failed:' . $entry->idempotency_key,
        ], [
            'source' => $entry->plugin_id,
            'target_type' => $entry->subject_type ?? $entry->operation,
            'external_type' => $entry->operation,
            'external_id' => (string) $entry->id,
            'case_type' => IntegrationInboxItem::CASE_CONFLICT,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'referenceable_type' => $entry->subject_type,
            'referenceable_id' => $entry->subject_id,
            'local_snapshot' => $entry->payload,
            'remote_snapshot' => [],
            'display_title' => $entry->operation,
            'display_subtitle' => mb_substr($reason, 0, 120),
            'occurred_at' => now(),
        ]);
    }
}
