<?php
/*
 * Created on   : Tue Jul 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeWritebackObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use App\Models\{ExternalReference, TimeEntry};
use App\Services\Integration\{IntegrationOutboxDispatcherResolver, IntegrationOutboxService};

/**
 * Stellt lokale Korrekturen an importierten Zeiten in die Outbox — für **alle**
 * Zeit-Plugins mit registriertem {@see TimeWritebackDispatcher}.
 *
 * Ein Eintrag kann aus mehreren Quellen stammen; jede verknüpfte Quelle mit
 * freigeschalteter Rückrichtung bekommt ihren eigenen Outbox-Eintrag.
 *
 * Nicht zurückgeschrieben wird:
 * - während des Imports ({@see suppressed()}) — sonst schwingt jeder importierte
 *   Eintrag sofort zurück;
 * - bei exportierten/abgerechneten Zeiten — sie hängen an Belegen, deren
 *   Grundlage sich nicht nachträglich im Fremdsystem ändern darf.
 */
class TimeWritebackObserver {
    /** Felder, deren Änderung eine Rückschreibung auslöst. */
    private const MIRRORED = ['description', 'started_at', 'ended_at', 'minutes', 'billable'];

    private static bool $suppressed = false;

    /** Während des Imports keine Outbox-Einträge erzeugen (Rückkopplung). */
    public static function suppressed(callable $callback): mixed {
        $previous = self::$suppressed;
        self::$suppressed = true;

        try {
            return $callback();
        } finally {
            self::$suppressed = $previous;
        }
    }

    public function updated(TimeEntry $entry): void {
        if (self::$suppressed || $entry->exported) {
            return;
        }

        if (array_intersect(array_keys($entry->getChanges()), self::MIRRORED) === []) {
            return;
        }

        foreach ($this->linkedWritebacks($entry) as [$dispatcher, $reference]) {
            app(IntegrationOutboxService::class)->enqueue(
                (int) $entry->organization_id,
                $dispatcher->pluginId(),
                TimeWritebackDispatcher::updateOperation($dispatcher->pluginId()),
                ['time_entry_id' => $entry->getKey()],
                // Je Eintrag und Änderungsstand genau einmal.
                $dispatcher->pluginId() . '-entry-update:' . $reference->external_id . ':' . $entry->updated_at?->getTimestamp(),
                $entry,
            );
        }
    }

    public function deleted(TimeEntry $entry): void {
        if (self::$suppressed || $entry->exported) {
            return;
        }

        foreach ($this->linkedWritebacks($entry) as [$dispatcher, $reference]) {
            app(IntegrationOutboxService::class)->enqueue(
                (int) $entry->organization_id,
                $dispatcher->pluginId(),
                TimeWritebackDispatcher::deleteOperation($dispatcher->pluginId()),
                [
                    'external_id' => (string) $reference->external_id,
                    // Der Kontext (Workspace o. ä.) steckt in der Referenz, die mit
                    // dem Eintrag verschwinden kann — deshalb hier mitgeben.
                    'context' => (array) $reference->payload,
                ],
                $dispatcher->pluginId() . '-entry-delete:' . $reference->external_id,
                $entry,
            );
        }
    }

    /**
     * Verknüpfte Quellen des Eintrags, die zurückschreiben können und dürfen.
     *
     * Erst die Dispatcher-Registry fragen: ohne Rückkanal fällt die
     * Referenz-Abfrage komplett weg — der Observer läuft bei jeder
     * Zeiteintrags-Änderung.
     *
     * @return list<array{0: TimeWritebackDispatcher, 1: ExternalReference}>
     */
    private function linkedWritebacks(TimeEntry $entry): array {
        $organizationId = (int) $entry->organization_id;

        $dispatchers = [];
        foreach (app(IntegrationOutboxDispatcherResolver::class)->all() as $dispatcher) {
            if ($dispatcher instanceof TimeWritebackDispatcher && $dispatcher->writebackEnabled($organizationId)) {
                $dispatchers[$dispatcher->pluginId()] = $dispatcher;
            }
        }

        if ($dispatchers === []) {
            return [];
        }

        $references = ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('plugin_id', array_keys($dispatchers))
            ->where('external_type', MatchingTimeImportService::EXT_TYPE_ENTRY)
            ->forReferenceable($entry)
            ->get();

        $out = [];
        foreach ($references as $reference) {
            $dispatcher = $dispatchers[$reference->plugin_id] ?? null;
            // CSV-Importe tragen keinen Fremd-Schlüssel — nichts zurückzuschreiben.
            if ($dispatcher !== null && RemoteEntryKey::externalId((string) $reference->external_id) !== null) {
                $out[] = [$dispatcher, $reference];
            }
        }

        return $out;
    }
}
