<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphIntakeClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2BearerAuthentication;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\Msgraph\{MsgraphConfig, MsgraphPlugin};
use App\Plugins\Support\{ConnectionTokenStore, PluginHttpFactory};
use App\Plugins\Support\Intake\{IntakeAccount, IntakeChangePage, IntakeContainer, IntakeItem};
use App\Services\CloudIntake\StaleCheckpointException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Microsoft-Graph-Gateway für den LESENDEN Dokumenteingang (Feature 080,
 * MVP-354): OneDrive-Drives und SharePoint-Dokumentbibliotheken über den
 * driveItem-Delta-Mechanismus. Der Checkpoint ist die von Graph gelieferte
 * absolute URL (`@odata.nextLink` solange Seiten offen sind, danach
 * `@odata.deltaLink`) — `hasMore` unterscheidet die beiden. Ein 410 Gone
 * (abgelaufenes Delta-Token) wirft {@see StaleCheckpointException}.
 *
 * Tombstones sind Graph-Item-IDs (deleted-Facette); der Runner matcht
 * id-zuerst, `path:`-Tombstones (Dropbox) über den Quellpfad.
 */
class MsgraphIntakeClient {
    private \App\Plugins\Support\PluginApiClient $api;

    private string $base;

    public function __construct(private readonly CloudDocumentConnection $connection) {
        $config = MsgraphConfig::resolve();
        $this->base = $config['api_base'];
        $this->api = app(PluginHttpFactory::class)->client(MsgraphPlugin::ID, $this->base);

        $grant = MsgraphConfig::isConfigured() ? app(MsgraphIntakeOAuth::class)->grant() : null;
        $this->api->setAuthentication(new OAuth2BearerAuthentication(new ConnectionTokenStore($connection, 'granted_scopes', scopeAsArray: true), $grant));
    }

    public function account(): IntakeAccount {
        $response = $this->api->getResponse($this->base . '/me');
        if (! $response->successful()) {
            throw new RuntimeException('Graph /me fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        /** @var array{id?: string, displayName?: string, mail?: string, userPrincipalName?: string} $data */
        $data = (array) $response->json();

        return new IntakeAccount(
            externalId: (string) ($data['id'] ?? ''),
            label: trim((string) ($data['displayName'] ?? '') . ' <' . (string) ($data['mail'] ?? $data['userPrincipalName'] ?? '') . '>'),
        );
    }

    /**
     * Eigene Drives (OneDrive) als Container; SharePoint-Bibliotheken werden
     * über {@see sites()}/{@see siteDrives()} der Ordner-Auswahl zugeführt.
     *
     * @return list<IntakeContainer>
     */
    public function containers(): array {
        $response = $this->api->getResponse($this->base . '/me/drives');
        if (! $response->successful()) {
            throw new RuntimeException('Graph /me/drives fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        $containers = [];
        /** @var array{value?: list<array{id?: string, name?: string, driveType?: string}>} $data */
        $data = (array) $response->json();
        foreach ((array) ($data['value'] ?? []) as $drive) {
            $containers[] = new IntakeContainer(
                id: (string) ($drive['id'] ?? ''),
                label: (string) ($drive['name'] ?? 'Drive'),
                kind: (string) ($drive['driveType'] ?? 'drive'),
            );
        }

        return $containers;
    }

    /**
     * SharePoint-Site-Suche für die Bibliotheksauswahl (P8-UI).
     *
     * @return list<array{id: string, label: string}>
     */
    public function sites(string $search): array {
        $response = $this->api->getResponse($this->base . '/sites', ['search' => $search === '' ? '*' : $search]);
        if (! $response->successful()) {
            return [];
        }

        $sites = [];
        /** @var array{value?: list<array{id?: string, displayName?: string, name?: string}>} $data */
        $data = (array) $response->json();
        foreach ((array) ($data['value'] ?? []) as $site) {
            $sites[] = [
                'id' => (string) ($site['id'] ?? ''),
                'label' => (string) ($site['displayName'] ?? $site['name'] ?? ''),
            ];
        }

        return $sites;
    }

    /**
     * Dokumentbibliotheken (Drives) einer SharePoint-Site.
     *
     * @return list<IntakeContainer>
     */
    public function siteDrives(string $siteId): array {
        $response = $this->api->getResponse($this->base . '/sites/' . rawurlencode($siteId) . '/drives');
        if (! $response->successful()) {
            return [];
        }

        $containers = [];
        /** @var array{value?: list<array{id?: string, name?: string, driveType?: string}>} $data */
        $data = (array) $response->json();
        foreach ((array) ($data['value'] ?? []) as $drive) {
            $containers[] = new IntakeContainer(
                id: (string) ($drive['id'] ?? ''),
                label: (string) ($drive['name'] ?? 'Bibliothek'),
                kind: (string) ($drive['driveType'] ?? 'documentLibrary'),
            );
        }

        return $containers;
    }

    public function changes(?string $checkpoint): IntakeChangePage {
        if ($checkpoint === null || $checkpoint === '') {
            $root = $this->connection->root_folder_id;
            $rootSegment = $root !== null && $root !== '' ? 'items/' . rawurlencode($root) : 'root';
            $url = $this->base . '/drives/' . rawurlencode((string) $this->connection->container_id) . '/' . $rootSegment . '/delta';
            $response = $this->api->getResponse($url, ['$top' => MsgraphConfig::resolve()['intake_page_size']]);
        } else {
            // Checkpoint ist die absolute next-/deltaLink-URL von Graph.
            $response = $this->api->getResponse($checkpoint);
        }

        if ($response->status() === 410) {
            throw new StaleCheckpointException('Graph-Delta-Token abgelaufen (410 Gone).');
        }
        if (! $response->successful()) {
            throw new RuntimeException('Graph delta fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        /** @var array{value?: list<array{id?: string, name?: string, size?: int, eTag?: string, cTag?: string, lastModifiedDateTime?: string, deleted?: array<string, mixed>, file?: array{mimeType?: string, hashes?: array{quickXorHash?: string}}, parentReference?: array{path?: string, id?: string}}>, '@odata.nextLink'?: string, '@odata.deltaLink'?: string} $data */
        $data = (array) $response->json();

        $items = [];
        $tombstones = [];
        foreach ((array) ($data['value'] ?? []) as $entry) {
            if (isset($entry['deleted'])) {
                $tombstones[] = (string) ($entry['id'] ?? '');

                continue;
            }
            if (! isset($entry['file'])) {
                continue; // Ordner/Sonstiges
            }

            $items[] = new IntakeItem(
                itemId: (string) ($entry['id'] ?? ''),
                path: $this->relativePath($entry),
                name: (string) ($entry['name'] ?? ''),
                revision: (string) ($entry['eTag'] ?? $entry['cTag'] ?? ''),
                size: (int) ($entry['size'] ?? 0),
                mime: isset($entry['file']['mimeType']) ? (string) $entry['file']['mimeType'] : null,
                modifiedAt: isset($entry['lastModifiedDateTime']) ? (string) $entry['lastModifiedDateTime'] : null,
                contentHash: isset($entry['file']['hashes']['quickXorHash']) ? (string) $entry['file']['hashes']['quickXorHash'] : null,
                parentId: isset($entry['parentReference']['id']) ? (string) $entry['parentReference']['id'] : null,
            );
        }

        $nextLink = isset($data['@odata.nextLink']) ? (string) $data['@odata.nextLink'] : null;
        $deltaLink = isset($data['@odata.deltaLink']) ? (string) $data['@odata.deltaLink'] : null;

        return new IntakeChangePage(
            items: $items,
            tombstones: array_values(array_filter($tombstones)),
            checkpoint: (string) ($nextLink ?? $deltaLink ?? ''),
            hasMore: $nextLink !== null,
        );
    }

    public function download(IntakeItem $item): StreamInterface {
        // Graph antwortet mit 302 auf eine kurzlebige Download-URL — Guzzle
        // folgt dem Redirect; die URL wird nie persistiert.
        $url = $this->base . '/drives/' . rawurlencode((string) $this->connection->container_id)
            . '/items/' . rawurlencode($item->itemId) . '/content';
        $response = $this->api->getResponse($url);

        if (! $response->successful()) {
            throw new RuntimeException('Graph-Download fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return $response->toPsrResponse()->getBody();
    }

    /**
     * Quellpfad relativ zum Stammordner: `parentReference.path` hat die Form
     * `/drives/{id}/root:/Unter/Ordner` — alles bis `root:` ist Drive-Anteil.
     *
     * @param  array{name?: string, parentReference?: array{path?: string}}  $entry
     */
    private function relativePath(array $entry): string {
        $parentPath = (string) ($entry['parentReference']['path'] ?? '');
        $name = (string) ($entry['name'] ?? '');

        $relativeParent = '';
        $marker = strpos($parentPath, 'root:');
        if ($marker !== false) {
            $relativeParent = ltrim(substr($parentPath, $marker + 5), '/');
        }

        $path = $relativeParent === '' ? $name : $relativeParent . '/' . $name;

        // Stammordner-Pfadanteil entfernen (Delta ab Unterordner liefert
        // Pfade weiterhin ab Drive-Root).
        $rootPath = ltrim((string) ($this->connection->root_folder_path ?? ''), '/');
        if ($rootPath !== '' && str_starts_with(mb_strtolower($path), mb_strtolower($rootPath) . '/')) {
            $path = substr($path, strlen($rootPath) + 1);
        }

        return $path;
    }
}
