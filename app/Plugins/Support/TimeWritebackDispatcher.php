<?php
/*
 * Created on   : Tue Jul 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeWritebackDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use App\Contracts\Integration\IntegrationOutboxDispatcher;
use App\Models\{ExternalReference, IntegrationInboxItem, IntegrationOutboxEntry, TimeEntry};

/**
 * Gemeinsame Rückrichtung der Zeit-Plugins: schreibt lokale Korrekturen an
 * importierten Zeiten ins Fremdsystem zurück (Änderung und Löschung, keine
 * Neuanlage — die entsteht dort).
 *
 * Vor jedem Schreibzugriff wird der aktuelle Fremdstand geholt und gegen den
 * beim Import hinterlegten Fingerabdruck geprüft. Weicht er ab, hat drüben
 * jemand nachgearbeitet: dann wird **nicht** überschrieben, sondern ein
 * Konflikt in die Integrations-Inbox gestellt. Ohne diese Prüfung würde die
 * Rückrichtung fremde Korrekturen still verwerfen.
 *
 * Konkrete Plugins liefern nur noch Kennung, Schreibzugang und Freischaltung.
 */
abstract class TimeWritebackDispatcher implements IntegrationOutboxDispatcher {
    /** Operationen der Outbox — `<plugin>.time_entry.update|delete`. */
    public const OP_SUFFIX_UPDATE = '.time_entry.update';

    public const OP_SUFFIX_DELETE = '.time_entry.delete';

    final public static function updateOperation(string $pluginId): string {
        return $pluginId . self::OP_SUFFIX_UPDATE;
    }

    final public static function deleteOperation(string $pluginId): string {
        return $pluginId . self::OP_SUFFIX_DELETE;
    }

    /**
     * Operation, die `InboxActionService::keepLocal()` generisch enqueued
     * (`<model>.update`). Für Zeiteinträge ist das die Ansage „der lokale Stand
     * gilt auch drüben" — sie muss den Fingerabdruck-Riegel überstimmen, sonst
     * meldet der Rückkanal denselben Konflikt sofort wieder.
     */
    private const OP_INBOX_KEEP_LOCAL = 'timeentry.update';

    /** Schreibzugang der Organisation; null = nicht konfiguriert/deaktiviert. */
    abstract protected function writer(int $organizationId): ?RemoteTimeWriter;

    /** Ist die Rückrichtung für diese Organisation freigeschaltet? */
    abstract public function writebackEnabled(int $organizationId): bool;

    public function dispatch(IntegrationOutboxEntry $entry): bool {
        return match ($entry->operation) {
            self::updateOperation($this->pluginId()) => $this->dispatchUpdate($entry),
            self::deleteOperation($this->pluginId()) => $this->dispatchDelete($entry),
            self::OP_INBOX_KEEP_LOCAL => $this->dispatchUpdate($entry, force: true),
            // Die Inbox enqueued bei „lokal behalten" generisch `<model>.update`
            // für jedes Plugin mit Dispatcher. Zeit-Plugins spiegeln nur
            // Zeiteinträge — für alles andere gibt es keinen Rückkanal, der Fall
            // ist mit der lokalen Auflösung erledigt.
            default => true,
        };
    }

    private function dispatchUpdate(IntegrationOutboxEntry $outbox, bool $force = false): bool {
        $payload = $outbox->payload;
        $reference = $this->resolveReference($outbox);
        $timeEntry = $reference?->referenceable;
        if (! $reference instanceof ExternalReference || ! $timeEntry instanceof TimeEntry) {
            // Lokal gelöscht oder Verknüpfung entfernt — die Löschung kommt als
            // eigener Outbox-Eintrag.
            return true;
        }

        $writer = $this->writer($outbox->organization_id);
        if ($writer === null) {
            return true; // Zugang weg → nichts zurückzuschreiben
        }

        $context = (array) $reference->payload;
        $externalId = RemoteEntryKey::externalId((string) $reference->external_id);
        if ($externalId === null) {
            return true; // CSV-Import: kein adressierbares Gegenstück im Fremdsystem
        }

        // Nach einer Inbox-Entscheidung („lokal behalten") wird bewusst ohne
        // Prüfung geschrieben — jemand hat den Konflikt gesehen und entschieden.
        if (! $force) {
            $remote = $this->divergingRemoteState($writer, $reference, $externalId, $context);
            if ($remote !== null) {
                $this->raiseConflict($outbox, $reference, $timeEntry, $remote);

                return true; // Konflikt gemeldet — kein Überschreiben
            }
        }

        $state = $this->localState($timeEntry);

        if (! $writer->pushEntryUpdate($externalId, $state, $context)) {
            return false; // Outbox wiederholt es
        }

        $this->rememberFingerprint($reference, $writer->fingerprintOf($state, $context));

        return true;
    }

    private function dispatchDelete(IntegrationOutboxEntry $outbox): bool {
        $payload = $outbox->payload;
        $externalId = RemoteEntryKey::externalId((string) ($payload['external_id'] ?? ''));
        $writer = $this->writer($outbox->organization_id);
        if ($writer === null || $externalId === null) {
            return true;
        }

        // Bei der Löschung wird bewusst nicht auf Abweichung geprüft: der lokale
        // Eintrag ist weg, ein Vergleichsstand existiert nicht mehr.
        return $writer->pushEntryDeletion($externalId, (array) ($payload['context'] ?? []));
    }

    /**
     * Fremdstand, falls er vom hinterlegten Abdruck abweicht — sonst null.
     *
     * @param  array<string, mixed>  $context
     * @return array{description: ?string, date: ?\Carbon\CarbonImmutable, started_at: ?\Carbon\CarbonImmutable, ended_at: ?\Carbon\CarbonImmutable, minutes: int, billable: bool}|null
     */
    private function divergingRemoteState(RemoteTimeWriter $writer, ExternalReference $reference, string $externalId, array $context): ?array {
        $known = is_array($reference->payload) ? (string) ($reference->payload['fingerprint'] ?? '') : '';
        if ($known === '') {
            return null; // Altbestand ohne Fingerabdruck — nicht blockieren
        }

        // Nicht erreichbar oder gelöscht: kein Konflikt behaupten, der nächste
        // Lauf versucht es erneut.
        $remote = $writer->fetchRemoteState($externalId, $context);
        if ($remote === null) {
            return null;
        }

        return $writer->fingerprintOf($remote, $context) === $known ? null : $remote;
    }

    /**
     * @param  array{description: ?string, date: ?\Carbon\CarbonImmutable, started_at: ?\Carbon\CarbonImmutable, ended_at: ?\Carbon\CarbonImmutable, minutes: int, billable: bool}  $remote
     */
    private function raiseConflict(IntegrationOutboxEntry $outbox, ExternalReference $reference, TimeEntry $timeEntry, array $remote): void {
        // `mapped_snapshot` + `diff_fields` sind das, womit die Inbox arbeitet:
        // ohne sie übernähme „Fremdstand übernehmen" nichts und der Fall wäre
        // eine Sackgasse.
        $mapped = array_filter([
            'description' => $remote['description'],
            'started_at' => $remote['started_at']?->toDateTimeString(),
            'ended_at' => $remote['ended_at']?->toDateTimeString(),
            'minutes' => $remote['minutes'],
        ], static fn ($v): bool => $v !== null);

        $local = [
            'description' => $timeEntry->description,
            'started_at' => $timeEntry->started_at?->toDateTimeString(),
            'ended_at' => $timeEntry->ended_at?->toDateTimeString(),
            'minutes' => (int) $timeEntry->minutes,
        ];
        $diff = array_keys(array_filter($mapped, static fn ($v, string $k): bool => ($local[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH));

        IntegrationInboxItem::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'organization_id' => $outbox->organization_id,
                'plugin_id' => $this->pluginId(),
                'dedupe_key' => $this->pluginId() . '-entry-conflict:' . $reference->external_id,
            ],
            [
                'source' => $this->pluginId(),
                'target_type' => TimeEntry::class,
                'external_type' => MatchingTimeImportService::EXT_TYPE_ENTRY,
                'external_id' => (string) $reference->external_id,
                'case_type' => IntegrationInboxItem::CASE_CONFLICT,
                'referenceable_type' => $timeEntry->getMorphClass(),
                'referenceable_id' => $timeEntry->getKey(),
                'status' => IntegrationInboxItem::STATUS_OPEN,
                'remote_snapshot' => [
                    'reason' => 'remote_changed',
                    'time_entry_id' => $timeEntry->getKey(),
                    'remote' => $mapped,
                    'local' => $local,
                ],
                'mapped_snapshot' => $mapped,
                'diff_fields' => $diff !== [] ? $diff : null,
                'local_snapshot' => $local,
            ],
        );
    }

    /**
     * Referenz zum Outbox-Eintrag: der Observer schickt die lokale ID,
     * `InboxActionService::keepLocal()` dagegen nur die Fremd-ID.
     */
    private function resolveReference(IntegrationOutboxEntry $outbox): ?ExternalReference {
        $payload = $outbox->payload;

        $query = ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $outbox->organization_id)
            ->where('plugin_id', $this->pluginId())
            ->where('external_type', MatchingTimeImportService::EXT_TYPE_ENTRY)
            ->where('referenceable_type', (new TimeEntry)->getMorphClass());

        if (isset($payload['time_entry_id'])) {
            return $query->where('referenceable_id', (int) $payload['time_entry_id'])->first();
        }

        $externalId = trim((string) ($payload['external_id'] ?? ''));

        return $externalId !== '' ? $query->where('external_id', $externalId)->first() : null;
    }

    /**
     * Lokaler Stand in der Form, die die Writer erwarten.
     *
     * @return array{description: ?string, date: ?\Carbon\CarbonImmutable, started_at: ?\Carbon\CarbonImmutable, ended_at: ?\Carbon\CarbonImmutable, minutes: int, billable: bool}
     */
    private function localState(TimeEntry $timeEntry): array {
        return [
            'description' => $timeEntry->description,
            // Systeme ohne Start-/Stoppzeiten (OpenProject) buchen auf das Datum.
            'date' => $timeEntry->date?->toImmutable() ?? $timeEntry->started_at?->toImmutable(),
            'started_at' => $timeEntry->started_at?->toImmutable(),
            'ended_at' => $timeEntry->ended_at?->toImmutable(),
            'minutes' => (int) $timeEntry->minutes,
            'billable' => (bool) $timeEntry->billable,
        ];
    }

    /** Nach erfolgreichem Schreiben ist der lokale Stand der Fremdstand. */
    private function rememberFingerprint(ExternalReference $reference, ?string $fingerprint): void {
        if ($fingerprint === null) {
            return;
        }

        $reference->payload = array_merge((array) $reference->payload, ['fingerprint' => $fingerprint]);
        $reference->synced_at = now();
        $reference->save();
    }

}
