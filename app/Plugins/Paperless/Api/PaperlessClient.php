<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaperlessClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Paperless\Api;

use APIToolkit\API\Authentication\ApiKeyAuthentication;
use App\Plugins\Paperless\Exceptions\PaperlessApiException;
use App\Plugins\Paperless\PaperlessPlugin;
use App\Plugins\Support\{GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\TaskSync\IncompleteRemoteReadException;
use Psr\Http\Message\StreamInterface;

/**
 * Lesender Paperless-ngx-Client. Ohne Versionsangabe im Accept-Header
 * antwortet der Server in seiner Standardversion; genutzt werden nur Felder,
 * die alle unterstützten Versionen gleich liefern. Die Liste `all` entfällt
 * ab API 10, daher sammelt {@see documentIds()} die IDs seitenweise.
 */
class PaperlessClient {
    use GuardsPluginApiResponses;

    private const ID_PAGE_SIZE = 250;

    /** Wählbare Quellen der Containerauswahl: Endpunkt → Container-Präfix. */
    public const CONTAINER_KINDS = ['tags' => 'tag', 'correspondents' => 'correspondent', 'document_types' => 'type'];

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $apiToken,
        private readonly int $maxIdPages,
    ) {}

    /**
     * Benutzer des Tokens; `/api/ui_settings/` liefert ihn ab API 3 unter
     * `user`, davor flach.
     *
     * @return array{id: string, username: string}
     */
    public function currentUser(): array {
        $body = $this->guard($this->api()->getResponse($this->url('/ui_settings/')), '/ui_settings/');
        $user = is_array($body['user'] ?? null) ? $body['user'] : $body;
        $id = $user['id'] ?? $user['user_id'] ?? null;
        $name = $user['username'] ?? null;
        if (! is_scalar($id) || ! is_string($name) || $name === '') {
            throw new PaperlessApiException('Paperless-ngx /ui_settings/: Benutzer fehlt in der Antwort.', 0, '/ui_settings/');
        }

        return ['id' => (string) $id, 'username' => $name];
    }

    /**
     * Tags, Korrespondenten und Dokumenttypen für die Containerauswahl.
     *
     * @return list<array{kind: string, id: string, name: string}>
     */
    public function containerOptions(?string $search): array {
        $options = [];
        foreach (self::CONTAINER_KINDS as $endpoint => $kind) {
            // Seitenweise bis `next` leer ist — sonst fehlten Tags jenseits der ersten Seite still in der Auswahl.
            for ($page = 1; $page <= $this->maxIdPages; $page++) {
                $body = $this->guard($this->api()->getResponse($this->url('/' . $endpoint . '/'), array_filter([
                    'ordering' => 'name',
                    'page' => $page,
                    'page_size' => 100,
                    'name__icontains' => $search,
                ], static fn (mixed $v): bool => $v !== null && $v !== '')), '/' . $endpoint . '/');
                foreach ($this->results($body) as $row) {
                    if (is_scalar($row['id'] ?? null) && is_string($row['name'] ?? null)) {
                        $options[] = ['kind' => $kind, 'id' => (string) $row['id'], 'name' => $row['name']];
                    }
                }
                if (($body['next'] ?? null) === null) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * Jüngster Änderungsstand im Filter — Startpunkt des nächsten Zyklus.
     *
     * @param  array<string, string>  $filter
     */
    public function newestModified(array $filter): ?string {
        $body = $this->guard($this->api()->getResponse($this->url('/documents/'), $filter + [
            'ordering' => '-modified',
            'page_size' => 1,
            'fields' => 'id,modified',
        ]), '/documents/');
        $first = $this->results($body)[0] ?? null;

        return is_string($first['modified'] ?? null) ? $first['modified'] : null;
    }

    /**
     * Alle Dokument-IDs im Filter, aufsteigend. Ändert sich die Gesamtzahl
     * während des Blätterns, gilt die Liste als unvollständig.
     *
     * @param  array<string, string>  $filter
     * @return list<int>
     */
    public function documentIds(array $filter): array {
        $ids = [];
        $count = null;
        for ($page = 1; $page <= $this->maxIdPages; $page++) {
            $body = $this->guard($this->api()->getResponse($this->url('/documents/'), $filter + [
                'ordering' => 'id',
                'page' => $page,
                'page_size' => self::ID_PAGE_SIZE,
                'fields' => 'id',
            ]), '/documents/');
            if (! is_int($body['count'] ?? null) || ($count !== null && $body['count'] !== $count)) {
                throw new IncompleteRemoteReadException('listing_changed');
            }
            $count = $body['count'];
            foreach ($this->results($body) as $row) {
                if (is_int($row['id'] ?? null)) {
                    $ids[] = $row['id'];
                }
            }
            if (($body['next'] ?? null) === null) {
                if (count($ids) !== $count) {
                    throw new IncompleteRemoteReadException('listing_changed');
                }

                return $ids;
            }
        }

        throw new IncompleteRemoteReadException('page_limit');
    }

    /**
     * Kopfdaten der genannten Dokumente; fehlende (inzwischen gelöschte) fehlen im Ergebnis.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    public function documents(array $ids): array {
        if ($ids === []) {
            return [];
        }
        $body = $this->guard($this->api()->getResponse($this->url('/documents/'), [
            'id__in' => implode(',', $ids),
            'page_size' => count($ids),
            'fields' => 'id,title,correspondent,document_type,original_file_name,modified',
        ]), '/documents/');
        $documents = [];
        foreach ($this->results($body) as $row) {
            if (is_int($row['id'] ?? null)) {
                $documents[$row['id']] = $row;
            }
        }

        return $documents;
    }

    /**
     * Namen von Korrespondenten oder Dokumenttypen.
     *
     * @param  'correspondents'|'document_types'  $endpoint
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function names(string $endpoint, array $ids): array {
        if ($ids === []) {
            return [];
        }
        $body = $this->guard($this->api()->getResponse($this->url('/' . $endpoint . '/'), [
            'id__in' => implode(',', $ids),
            'page_size' => count($ids),
            'fields' => 'id,name',
        ]), '/' . $endpoint . '/');
        $names = [];
        foreach ($this->results($body) as $row) {
            if (is_int($row['id'] ?? null) && is_string($row['name'] ?? null)) {
                $names[$row['id']] = $row['name'];
            }
        }

        return $names;
    }

    /**
     * Prüfsumme, Größe und MIME-Typ des Originals; null, wenn das Dokument fehlt.
     *
     * @return array<string, mixed>|null
     */
    public function metadata(int $documentId): ?array {
        $response = $this->api()->getResponse($this->url('/documents/' . $documentId . '/metadata/'));
        if ($response->status() === 404) {
            return null;
        }

        /** @var array<string, mixed> */
        return $this->guard($response, '/documents/{id}/metadata/');
    }

    /** Originaldatei als Stream — das Archiv-PDF ließe eingebettete E-Rechnungsdaten weg. */
    public function download(int $documentId): StreamInterface {
        $response = $this->api()->getResponse($this->url('/documents/' . $documentId . '/download/'), ['original' => 'true'], ['stream' => true, 'timeout' => 120]);
        if (! $response->successful()) {
            throw $this->apiError($response, '/documents/{id}/download/');
        }

        return $response->toPsrResponse()->getBody();
    }

    /** Leseprobe der Instanz (Healthcheck). */
    public function probe(): void {
        $this->currentUser();
    }

    /**
     * @param  array<mixed>  $body
     * @return list<array<string, mixed>>
     */
    private function results(array $body): array {
        $results = $body['results'] ?? null;
        if (! is_array($results) || ! array_is_list($results)) {
            throw new IncompleteRemoteReadException('invalid_response');
        }

        return array_values(array_filter($results, 'is_array'));
    }

    private function url(string $path): string {
        return $this->baseUrl . '/api' . $path;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = $this->http->client(PaperlessPlugin::ID, $this->baseUrl);
            $this->api->setAuthentication(new ApiKeyAuthentication('Token ' . $this->apiToken, 'Authorization'));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return PaperlessApiException::class;
    }

    protected function apiLabel(): string {
        return 'Paperless-ngx';
    }
}
