<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NextcloudIntakeClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Nextcloud\Api;

use App\Enums\CloudIntake\CloudIntakeItemStatus;
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentItem};
use App\Plugins\Nextcloud\Contracts\NextcloudTransportFactory;
use App\Plugins\Nextcloud\NextcloudConfig;
use App\Plugins\Support\Intake\{IntakeAccount, IntakeChangePage, IntakeContainer, IntakeItem};
use App\Services\CloudIntake\StaleCheckpointException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Nextcloud als LESENDE Quelle des Cloud-Dokumenteingangs (Feature 080,
 * MVP-382). Anders als Dropbox/Graph/Google garantiert WebDAV keinen
 * providerweiten Delta-Cursor — statt eines Server-Cursors führt der Adapter
 * einen budgetierten rekursiven Scan mit persistiertem Ordner-Checkpoint:
 *
 *  - Jede Seite besucht bis zu `scan_folder_budget` Ordner (PROPFIND Depth:1)
 *    und meldet ALLE gefundenen Dateien; die Idempotenz (Runner dedupt vor dem
 *    Download über `item_revision_hash`) macht das Wiedersehen unveränderter
 *    Dateien billig — es wird nichts erneut geladen.
 *  - Identität ist `oc:fileid` (+ ETag als Revision), nie der Pfad.
 *  - Tombstones (`source_gone`) entstehen beim Zyklus-Abschluss durch Abgleich
 *    der in diesem Zyklus gesehenen fileids gegen die vorhandenen Nachweise.
 *    Über `max_reconcile_files` hinaus wird der Abgleich ausgelassen (Flag
 *    `overflow`) statt still zu kürzen.
 *  - Ein unlesbarer Checkpoint wirft {@see StaleCheckpointException} → der
 *    Runner startet EINEN begrenzten Vollabgleich.
 */
class NextcloudIntakeClient {
    private readonly NextcloudWebdavClient $transport;

    public function __construct(private readonly CloudDocumentConnection $connection) {
        $this->transport = app(NextcloudTransportFactory::class)->forCredentials(
            (string) $connection->server_url,
            (string) $connection->username,
            (string) $connection->access_token,
        );
    }

    public function account(): IntakeAccount {
        if (! $this->transport->ping()) {
            throw new RuntimeException('Nextcloud-Anmeldung fehlgeschlagen (PROPFIND ohne 207).');
        }

        $host = $this->transport->serverHost();

        return new IntakeAccount(
            externalId: $host . '|' . (string) $this->connection->username,
            label: (string) $this->connection->username . ' @ ' . $host,
        );
    }

    /**
     * Ein Namespace (persönliche Ablage); Stammordner wählt der Admin.
     *
     * @return list<IntakeContainer>
     */
    public function containers(): array {
        return [new IntakeContainer('files', 'Nextcloud', 'files')];
    }

    public function changes(?string $checkpoint): IntakeChangePage {
        $rootPath = trim((string) $this->connection->root_folder_path, '/');
        [$queue, $seen, $overflow] = $this->decodeCheckpoint($checkpoint);

        $config = NextcloudConfig::resolve();
        $budget = max(1, (int) $config['scan_folder_budget']);
        $maxReconcile = max(0, (int) $config['max_reconcile_files']);

        $items = [];
        while ($budget-- > 0 && $queue !== []) {
            $folderRel = (string) array_shift($queue);
            try {
                $children = $this->transport->listChildren($this->joinRoot($rootPath, $folderRel));
            } catch (NextcloudNotFoundException $e) {
                if ($folderRel === '') {
                    // Fehlender STAMMordner ist ein Verbindungsfehler, nie „alles gelöscht".
                    throw new RuntimeException('Nextcloud-Stammordner nicht gefunden: /' . $rootPath, 0, $e);
                }

                continue; // Unterordner verschwunden (Race) — tolerieren.
            }

            foreach ($children as $child) {
                $name = $this->basename($child['path']);
                $childRel = $folderRel === '' ? $name : $folderRel . '/' . $name;

                if ($child['is_dir']) {
                    $queue[] = $childRel;

                    continue;
                }

                $fileId = $child['fileid'] !== '' ? $child['fileid'] : 'path:' . $child['path'];
                if (! $overflow) {
                    $seen[$fileId] = true;
                    if (count($seen) > $maxReconcile) {
                        $overflow = true; // Reconcile-Budget überschritten → kein Tombstoning
                        $seen = [];
                    }
                }

                $items[] = new IntakeItem(
                    itemId: $fileId,
                    path: $childRel,
                    name: $name,
                    revision: $child['etag'] !== '' ? $child['etag'] : (string) $child['size'],
                    size: $child['size'],
                    mime: $child['mime'],
                    modifiedAt: $child['modified'],
                );
            }
        }

        $done = $queue === [];
        $tombstones = ($done && ! $overflow) ? $this->reconcileTombstones($seen) : [];

        $nextCheckpoint = json_encode(
            $done
                ? ['queue' => [], 'seen' => [], 'overflow' => false]
                : ['queue' => $queue, 'seen' => array_keys($seen), 'overflow' => $overflow],
            JSON_THROW_ON_ERROR,
        );

        return new IntakeChangePage($items, $tombstones, $nextCheckpoint, hasMore: ! $done);
    }

    public function download(IntakeItem $item): StreamInterface {
        $rootPath = trim((string) $this->connection->root_folder_path, '/');

        return $this->transport->getStream($this->joinRoot($rootPath, $item->path));
    }

    /**
     * @return array{0: list<string>, 1: array<string, true>, 2: bool}
     */
    private function decodeCheckpoint(?string $checkpoint): array {
        if ($checkpoint === null || $checkpoint === '') {
            return [[''], [], false]; // frischer Zyklus ab Stammordner
        }

        $decoded = json_decode($checkpoint, true);
        if (! is_array($decoded) || ! array_key_exists('queue', $decoded)) {
            throw new StaleCheckpointException('Nextcloud-Checkpoint ungültig.');
        }

        $queue = array_values(array_filter((array) ($decoded['queue'] ?? []), 'is_string'));
        if ($queue === []) {
            return [[''], [], false]; // vorheriger Zyklus abgeschlossen → neuer Scan
        }

        $seen = [];
        foreach ((array) ($decoded['seen'] ?? []) as $fileId) {
            if (is_string($fileId)) {
                $seen[$fileId] = true;
            }
        }

        return [$queue, $seen, (bool) ($decoded['overflow'] ?? false)];
    }

    /**
     * @param  array<string, true>  $seen
     * @return list<string>
     */
    private function reconcileTombstones(array $seen): array {
        $tombstones = [];
        CloudDocumentItem::query()
            ->where('connection_id', $this->connection->id)
            ->where('status', '!=', CloudIntakeItemStatus::SourceGone->value)
            ->distinct()
            ->pluck('external_item_id')
            ->each(function ($fileId) use ($seen, &$tombstones): void {
                $fileId = (string) $fileId;
                if ($fileId !== '' && ! isset($seen[$fileId])) {
                    $tombstones[] = $fileId;
                }
            });

        return array_values(array_unique($tombstones));
    }

    private function joinRoot(string $rootPath, string $rel): string {
        $rel = trim($rel, '/');
        if ($rootPath === '') {
            return $rel;
        }

        return $rel === '' ? $rootPath : $rootPath . '/' . $rel;
    }

    private function basename(string $path): string {
        $path = rtrim($path, '/');
        $pos = strrpos($path, '/');

        return $pos === false ? $path : substr($path, $pos + 1);
    }
}
