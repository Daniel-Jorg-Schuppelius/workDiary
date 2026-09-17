<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphOneNoteClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2BearerAuthentication;
use APIToolkit\API\Pagination\{CursorPage, CursorPaginator};
use App\Models\MsgraphOneNoteConnection;
use App\Plugins\Msgraph\{MsgraphConfig, MsgraphPlugin};
use App\Plugins\Support\{ConnectionTokenStore, PluginApiClient, PluginHttpFactory};
use Generator;
use RuntimeException;

/**
 * Lesender OneNote-Client (Feature 155, MVP-815): Notizbücher, Abschnitte
 * (auch eine Ebene Abschnittsgruppen), Seiten und Seiteninhalt. Kein
 * Schreibzugriff — der Grant trägt nur `Notes.Read`.
 */
class MsgraphOneNoteClient {
    private PluginApiClient $api;

    private string $base;

    public function __construct(private readonly MsgraphOneNoteConnection $connection) {
        $this->base = MsgraphConfig::resolve()['api_base'];
        $this->api = app(PluginHttpFactory::class)->client(MsgraphPlugin::ID, $this->base);

        $orgId = (int) $connection->organization_id;
        $grant = MsgraphConfig::isConfigured($orgId) ? app(MsgraphOneNoteOAuth::class)->grantFor($orgId) : null;
        $this->api->setAuthentication(new OAuth2BearerAuthentication(new ConnectionTokenStore($this->connection), $grant));
    }

    /** @return array{id: string, label: string} */
    public function account(): array {
        $response = $this->api->getResponse($this->base . '/me');
        if (! $response->successful()) {
            throw new RuntimeException('Graph /me fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return [
            'id' => (string) $response->json('id', ''),
            'label' => trim((string) $response->json('displayName', '') . ' <' . (string) ($response->json('mail') ?? $response->json('userPrincipalName', '')) . '>'),
        ];
    }

    /** @return list<array{id: string, name: string}> */
    public function notebooks(): array {
        return $this->named($this->graphPages($this->base . '/me/onenote/notebooks', ['$select' => 'id,displayName', '$orderby' => 'displayName'], 'Graph OneNote-Notizbücher'));
    }

    /**
     * Abschnitte eines Notizbuchs; Abschnitte in Abschnittsgruppen tragen den
     * Gruppennamen. Tiefer verschachtelte Gruppen bleiben außen vor.
     *
     * @return list<array{id: string, name: string, group: string|null}>
     */
    public function sections(string $notebookId): array {
        $sections = [];
        foreach ($this->named($this->graphPages($this->base . '/me/onenote/notebooks/' . rawurlencode($notebookId) . '/sections', ['$select' => 'id,displayName'], 'Graph OneNote-Abschnitte')) as $section) {
            $sections[] = [...$section, 'group' => null];
        }

        foreach ($this->graphPages($this->base . '/me/onenote/notebooks/' . rawurlencode($notebookId) . '/sectionGroups', ['$select' => 'id,displayName', '$expand' => 'sections($select=id,displayName)'], 'Graph OneNote-Abschnittsgruppen') as $group) {
            if (! is_array($group)) {
                continue;
            }
            foreach ($this->named((array) ($group['sections'] ?? [])) as $section) {
                $sections[] = [...$section, 'group' => (string) ($group['displayName'] ?? '')];
            }
        }

        return $sections;
    }

    /** @return list<array{id: string, title: string, modified: string|null}> */
    public function pages(string $sectionId): array {
        $pages = [];
        foreach ($this->graphPages($this->base . '/me/onenote/sections/' . rawurlencode($sectionId) . '/pages', ['$select' => 'id,title,lastModifiedDateTime', '$top' => '100'], 'Graph OneNote-Seiten') as $row) {
            if (is_array($row) && is_string($row['id'] ?? null) && $row['id'] !== '') {
                $pages[] = [
                    'id' => $row['id'],
                    'title' => (string) ($row['title'] ?? ''),
                    'modified' => is_string($row['lastModifiedDateTime'] ?? null) ? $row['lastModifiedDateTime'] : null,
                ];
            }
        }

        return $pages;
    }

    /** HTML-Inhalt einer Seite. */
    public function pageContent(string $pageId): string {
        $response = $this->api->getResponse($this->base . '/me/onenote/pages/' . rawurlencode($pageId) . '/content');
        if (! $response->successful()) {
            throw new RuntimeException('Graph OneNote-Seiteninhalt fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return $response->body();
    }

    /**
     * @param  iterable<mixed>  $rows
     * @return list<array{id: string, name: string}>
     */
    private function named(iterable $rows): array {
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row) && is_string($row['id'] ?? null) && $row['id'] !== '') {
                $out[] = ['id' => $row['id'], 'name' => (string) ($row['displayName'] ?? $row['id'])];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $query
     * @return Generator<int, mixed>
     */
    private function graphPages(string $firstUrl, array $query, string $label): Generator {
        $paginator = new CursorPaginator(function (?string $nextLink) use ($firstUrl, $query, $label): CursorPage {
            $response = $nextLink === null
                ? $this->api->getResponse($firstUrl, $query)
                : $this->api->getResponse($nextLink);
            if (! $response->successful()) {
                throw new RuntimeException($label . ' fehlgeschlagen (HTTP ' . $response->status() . ').');
            }
            $data = (array) $response->json();
            $next = $data['@odata.nextLink'] ?? null;

            return new CursorPage((array) ($data['value'] ?? []), is_string($next) && $next !== '' ? $next : null);
        }, maxPages: 200);

        yield from $paginator;
    }
}
