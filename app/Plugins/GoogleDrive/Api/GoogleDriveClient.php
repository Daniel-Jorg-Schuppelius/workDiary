<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleDriveClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\GoogleDrive\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2BearerAuthentication;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\GoogleDrive\{GoogleDriveConfig, GoogleDrivePlugin};
use App\Plugins\Support\{ConnectionTokenStore, PluginHttpFactory};
use App\Plugins\Support\Intake\{IntakeAccount, IntakeChangePage, IntakeContainer, IntakeItem};
use App\Services\CloudIntake\StaleCheckpointException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Google-Drive-Gateway (Feature 080, MVP-355). Zweiphasiger Checkpoint
 * (JSON-String):
 *
 *  1. `{"phase":"initial","pageToken":…,"startPageToken":…}` — Erstabgleich
 *     über `files.list` (Seiten); der `startPageToken` wird VOR dem
 *     Erstlauf eingefroren, damit zwischenzeitliche Änderungen später vom
 *     Delta abgedeckt sind.
 *  2. `{"phase":"delta","pageToken":…}` — `changes.list` inkl. Shared-Drive-
 *     Flags; `removed`/`trashed` ⇒ ID-Tombstone.
 *
 * Pfade: Drive liefert nur Parent-IDs — {@see folderPath()} löst die
 * Ordnerkette lazy (memoisiert je Client-Instanz) bis zum Stammordner auf.
 * Google-native Formate (application/vnd.google-apps.*) werden übersprungen.
 * HTTP 404 auf einen pageToken bzw. „Invalid Value" ⇒
 * {@see StaleCheckpointException} (begrenzter Vollabgleich).
 */
class GoogleDriveClient {
    private \App\Plugins\Support\PluginApiClient $api;

    private string $base;

    /** @var array<string, array{name: string, parent: string|null}|null> */
    private array $folderCache = [];

    public function __construct(private readonly CloudDocumentConnection $connection) {
        $config = GoogleDriveConfig::resolve();
        $this->base = $config['api_base'];
        $this->api = app(PluginHttpFactory::class)->client(GoogleDrivePlugin::ID, $this->base);

        $grant = GoogleDriveConfig::isConfigured() ? app(GoogleDriveOAuth::class)->grant() : null;
        $this->api->setAuthentication(new OAuth2BearerAuthentication(new ConnectionTokenStore($connection, 'granted_scopes', scopeAsArray: true), $grant));
    }

    public function account(): IntakeAccount {
        $response = $this->api->getResponse($this->base . '/about', ['fields' => 'user(permissionId,displayName,emailAddress)']);
        if (! $response->successful()) {
            throw new RuntimeException('Drive /about fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        /** @var array{user?: array{permissionId?: string, displayName?: string, emailAddress?: string}} $data */
        $data = (array) $response->json();

        return new IntakeAccount(
            externalId: (string) ($data['user']['permissionId'] ?? ''),
            label: trim((string) ($data['user']['displayName'] ?? '') . ' <' . (string) ($data['user']['emailAddress'] ?? '') . '>'),
        );
    }

    /**
     * „Meine Ablage" + zugängliche Shared Drives.
     *
     * @return list<IntakeContainer>
     */
    public function containers(): array {
        $containers = [new IntakeContainer('my-drive', 'Meine Ablage', 'myDrive')];

        $response = $this->api->getResponse($this->base . '/drives', ['pageSize' => 100]);
        if ($response->successful()) {
            /** @var array{drives?: list<array{id?: string, name?: string}>} $data */
            $data = (array) $response->json();
            foreach ((array) ($data['drives'] ?? []) as $drive) {
                $containers[] = new IntakeContainer(
                    id: (string) ($drive['id'] ?? ''),
                    label: (string) ($drive['name'] ?? 'Shared Drive'),
                    kind: 'sharedDrive',
                );
            }
        }

        return $containers;
    }

    public function changes(?string $checkpoint): IntakeChangePage {
        $state = $this->decodeCheckpoint($checkpoint);

        return $state['phase'] === 'initial'
            ? $this->initialPage($state)
            : $this->deltaPage($state);
    }

    public function download(IntakeItem $item): StreamInterface {
        $response = $this->api->getResponse(
            $this->base . '/files/' . rawurlencode($item->itemId),
            ['alt' => 'media', 'supportsAllDrives' => 'true'],
        );

        if (! $response->successful()) {
            throw new RuntimeException('Drive-Download fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return $response->toPsrResponse()->getBody();
    }

    // ── Phase 1: Erstabgleich ───────────────────────────────────────────

    /**
     * @param  array{phase: string, pageToken: string|null, startPageToken: string|null}  $state
     */
    private function initialPage(array $state): IntakeChangePage {
        $config = GoogleDriveConfig::resolve();

        // startPageToken einmalig VOR der ersten Seite einfrieren.
        $startPageToken = $state['startPageToken'] ?? $this->fetchStartPageToken();

        $query = [
            'q' => 'trashed = false',
            'pageSize' => $config['page_size'],
            'fields' => 'nextPageToken, files(id,name,mimeType,size,md5Checksum,headRevisionId,version,modifiedTime,parents,driveId)',
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
        ];
        if ($this->isSharedDrive()) {
            $query['corpora'] = 'drive';
            $query['driveId'] = (string) $this->connection->container_id;
        } else {
            $query['corpora'] = 'user';
        }
        if (! empty($state['pageToken'])) {
            $query['pageToken'] = (string) $state['pageToken'];
        }

        $response = $this->api->getResponse($this->base . '/files', $query);
        $this->guardCheckpointResponse($response);

        /** @var array{nextPageToken?: string, files?: list<array{id?: string, name?: string, mimeType?: string, size?: int|string, md5Checksum?: string, headRevisionId?: string, version?: int|string, modifiedTime?: string, parents?: list<string>}>} $data */
        $data = (array) $response->json();

        $items = [];
        foreach ((array) ($data['files'] ?? []) as $file) {
            $item = $this->mapFile($file);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        $nextPageToken = isset($data['nextPageToken']) ? (string) $data['nextPageToken'] : null;
        $checkpoint = $nextPageToken !== null
            ? $this->encodeCheckpoint('initial', $nextPageToken, $startPageToken)
            : $this->encodeCheckpoint('delta', $startPageToken, null);

        return new IntakeChangePage(
            items: $items,
            tombstones: [],
            checkpoint: $checkpoint,
            hasMore: $nextPageToken !== null,
        );
    }

    // ── Phase 2: Delta ──────────────────────────────────────────────────

    /**
     * @param  array{phase: string, pageToken: string|null, startPageToken: string|null}  $state
     */
    private function deltaPage(array $state): IntakeChangePage {
        $config = GoogleDriveConfig::resolve();

        $query = [
            'pageToken' => (string) ($state['pageToken'] ?? ''),
            'pageSize' => $config['page_size'],
            'fields' => 'nextPageToken, newStartPageToken, changes(removed,fileId,file(id,name,mimeType,size,md5Checksum,headRevisionId,version,modifiedTime,parents,trashed,driveId))',
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
        ];
        if ($this->isSharedDrive()) {
            $query['driveId'] = (string) $this->connection->container_id;
        }

        $response = $this->api->getResponse($this->base . '/changes', $query);
        $this->guardCheckpointResponse($response);

        /** @var array{nextPageToken?: string, newStartPageToken?: string, changes?: list<array{removed?: bool, fileId?: string, file?: array{id?: string, name?: string, mimeType?: string, size?: int|string, md5Checksum?: string, headRevisionId?: string, version?: int|string, modifiedTime?: string, parents?: list<string>, trashed?: bool}}>} $data */
        $data = (array) $response->json();

        $items = [];
        $tombstones = [];
        foreach ((array) ($data['changes'] ?? []) as $change) {
            $fileId = (string) ($change['fileId'] ?? '');
            if (($change['removed'] ?? false) === true || (($change['file']['trashed'] ?? false) === true)) {
                if ($fileId !== '') {
                    $tombstones[] = $fileId;
                }

                continue;
            }

            $item = $this->mapFile((array) ($change['file'] ?? []));
            if ($item !== null) {
                $items[] = $item;
            }
        }

        $nextPageToken = isset($data['nextPageToken']) ? (string) $data['nextPageToken'] : null;
        $newStart = isset($data['newStartPageToken']) ? (string) $data['newStartPageToken'] : null;

        return new IntakeChangePage(
            items: $items,
            tombstones: $tombstones,
            checkpoint: $this->encodeCheckpoint('delta', $nextPageToken ?? $newStart ?? (string) ($state['pageToken'] ?? ''), null),
            hasMore: $nextPageToken !== null,
        );
    }

    // ── Mapping/Pfade ───────────────────────────────────────────────────

    /**
     * @param  array{id?: string, name?: string, mimeType?: string, size?: int|string, md5Checksum?: string, headRevisionId?: string, version?: int|string, modifiedTime?: string, parents?: list<string>}  $file
     */
    private function mapFile(array $file): ?IntakeItem {
        $mime = (string) ($file['mimeType'] ?? '');
        // Ordner + Google-native Formate (Docs/Sheets/Slides) überspringen.
        if ($mime === 'application/vnd.google-apps.folder' || str_starts_with($mime, 'application/vnd.google-apps.')) {
            return null;
        }

        $parents = (array) ($file['parents'] ?? []);
        $parentId = isset($parents[0]) ? (string) $parents[0] : null;
        $path = $this->buildPath($parentId, (string) ($file['name'] ?? ''));
        if ($path === null) {
            return null; // außerhalb des Stammordner-Teilbaums
        }

        return new IntakeItem(
            itemId: (string) ($file['id'] ?? ''),
            path: $path,
            name: (string) ($file['name'] ?? ''),
            revision: (string) ($file['headRevisionId'] ?? $file['version'] ?? ''),
            size: (int) ($file['size'] ?? 0),
            mime: $mime !== '' ? $mime : null,
            modifiedAt: isset($file['modifiedTime']) ? (string) $file['modifiedTime'] : null,
            contentHash: isset($file['md5Checksum']) ? (string) $file['md5Checksum'] : null,
            parentId: $parentId,
        );
    }

    /**
     * Pfad relativ zum Stammordner über die (memoisierte) Ordnerkette;
     * null, wenn die Datei nicht unterhalb des Stammordners liegt.
     */
    private function buildPath(?string $parentId, string $name): ?string {
        $rootId = (string) ($this->connection->root_folder_id ?? '');
        $segments = [];
        $current = $parentId;

        // Ohne konfigurierten Stammordner zählt der Container-Root.
        while ($current !== null && $current !== '') {
            if ($rootId !== '' && $current === $rootId) {
                return implode('/', array_reverse($segments)) === ''
                    ? $name
                    : implode('/', array_reverse($segments)) . '/' . $name;
            }

            $folder = $this->folder($current);
            if ($folder === null) {
                // Kette nicht auflösbar (Rechte/gelöscht) — außerhalb behandeln.
                return $rootId === '' ? $name : null;
            }
            if ($folder['parent'] === null) {
                // Drive-Root erreicht.
                return $rootId === ''
                    ? (implode('/', array_reverse($segments)) === '' ? $name : implode('/', array_reverse($segments)) . '/' . $name)
                    : null;
            }

            $segments[] = $folder['name'];
            $current = $folder['parent'];
        }

        return $rootId === '' ? $name : null;
    }

    /** @return array{name: string, parent: string|null}|null */
    private function folder(string $id): ?array {
        if (array_key_exists($id, $this->folderCache)) {
            return $this->folderCache[$id];
        }

        $response = $this->api->getResponse(
            $this->base . '/files/' . rawurlencode($id),
            ['fields' => 'id,name,parents', 'supportsAllDrives' => 'true'],
        );
        if (! $response->successful()) {
            return $this->folderCache[$id] = null;
        }

        /** @var array{name?: string, parents?: list<string>} $data */
        $data = (array) $response->json();
        $parents = (array) ($data['parents'] ?? []);

        return $this->folderCache[$id] = [
            'name' => (string) ($data['name'] ?? ''),
            'parent' => isset($parents[0]) ? (string) $parents[0] : null,
        ];
    }

    // ── Checkpoint-Handling ─────────────────────────────────────────────

    private function fetchStartPageToken(): string {
        $query = ['supportsAllDrives' => 'true'];
        if ($this->isSharedDrive()) {
            $query['driveId'] = (string) $this->connection->container_id;
        }

        $response = $this->api->getResponse($this->base . '/changes/startPageToken', $query);
        if (! $response->successful()) {
            throw new RuntimeException('Drive startPageToken fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        /** @var array{startPageToken?: string} $data */
        $data = (array) $response->json();

        return (string) ($data['startPageToken'] ?? '');
    }

    /** @return array{phase: string, pageToken: string|null, startPageToken: string|null} */
    private function decodeCheckpoint(?string $checkpoint): array {
        if ($checkpoint === null || $checkpoint === '') {
            return ['phase' => 'initial', 'pageToken' => null, 'startPageToken' => null];
        }

        $decoded = json_decode($checkpoint, true);
        if (! is_array($decoded) || ! in_array($decoded['phase'] ?? '', ['initial', 'delta'], true)) {
            throw new StaleCheckpointException('Unlesbarer Drive-Checkpoint.');
        }

        return [
            'phase' => (string) $decoded['phase'],
            'pageToken' => isset($decoded['pageToken']) ? (string) $decoded['pageToken'] : null,
            'startPageToken' => isset($decoded['startPageToken']) ? (string) $decoded['startPageToken'] : null,
        ];
    }

    private function encodeCheckpoint(string $phase, ?string $pageToken, ?string $startPageToken): string {
        return (string) json_encode(array_filter([
            'phase' => $phase,
            'pageToken' => $pageToken,
            'startPageToken' => $startPageToken,
        ], static fn ($v) => $v !== null), JSON_THROW_ON_ERROR);
    }

    private function guardCheckpointResponse(\Illuminate\Http\Client\Response $response): void {
        // Ungültiger/abgelaufener pageToken: Google antwortet 400/404 mit
        // "Invalid Value" — begrenzter Vollabgleich statt blindem Neuimport.
        if (in_array($response->status(), [400, 404], true)
            && str_contains(strtolower((string) $response->body()), 'invalid')) {
            throw new StaleCheckpointException('Drive-pageToken ungültig/abgelaufen.');
        }
        if (! $response->successful()) {
            throw new RuntimeException('Drive-Abfrage fehlgeschlagen (HTTP ' . $response->status() . ').');
        }
    }

    private function isSharedDrive(): bool {
        $container = (string) ($this->connection->container_id ?? '');

        return $container !== '' && $container !== 'my-drive';
    }
}
