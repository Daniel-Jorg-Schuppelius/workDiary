<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DropboxClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Dropbox\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2BearerAuthentication;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\Dropbox\{DropboxConfig, DropboxPlugin};
use App\Plugins\Support\Intake\{IntakeAccount, IntakeChangePage, IntakeContainer, IntakeItem};
use App\Plugins\Support\PluginHttpFactory;
use App\Services\CloudIntake\{CloudIntakeTokenStore, StaleCheckpointException};
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Dropbox-Gateway (Feature 080, MVP-353) auf dem php-api-toolkit-Fundament:
 * OAuth2-Bearer über den verbindungsgebundenen {@see CloudIntakeTokenStore}
 * (transparenter Refresh). Delta über `files/list_folder` (+ `/continue`);
 * `include_deleted` liefert Tombstones — Dropbox meldet Löschungen NUR über
 * den Pfad (kein Item-ID im deleted-Eintrag), deshalb sind Tombstones hier
 * `path:`-präfixiert; der Runner matcht id-zuerst, dann Pfad.
 *
 * Ein 409 mit `reset`-Tag am Cursor wirft {@see StaleCheckpointException}
 * (begrenzter Vollabgleich statt blindem Neuimport).
 */
class DropboxClient {
    private \App\Plugins\Support\PluginApiClient $api;

    private \App\Plugins\Support\PluginApiClient $content;

    private string $apiBase;

    private string $contentBase;

    public function __construct(private readonly CloudDocumentConnection $connection) {
        $config = DropboxConfig::resolve();
        $factory = app(PluginHttpFactory::class);

        // Vollqualifizierte URLs je Request (SevDesk-Muster): Guzzle-base_uri
        // würde bei führendem Slash den Basis-Pfad (/2) verwerfen.
        $this->apiBase = rtrim($config['api_base'], '/');
        $this->contentBase = rtrim($config['content_base'], '/');
        $this->api = $factory->client(DropboxPlugin::ID, $this->apiBase);
        $this->content = $factory->client(DropboxPlugin::ID, $this->contentBase);

        $grant = DropboxConfig::isConfigured() ? app(DropboxOAuth::class)->grant() : null;
        $store = new CloudIntakeTokenStore($connection);
        $this->api->setAuthentication(new OAuth2BearerAuthentication($store, $grant));
        $this->content->setAuthentication(new OAuth2BearerAuthentication($store, $grant));
    }

    public function account(): IntakeAccount {
        $response = $this->api->postJson($this->apiBase . '/users/get_current_account');
        if (! $response->successful()) {
            throw new RuntimeException('Dropbox get_current_account fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        /** @var array{account_id?: string, email?: string, name?: array{display_name?: string}} $data */
        $data = (array) $response->json();

        return new IntakeAccount(
            externalId: (string) ($data['account_id'] ?? ''),
            label: trim((string) ($data['name']['display_name'] ?? '') . ' <' . (string) ($data['email'] ?? '') . '>'),
        );
    }

    /**
     * MVP: das verbundene Konto als ein Container (persönlicher Namespace
     * inkl. für das Konto freigegebener Ordner); Team-Admin-Modus Nach-MVP.
     *
     * @return list<IntakeContainer>
     */
    public function containers(): array {
        return [new IntakeContainer('personal', 'Dropbox', 'personal')];
    }

    public function changes(?string $checkpoint): IntakeChangePage {
        if ($checkpoint === null || $checkpoint === '') {
            $response = $this->api->postJson($this->apiBase . '/files/list_folder', [
                'path' => $this->rootPath(),
                'recursive' => true,
                'include_deleted' => true,
                'limit' => DropboxConfig::resolve()['page_size'],
            ]);
        } else {
            $response = $this->api->postJson($this->apiBase . '/files/list_folder/continue', ['cursor' => $checkpoint]);
        }

        if ($response->status() === 409 && str_contains((string) $response->body(), 'reset')) {
            throw new StaleCheckpointException('Dropbox-Cursor abgelaufen (reset).');
        }
        if (! $response->successful()) {
            throw new RuntimeException('Dropbox list_folder fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        /** @var array{entries?: list<array<string, mixed>>, cursor?: string, has_more?: bool} $data */
        $data = (array) $response->json();

        $items = [];
        $tombstones = [];
        foreach ((array) ($data['entries'] ?? []) as $entry) {
            $tag = (string) ($entry['.tag'] ?? '');
            $displayPath = (string) ($entry['path_display'] ?? '');

            if ($tag === 'file') {
                $items[] = new IntakeItem(
                    itemId: (string) ($entry['id'] ?? ''),
                    path: $this->relativePath($displayPath),
                    name: (string) ($entry['name'] ?? basename($displayPath)),
                    revision: (string) ($entry['rev'] ?? ''),
                    size: (int) ($entry['size'] ?? 0),
                    modifiedAt: isset($entry['server_modified']) ? (string) $entry['server_modified'] : null,
                    contentHash: isset($entry['content_hash']) ? (string) $entry['content_hash'] : null,
                );
            } elseif ($tag === 'deleted') {
                // Dropbox liefert für Löschungen keinen Item-ID — Pfad-Tombstone.
                $tombstones[] = 'path:' . $this->relativePath($displayPath);
            }
        }

        return new IntakeChangePage(
            items: $items,
            tombstones: $tombstones,
            checkpoint: (string) ($data['cursor'] ?? ''),
            hasMore: (bool) ($data['has_more'] ?? false),
        );
    }

    public function download(IntakeItem $item): StreamInterface {
        $response = $this->content->requestResponse('POST', $this->contentBase . '/files/download', [
            'headers' => [
                'Dropbox-API-Arg' => json_encode(['path' => $item->itemId], JSON_THROW_ON_ERROR),
                // Dropbox verlangt einen leeren Body ohne JSON-Content-Type.
                'Content-Type' => 'text/plain; charset=dropbox-cors-hack',
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Dropbox-Download fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return $response->toPsrResponse()->getBody();
    }

    /** Stammordner der Verbindung ('' = Konto-Root). */
    private function rootPath(): string {
        $root = (string) ($this->connection->root_folder_path ?? '');

        return $root === '/' ? '' : rtrim($root, '/');
    }

    /** Quellpfad relativ zum Stammordner (case-insensitiv, ohne führenden Slash). */
    private function relativePath(string $displayPath): string {
        $root = $this->rootPath();
        $path = ltrim($displayPath, '/');
        $rootTrimmed = ltrim($root, '/');

        if ($rootTrimmed !== '' && str_starts_with(mb_strtolower($path), mb_strtolower($rootTrimmed) . '/')) {
            $path = substr($path, strlen($rootTrimmed) + 1);
        }

        return $path;
    }
}
