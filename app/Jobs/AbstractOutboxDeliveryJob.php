<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractOutboxDeliveryJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\{OutboxTransitionService, PluginDispatcher};
use App\Models\{IntegrationOutboxEntry, InventoryOutboxEntry};
use App\Services\AbstractPluginDispatcherResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use RuntimeException;
use Throwable;

/**
 * Gemeinsames Zustell-Skelett der Outbox-Delivery-Jobs (C14): Entry-Refetch
 * mit Terminal-Guard, Dispatcher-Auflösung, Retry/Backoff über die Queue und
 * die mark*-Übergänge. Idempotent über den `idempotency_key`; nach Aufbrauchen
 * aller Versuche kompensationspflichtig — der lokale Stand bleibt bestehen,
 * der Ausgleich erfolgt fachlich (kein Rollback). Je Stack bleiben Vorprüfung
 * (Modul-Gate) und Kompensationsziel.
 *
 * @template TEntry of IntegrationOutboxEntry|InventoryOutboxEntry
 * @template TDispatcher of PluginDispatcher
 */
abstract class AbstractOutboxDeliveryJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly int $entryId) {}

    /**
     * Frische Query auf die konkrete Outbox-Tabelle (class-string<TEntry>::query()
     * würde den Template-Typ bei Larastan zu Model kollabieren).
     *
     * @return Builder<TEntry>
     */
    abstract protected function newEntryQuery(): Builder;

    /**
     * Service für den failed()-Pfad (handle() bekommt ihn injiziert).
     *
     * @return OutboxTransitionService<TEntry>
     */
    abstract protected function outboxService(): OutboxTransitionService;

    /**
     * Externe Zustellung über den aufgelösten Dispatcher; true = bestätigt.
     *
     * @param TDispatcher $dispatcher
     * @param TEntry $entry
     */
    abstract protected function dispatchEntry(PluginDispatcher $dispatcher, Model $entry): bool;

    /**
     * Fachliche Kompensation nach Aufbrauchen aller Versuche — Ziel bleibt je
     * Stack (Inbox-Fall bzw. PendingExternalConflict).
     *
     * @param TEntry $entry
     */
    abstract protected function compensateEntry(Model $entry, string $reason): void;

    /**
     * Vorprüfung vor der Zustellung (z. B. Modul-Gate); false → ohne
     * Zustellung beenden, der Eintrag bleibt offen für später.
     *
     * @param TEntry $entry
     */
    protected function shouldDeliver(Model $entry): bool {
        return true;
    }

    /**
     * Gemeinsamer Zustellablauf — die konkreten handle()-Methoden reichen ihre
     * typisiert injizierten Abhängigkeiten hierher durch.
     *
     * @param OutboxTransitionService<TEntry> $outbox
     * @param AbstractPluginDispatcherResolver<TDispatcher> $resolver
     */
    protected function deliver(OutboxTransitionService $outbox, AbstractPluginDispatcherResolver $resolver): void {
        $entry = $this->findEntry();
        if ($entry === null || ! $this->shouldDeliver($entry)) {
            return;
        }

        $dispatcher = $resolver->for($entry->plugin_id);
        if ($dispatcher === null) {
            // Kein Plugin registriert → kann nicht zugestellt werden. Nicht
            // endlos wiederholen; die Zustellung erfolgt mit dem Plugin.
            $outbox->markFailed($entry, 'kein Dispatcher für Plugin: ' . ($entry->plugin_id ?? '—'));

            return;
        }

        $outbox->markProcessing($entry);

        try {
            if ($this->dispatchEntry($dispatcher, $entry)) {
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
        $entry = $this->findEntry();
        if ($entry === null) {
            return;
        }

        $this->compensate($entry, $this->outboxService(), $e?->getMessage() ?? 'Zustellung fehlgeschlagen');
    }

    /**
     * @param TEntry $entry
     * @param OutboxTransitionService<TEntry> $outbox
     */
    private function compensate(Model $entry, OutboxTransitionService $outbox, string $reason): void {
        $outbox->markCompensationRequired($entry, $reason);
        $this->compensateEntry($entry, $reason);
    }

    /**
     * Entry-Refetch ohne globale Scopes; terminale Einträge sind erledigt.
     *
     * @return TEntry|null
     */
    private function findEntry(): ?Model {
        $entry = $this->newEntryQuery()->withoutGlobalScopes()->find($this->entryId);

        return $entry === null || $entry->status->isTerminal() ? null : $entry;
    }
}
