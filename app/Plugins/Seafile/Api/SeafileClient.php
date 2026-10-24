<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeafileClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Seafile\Api;

use APIToolkit\API\Authentication\ApiKeyAuthentication;
use App\Plugins\Seafile\Exceptions\SeafileApiException;
use App\Plugins\Seafile\SeafilePlugin;
use App\Plugins\Support\{GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory};
use CommonToolkit\Helper\FileSystem\File;
use Psr\Http\Message\StreamInterface;

/**
 * Seafile-Web-API (`/api2`) mit Konto-Token: Konto, Bibliotheken, Ordner
 * (mit `oid`: „uptodate“, wenn sich darunter nichts geändert hat), Download-
 * und Upload-Links, Ordner anlegen, Löschen. Down- und Uploads laufen über
 * kurzlebige Links des Dateiservers (`seafhttp`) — mit eigenem Client ohne
 * Token, durch dieselbe SSRF-Schranke.
 */
class SeafileClient {
    use GuardsPluginApiResponses;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $apiToken,
        private readonly ?bool $allowPrivateNetwork = null,
    ) {}

    /**
     * Konto: `email` ist die Kennung (seit 11 oft eine interne wie `…@auth.local`),
     * `contact_email` die lesbare Adresse; Speicher gilt für eigene Bibliotheken.
     *
     * @return array{email: ?string, contact: ?string, name: ?string, total: ?int, usage: ?int}
     */
    public function account(): array {
        $info = $this->guard($this->api()->getResponse($this->url('/account/info/')), '/account/info');
        $int = static fn (mixed $value): ?int => is_int($value) && $value >= 0 ? $value : null;

        return [
            'email' => is_string($info['email'] ?? null) ? $info['email'] : null,
            'contact' => is_string($info['contact_email'] ?? null) && $info['contact_email'] !== '' ? $info['contact_email'] : null,
            'name' => is_string($info['name'] ?? null) && $info['name'] !== '' ? $info['name'] : null,
            // Seafile meldet „unbegrenzt“ als negative Zahl.
            'total' => $int($info['total'] ?? null),
            'usage' => $int($info['usage'] ?? null),
        ];
    }

    /**
     * Bibliotheken des Kontos — Seafile listet eigene vor geteilten, Gruppen-
     * und Organisationsbibliotheken; je ID zählt der erste Eintrag.
     *
     * @return list<array{id: string, name: string, encrypted: bool, writable: bool, own: bool}>
     */
    public function libraries(): array {
        $repos = $this->guard($this->api()->getResponse($this->url('/repos/')), '/repos');
        $libraries = [];
        foreach ($repos as $repo) {
            if (is_array($repo) && is_string($repo['id'] ?? null) && self::isRepoId($repo['id']) && is_string($repo['name'] ?? null)) {
                $libraries[$repo['id']] ??= [
                    'id' => $repo['id'],
                    'name' => $repo['name'],
                    'encrypted' => (bool) ($repo['encrypted'] ?? false),
                    'writable' => ($repo['permission'] ?? null) === 'rw',
                    'own' => ($repo['type'] ?? null) === 'repo',
                ];
            }
        }

        return array_values($libraries);
    }

    /** Gibt es die Bibliothek noch? 404 heißt bei Ordner- und Dateiabrufen auch „Bibliothek fehlt“. */
    public function libraryExists(string $repoId): bool {
        $response = $this->api()->getResponse($this->repo($repoId, '/'));
        if ($response->status() === 404) {
            return false;
        }
        $this->assertOk($response, '/repos/{id}');

        return true;
    }

    /**
     * Einträge eines Ordners. `$knownOid` ist die Ordner-ID des letzten Stands:
     * stimmt sie noch, antwortet Seafile „uptodate“ ohne Liste. Null bei 404.
     *
     * @return array{oid: ?string, uptodate: bool, entries: list<array<string, mixed>>}|null
     */
    public function folder(string $repoId, string $path, ?string $knownOid = null): ?array {
        $response = $this->api()->getResponse($this->repo($repoId, '/dir/'), array_filter(['p' => self::path($path), 'oid' => $knownOid], static fn (?string $v): bool => $v !== null));
        if ($response->status() === 404) {
            return null;
        }
        $this->assertOk($response, '/repos/{id}/dir');
        $oid = $response->header('oid');
        $body = $response->json();
        if ($body === 'uptodate') {
            return ['oid' => $oid !== '' ? $oid : $knownOid, 'uptodate' => true, 'entries' => []];
        }
        if (! is_array($body) || ! array_is_list($body)) {
            throw new SeafileApiException('Seafile /repos/{id}/dir: unerwartete Antwort', 0, '/repos/{id}/dir');
        }

        return ['oid' => $oid !== '' ? $oid : null, 'uptodate' => false, 'entries' => array_values(array_filter($body, 'is_array'))];
    }

    /** Größe einer Datei; null, wenn es sie nicht gibt. */
    public function fileSize(string $repoId, string $path): ?int {
        $response = $this->api()->getResponse($this->repo($repoId, '/file/detail/'), ['p' => self::path($path)]);
        if ($response->status() === 404) {
            return null;
        }
        $detail = $this->guard($response, '/repos/{id}/file/detail');

        return is_int($detail['size'] ?? null) ? $detail['size'] : null;
    }

    public function download(string $repoId, string $path): StreamInterface {
        $link = $this->link($this->repo($repoId, '/file/'), ['p' => self::path($path), 'reuse' => '1'], '/repos/{id}/file');
        $response = $this->linkClient($link)->getResponse($link, [], ['stream' => true, 'timeout' => 300]);
        if (! $response->successful()) {
            throw $this->apiError($response, 'seafhttp/files');
        }

        return $response->toPsrResponse()->getBody();
    }

    /**
     * Legt einen Ordner samt fehlender Elternordner an. Vorher prüfen, weil
     * Seafile einen gleichnamigen sonst als „Name (1)“ anlegt.
     */
    public function makeFolder(string $repoId, string $path): void {
        $existing = $this->folder($repoId, $path);
        if ($existing !== null) {
            return;
        }
        $response = $this->api()->requestResponse('POST', $this->repo($repoId, '/dir/') . '?' . http_build_query(['p' => self::path($path)]), ['form_params' => ['operation' => 'mkdir', 'create_parents' => 'true']]);
        $this->assertOk($response, '/repos/{id}/dir mkdir');
    }

    /**
     * Lädt eine lokale Datei in `$parentDir` hoch und ersetzt eine gleichnamige;
     * liefert Name und Größe aus der Antwort des Dateiservers (`ret-json`).
     *
     * @return array{name: ?string, size: ?int}
     */
    public function upload(string $repoId, string $parentDir, string $localPath, string $fileName): array {
        $parent = self::path($parentDir);
        $link = $this->link($this->repo($repoId, '/upload-link/'), ['p' => $parent], '/repos/{id}/upload-link');
        $handle = File::openStream($localPath, 'rb');
        if ($handle === false) {
            throw new SeafileApiException('Seafile-Upload: lokale Datei nicht lesbar.', 0, 'upload');
        }
        try {
            $response = $this->linkClient($link)->requestResponse('POST', $link . (str_contains($link, '?') ? '&' : '?') . 'ret-json=1', [
                'multipart' => [
                    ['name' => 'parent_dir', 'contents' => $parent],
                    ['name' => 'replace', 'contents' => '1'],
                    ['name' => 'file', 'contents' => $handle, 'filename' => $fileName],
                ],
                'timeout' => 900,
            ]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
        $this->assertOk($response, 'seafhttp/upload-api');
        $uploaded = $response->json()[0] ?? null;

        return [
            'name' => is_array($uploaded) && is_string($uploaded['name'] ?? null) ? $uploaded['name'] : null,
            'size' => is_array($uploaded) && is_int($uploaded['size'] ?? null) ? $uploaded['size'] : null,
        ];
    }

    /**
     * Löscht Datei oder Ordner samt Inhalt; ein fehlendes Objekt gilt als
     * gelöscht. Ordner über `api2 …/dir/`, Dateien über `api/v2.1 …/file/`,
     * das bei fehlender Datei 200 und nur bei fehlender Bibliothek 404 meldet.
     */
    public function delete(string $repoId, string $path): bool {
        $query = '?' . http_build_query(['p' => self::path($path)]);
        if ($this->folder($repoId, $path) !== null) {
            $this->assertOk($this->api()->deleteResponse($this->repo($repoId, '/dir/') . $query), '/repos/{id}/dir');

            return true;
        }
        $this->assertOk($this->api()->deleteResponse($this->baseUrl . '/api/v2.1/repos/' . $repoId . '/file/' . $query), '/api/v2.1/repos/{id}/file');

        return true;
    }

    /** Leseprobe der Instanz (Healthcheck). */
    public function probe(): void {
        $this->account();
    }

    public static function isRepoId(string $id): bool {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id) === 1;
    }

    /** Seafile-Pfad: führender Schrägstrich, keine leeren oder relativen Segmente. */
    public static function path(string $path): string {
        $segments = array_values(array_filter(explode('/', str_replace('\\', '/', $path)), static fn (string $s): bool => $s !== '' && $s !== '.'));
        if (in_array('..', $segments, true)) {
            throw new SeafileApiException('Seafile-Pfad ungültig.', 0, 'path');
        }

        return '/' . implode('/', $segments);
    }

    /**
     * Link des Dateiservers aus einer API-Antwort (JSON-String).
     *
     * @param  array<string, string>  $query
     */
    private function link(string $url, array $query, string $endpoint): string {
        $response = $this->api()->getResponse($url, $query);
        $this->assertOk($response, $endpoint);
        $link = $response->json();
        if (! is_string($link) || ! in_array(strtolower((string) parse_url($link, PHP_URL_SCHEME)), ['http', 'https'], true) || parse_url($link, PHP_URL_HOST) === null) {
            throw new SeafileApiException('Seafile ' . $endpoint . ': kein gültiger Link', 0, $endpoint);
        }

        return $link;
    }

    /** Client für den Host eines Dateiserver-Links — ohne Konto-Token, mit SSRF-Schranke. */
    private function linkClient(string $link): PluginApiClient {
        $parts = (array) parse_url($link);
        $origin = strtolower((string) ($parts['scheme'] ?? 'https')) . '://' . (string) ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return $this->http->client(SeafilePlugin::ID, $origin, allowPrivateNetwork: $this->allowPrivateNetwork);
    }

    private function repo(string $repoId, string $path): string {
        if (! self::isRepoId($repoId)) {
            throw new SeafileApiException('Seafile-Bibliothek ungültig.', 0, 'repo');
        }

        return $this->url('/repos/' . $repoId . $path);
    }

    private function url(string $path): string {
        return $this->baseUrl . '/api2' . $path;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = $this->http->client(SeafilePlugin::ID, $this->baseUrl, allowPrivateNetwork: $this->allowPrivateNetwork);
            $this->api->setAuthentication(new ApiKeyAuthentication('Token ' . $this->apiToken, 'Authorization'));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return SeafileApiException::class;
    }

    protected function apiLabel(): string {
        return 'Seafile';
    }
}
