<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudIntakeRunner.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\CloudIntake;

use App\Enums\CloudIntake\CloudIntakeItemStatus;
use App\Models\{AuditLog, User};
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentItem};
use App\Plugins\Contracts\DocumentIntakeSource;
use App\Plugins\PluginManager;
use App\Plugins\Support\Intake\IntakeItem;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Intake-Pipeline des Cloud-Dokumenteingangs (Feature 080, MVP-356):
 * budgetierter, wiederanlaufbarer Lauf je Verbindung.
 *
 *  - **Lease:** Cache-Lock je Verbindung — Webhook und Scheduler dürfen
 *    denselben Lauf mehrfach anstoßen, verarbeitet wird ein Cursor nie
 *    parallel.
 *  - **Checkpoint-Disziplin:** erst nach vollständig verarbeiteter Seite
 *    fortgeschrieben; {@see StaleCheckpointException} ⇒ genau EIN begrenzter
 *    Vollabgleich (Idempotenz über Übergabenachweise), kein blinder
 *    Neuimport.
 *  - **Quarantäne:** Download in einen temporären Bereich mit Größenbudget,
 *    Endungs-/MIME-Blockliste (Archive/Executables), SHA-256; Klartext der
 *    Quelle verlässt die Quarantäne nur Richtung Zielimport.
 *  - **Dedup:** je Item-Revision (Unique) und kanalübergreifend über SHA-256.
 *  - Fehler ⇒ {@see \App\Models\Concerns\HasConnectionHealth} + sichtbares
 *    Datenalter; Tombstones markieren nur Nachweise (`source_gone`).
 */
class CloudIntakeRunner {
    public function __construct(
        private readonly RoutePatternValidator $patterns,
        private readonly CloudIntakeRouter $router,
    ) {}

    /**
     * @return array{status: string, pages: int, imported: int, inbox: int, rejected: int, duplicates: int, skipped: int, tombstones: int}
     */
    public function run(CloudDocumentConnection $connection, ?DocumentIntakeSource $adapter = null): array {
        $result = ['status' => 'ok', 'pages' => 0, 'imported' => 0, 'inbox' => 0, 'rejected' => 0, 'duplicates' => 0, 'skipped' => 0, 'tombstones' => 0];

        if (! $connection->isRunnable()) {
            $result['status'] = 'not_runnable';

            return $result;
        }

        $adapter ??= $this->resolveAdapter($connection);
        if ($adapter === null) {
            $connection->recordConnectionFailure('adapter_missing');
            $result['status'] = 'no_adapter';

            return $result;
        }

        $actor = $connection->creator;
        if (! $actor instanceof User) {
            $connection->recordConnectionFailure('actor_missing');
            $result['status'] = 'no_actor';

            return $result;
        }

        $lock = Cache::lock('cloud-intake:run:' . $connection->id, 900);
        if (! $lock->get()) {
            $result['status'] = 'locked';

            return $result;
        }

        // Org-Kontext binden (Scheduler-Lauf) und danach sauber zurückstellen
        // (Queue-/Scheduler-Org-Hygiene).
        $previousOrg = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        app()->instance('currentOrganization', $connection->organization);

        try {
            $this->pump($connection, $adapter, $actor, $result);
        } finally {
            if ($previousOrg instanceof \App\Models\Organization) {
                app()->instance('currentOrganization', $previousOrg);
            } else {
                app()->forgetInstance('currentOrganization');
            }
            $lock->release();
        }

        return $result;
    }

    /**
     * @param  array{status: string, pages: int, imported: int, inbox: int, rejected: int, duplicates: int, skipped: int, tombstones: int}  $result
     */
    private function pump(CloudDocumentConnection $connection, DocumentIntakeSource $adapter, User $actor, array &$result): void {
        $routes = $connection->routes()->where('active', true)->orderBy('priority')->get();
        $checkpoint = $connection->checkpoint;
        $maxPages = max(1, (int) config('cloud_intake.max_pages_per_run', 10));
        $resynced = false;

        while ($result['pages'] < $maxPages) {
            try {
                $page = $adapter->intakeChanges($connection, $checkpoint);
            } catch (StaleCheckpointException) {
                if ($resynced) {
                    $connection->recordConnectionFailure('stale_checkpoint');
                    $result['status'] = 'failed';

                    return;
                }
                // Begrenzter Vollabgleich: Cursor verwerfen, Nachweise dedupen.
                $resynced = true;
                $checkpoint = null;

                continue;
            } catch (Throwable $e) {
                $connection->recordConnectionFailure(class_basename($e));
                $result['status'] = 'failed';

                return;
            }

            foreach ($page->items as $item) {
                $this->processItem($connection, $adapter, $actor, $routes, $item, $result);
            }
            $result['tombstones'] += $this->applyTombstones($connection, $page->tombstones);

            // Seite vollständig verarbeitet ⇒ Checkpoint gilt.
            $checkpoint = $page->checkpoint;
            $connection->forceFill([
                'checkpoint' => $checkpoint,
                'last_run_at' => Carbon::now(),
            ])->save();
            $connection->recordConnectionSuccess();
            $result['pages']++;

            if (! $page->hasMore) {
                return;
            }
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Models\CloudIntake\CloudDocumentRoute>  $routes
     * @param  array{status: string, pages: int, imported: int, inbox: int, rejected: int, duplicates: int, skipped: int, tombstones: int}  $result
     */
    private function processItem(CloudDocumentConnection $connection, DocumentIntakeSource $adapter, User $actor, $routes, IntakeItem $item, array &$result): void {
        // 1. Regel-Treffer (Priorität, Endungs-/Größenfilter der Route).
        $extension = strtolower(pathinfo($item->name, PATHINFO_EXTENSION));
        $match = $this->patterns->firstMatch($routes, $item->path, $extension, $item->size);
        if ($match === null) {
            $result['skipped']++; // außerhalb der Regeln — kein Nachweis

            return;
        }

        // 2. Idempotenz je Item-Revision.
        $hash = CloudDocumentItem::itemRevisionHash($item->itemId, $item->revision);
        $exists = CloudDocumentItem::query()
            ->where('connection_id', $connection->id)
            ->where('item_revision_hash', $hash)
            ->exists();
        if ($exists) {
            $result['skipped']++;

            return;
        }

        // 3. Globale Blocklisten/Grenzen VOR dem Download.
        if (in_array($extension, (array) config('cloud_intake.blocked_extensions', []), true)) {
            $this->record($connection, $match['route']->id, $item, null, CloudIntakeItemStatus::Rejected, 'blocked_extension', null, $actor);
            $result['rejected']++;

            return;
        }
        $maxSize = (int) config('cloud_intake.max_file_size', 52_428_800);
        if ($item->size > $maxSize) {
            $this->record($connection, $match['route']->id, $item, null, CloudIntakeItemStatus::Rejected, 'too_large', null, $actor);
            $result['rejected']++;

            return;
        }

        // 4. Quarantäne-Download mit Größenbudget.
        $quarantine = null;
        try {
            $quarantine = $this->downloadToQuarantine($connection, $adapter, $item, $maxSize);
            if ($quarantine === null) {
                $this->record($connection, $match['route']->id, $item, null, CloudIntakeItemStatus::Rejected, 'too_large', null, $actor);
                $result['rejected']++;

                return;
            }

            // 5. MIME-Prüfung auf dem tatsächlichen Inhalt.
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo !== false ? (string) (finfo_file($finfo, $quarantine) ?: '') : '';
            if (in_array($mime, (array) config('cloud_intake.blocked_mimes', []), true)) {
                $this->record($connection, $match['route']->id, $item, null, CloudIntakeItemStatus::Rejected, 'blocked_mime', null, $actor);
                $result['rejected']++;

                return;
            }

            // 6. Kanalübergreifender Inhalts-Dedup (Mail/Upload/Cloud).
            $sha256 = (string) hash_file('sha256', $quarantine);
            $contentDuplicate = CloudDocumentItem::query()
                ->where('organization_id', $connection->organization_id)
                ->where('sha256', $sha256)
                ->where('status', CloudIntakeItemStatus::Imported->value)
                ->exists();
            if ($contentDuplicate) {
                $this->record($connection, $match['route']->id, $item, $sha256, CloudIntakeItemStatus::Duplicate, 'content_duplicate', null, $actor);
                $result['duplicates']++;

                return;
            }

            // 7. Ziel-Übergabe über die bestehenden Pipelines.
            try {
                $routed = $this->router->route($connection, $match['route'], $match['variables'], $item, $quarantine, $actor);
            } catch (Throwable $e) {
                $this->record($connection, $match['route']->id, $item, $sha256, CloudIntakeItemStatus::Rejected, class_basename($e), null, $actor);
                $result['rejected']++;

                return;
            }

            // 8. Übergabenachweis + Zähler.
            $this->record($connection, $match['route']->id, $item, $sha256, $routed['status'], $routed['reason'], $routed['imported'], $actor);
            match ($routed['status']) {
                CloudIntakeItemStatus::Imported => $result['imported']++,
                CloudIntakeItemStatus::Inbox => $result['inbox']++,
                CloudIntakeItemStatus::Duplicate => $result['duplicates']++,
                default => $result['rejected']++,
            };
        } finally {
            if ($quarantine !== null && is_file($quarantine)) {
                @unlink($quarantine);
            }
        }
    }

    /** Download mit hartem Byte-Budget in den Quarantäne-Bereich. */
    private function downloadToQuarantine(CloudDocumentConnection $connection, DocumentIntakeSource $adapter, IntakeItem $item, int $maxSize): ?string {
        $dir = storage_path('app/cloud-intake-quarantine');
        if (! is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        $path = $dir . '/' . Str::uuid()->toString();

        $stream = $adapter->intakeDownload($connection, $item);
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Quarantäne-Datei nicht beschreibbar.');
        }

        $written = 0;
        try {
            while (! $stream->eof()) {
                $chunk = $stream->read(8192);
                if ($chunk === '') {
                    break;
                }
                $written += strlen($chunk);
                if ($written > $maxSize) {
                    return null; // Budget überschritten — Datei verwerfen
                }
                fwrite($handle, $chunk);
            }
        } finally {
            fclose($handle);
            if ($written > $maxSize && is_file($path)) {
                @unlink($path);
            }
        }

        return $written > $maxSize ? null : $path;
    }

    /**
     * Übergabenachweis schreiben (+ Audit für Import/Ablehnung); die
     * Unique-Verletzung eines parallelen Laufs zählt als bereits verarbeitet.
     */
    private function record(
        CloudDocumentConnection $connection,
        ?int $routeId,
        IntakeItem $item,
        ?string $sha256,
        CloudIntakeItemStatus $status,
        ?string $reason,
        ?object $imported,
        User $actor,
    ): void {
        try {
            CloudDocumentItem::query()->create([
                'organization_id' => $connection->organization_id,
                'connection_id' => $connection->id,
                'route_id' => $routeId,
                'provider' => $connection->provider,
                'external_item_id' => $item->itemId,
                'revision' => $item->revision,
                'source_path' => $item->path,
                'sha256' => $sha256,
                'size' => $item->size,
                'status' => $status,
                'status_reason' => $reason,
                'target' => null,
                'imported_type' => $imported instanceof \Illuminate\Database\Eloquent\Model ? $imported->getMorphClass() : null,
                'imported_id' => $imported instanceof \Illuminate\Database\Eloquent\Model ? $imported->getKey() : null,
                'imported_at' => $status === CloudIntakeItemStatus::Imported ? Carbon::now() : null,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if (! str_contains(strtolower($e->getMessage()), 'unique')) {
                throw $e;
            }

            return;
        }

        if (in_array($status, [CloudIntakeItemStatus::Imported, CloudIntakeItemStatus::Rejected], true)) {
            AuditLog::query()->create([
                'organization_id' => $connection->organization_id,
                'user_id' => $actor->id,
                'event' => $status === CloudIntakeItemStatus::Imported ? 'cloudIntake.imported' : 'cloudIntake.rejected',
                'auditable_type' => CloudDocumentConnection::class,
                'auditable_id' => $connection->id,
                'changes' => [
                    'item' => $item->itemId,
                    'revision' => $item->revision,
                    'path' => $item->path,
                    'reason' => $reason,
                ],
            ]);
        }
    }

    /**
     * Tombstones markieren nur Nachweise (`source_gone`) — lokale Dokumente
     * werden NIE automatisch gelöscht. `path:`-Tombstones (Dropbox) matchen
     * den Quellpfad, sonst gilt die Provider-Item-ID.
     *
     * @param  list<string>  $tombstones
     */
    private function applyTombstones(CloudDocumentConnection $connection, array $tombstones): int {
        $marked = 0;

        foreach ($tombstones as $tombstone) {
            $query = CloudDocumentItem::query()
                ->where('connection_id', $connection->id)
                ->where('status', '!=', CloudIntakeItemStatus::SourceGone->value);

            if (str_starts_with($tombstone, 'path:')) {
                $path = mb_strtolower(substr($tombstone, 5));
                $query->whereRaw('LOWER(source_path) = ?', [$path]);
            } else {
                $query->where('external_item_id', $tombstone);
            }

            $query->get()->each(function (CloudDocumentItem $evidence) use (&$marked): void {
                $evidence->update(['status' => CloudIntakeItemStatus::SourceGone, 'status_reason' => 'tombstone']);
                $marked++;
            });
        }

        return $marked;
    }

    private function resolveAdapter(CloudDocumentConnection $connection): ?DocumentIntakeSource {
        $plugin = app(PluginManager::class)->find($connection->provider->pluginId());

        return $plugin instanceof DocumentIntakeSource ? $plugin : null;
    }
}
