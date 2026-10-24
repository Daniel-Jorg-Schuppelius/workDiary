<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlpiClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Glpi\Api;

use APIToolkit\API\Authentication\ApiKeyAuthentication;
use APIToolkit\API\Pagination\CursorPage;
use App\Plugins\Glpi\Exceptions\GlpiApiException;
use App\Plugins\Glpi\GlpiPlugin;
use App\Plugins\Support\{GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\TaskSync\{IncompleteRemoteReadException, RemotePages};
use CommonToolkit\Helper\Data\StringHelper;
use Generator;
use Throwable;

/**
 * Lesezugriff auf die REST-API von GLPI (`/apirest.php`, auch unter GLPI 11).
 * Der Client öffnet beim ersten Aufruf eine Sitzung mit dem Benutzer-Token (und
 * App-Token, falls gesetzt) und beendet sie mit `killSession`. Tickets einer
 * Entität liest er nach `changeActiveEntities` über `range` bis zur
 * Gesamtzahl aus `Content-Range`. Texte kommen unmaskiert
 * (`X-GLPI-Sanitized-Content: false` ab 10.0.3; GLPI 11 speichert sie roh).
 */
class GlpiClient {
    use GuardsPluginApiResponses;

    private ?PluginApiClient $api = null;

    private ?string $activeEntity = null;

    private bool $sessionOpen = false;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $baseUrl,
        private readonly ?string $appToken,
        private readonly string $userToken,
        private readonly int $pageSize,
        private readonly int $maxPages,
    ) {}

    /** @return array{id: ?string, name: ?string, version: ?string} */
    public function account(): array {
        $session = $this->get('/getFullSession')['session'] ?? null;
        $name = is_array($session) && is_string($session['glpifriendlyname'] ?? null) && $session['glpifriendlyname'] !== ''
            ? $session['glpifriendlyname']
            : (is_array($session) ? ($session['glpiname'] ?? null) : null);

        return [
            'id' => is_array($session) && is_scalar($session['glpiID'] ?? null) ? (string) $session['glpiID'] : null,
            'name' => is_string($name) ? $name : null,
            'version' => null,
        ];
    }

    /**
     * Entitäten des Benutzers samt Unterentitäten; jede ist eine eigene Quelle.
     *
     * @return list<array{id: string, key: string, name: string}>
     */
    public function projects(): array {
        $entities = $this->get('/getMyEntities', ['is_recursive' => 'true'])['myentities'] ?? null;
        if (! is_array($entities)) {
            throw new IncompleteRemoteReadException('invalid_response');
        }
        $projects = [];
        foreach ($entities as $entity) {
            $id = is_array($entity) && is_scalar($entity['id'] ?? null) ? (string) $entity['id'] : '';
            // Über mehrere Profil-Entitäten kann dieselbe Entität mehrfach kommen.
            if (ctype_digit($id) && is_string($entity['name'] ?? null) && ! isset($projects[$id])) {
                $projects[$id] = ['id' => $id, 'key' => $id, 'name' => self::unmask($entity['name'])];
            }
        }

        return array_values($projects);
    }

    /**
     * Tickets genau dieser Entität (ohne Unterentitäten und Papierkorb). Ohne
     * `$modifiedFrom` nach ID; mit `$modifiedFrom` („Y-m-d H:i:s“ in der
     * Zeitzone des API-Kontos) neueste Änderung zuerst, bis die Seite älter
     * ist — GLPI kennt in der Liste keinen Änderungsfilter.
     *
     * @return Generator<int, mixed>
     */
    public function tickets(string $entityId, ?string $modifiedFrom = null): Generator {
        $this->activate($entityId);
        $total = null;

        return RemotePages::all(function (?string $start) use ($entityId, $modifiedFrom, &$total): CursorPage {
            $this->activate($entityId);
            $from = (int) ($start ?? 0);
            $response = $this->api()->getResponse($this->url('/Ticket'), [
                'range' => $from . '-' . ($from + $this->pageSize - 1),
                'sort' => $modifiedFrom === null ? 'id' : 'date_mod',
                'order' => $modifiedFrom === null ? 'ASC' : 'DESC',
                'get_hateoas' => 'false',
            ]);
            $this->assertOk($response, '/Ticket');
            $items = $response->json();
            if (! is_array($items) || ! array_is_list($items)) {
                throw new IncompleteRemoteReadException('invalid_response');
            }
            // „0-49/200“: weiter, solange das Ende vor der Gesamtzahl liegt. Schrumpft die
            // Gesamtzahl beim Blättern, rücken Tickets über die Seitengrenze — kein vollständiges Lesen.
            $more = false;
            if (preg_match('#^\s*\d+-(\d+)/(\d+)\s*$#', (string) $response->header('Content-Range'), $m) === 1) {
                if ($total !== null && (int) $m[2] < $total) {
                    throw new IncompleteRemoteReadException('listing_changed');
                }
                $total = (int) $m[2];
                $more = (int) $m[1] + 1 < $total;
            }
            $last = end($items);
            if ($modifiedFrom !== null && is_array($last) && is_string($last['date_mod'] ?? null) && strcmp($last['date_mod'], $modifiedFrom) < 0) {
                $more = false;
            }

            return new CursorPage($items, $more && $items !== [] ? (string) ($from + count($items)) : null);
        }, $this->maxPages);
    }

    /**
     * Einzelnes Ticket aus der Sicht der Entität; null bei 404 oder ohne Leserecht (403).
     *
     * @return array<string, mixed>|null
     */
    public function ticket(string $ticketId, string $entityId): ?array {
        if (! ctype_digit($ticketId)) {
            return null;
        }
        $this->activate($entityId);
        $response = $this->api()->getResponse($this->url('/Ticket/' . $ticketId), ['get_hateoas' => 'false']);
        if (in_array($response->status(), [403, 404], true)) {
            return null;
        }

        /** @var array<string, mixed> */
        return $this->guard($response, '/Ticket/{id}');
    }

    /** Leseprobe der Instanz (Healthcheck): die Sitzung selbst. */
    public function probe(): void {
        $this->api();
    }

    /** Begrenzt die Sitzung auf eine Entität — GLPI filtert Listen nach der aktiven Entität. */
    private function activate(string $entityId): void {
        if (! ctype_digit($entityId)) {
            throw new IncompleteRemoteReadException('invalid_entity');
        }
        if ($this->activeEntity === $entityId) {
            return;
        }
        // Setzt nur die Entität der eigenen Sitzung — wiederholbar.
        $response = $this->api()->postJson($this->url('/changeActiveEntities'), ['entities_id' => (int) $entityId, 'is_recursive' => false], ['retry_non_idempotent' => true]);
        $this->assertOk($response, '/changeActiveEntities');
        // Ein abgelehnter Wechsel kommt mit HTTP 200 und `false` — dann gälte noch die alte Entität.
        if ($response->json() !== true) {
            throw new IncompleteRemoteReadException('entity_unavailable');
        }
        $this->activeEntity = $entityId;
    }

    /**
     * @param  array<string, string>  $query
     * @return array<mixed>
     */
    private function get(string $path, array $query = []): array {
        return $this->guard($this->api()->getResponse($this->url($path), $query), $path);
    }

    private function url(string $path): string {
        return $this->baseUrl . '/apirest.php' . $path;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $api = $this->http->client(GlpiPlugin::ID, $this->baseUrl);
            $api->setDefaultHeaders(($this->appToken !== null ? ['App-Token' => $this->appToken] : []) + [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-GLPI-Sanitized-Content' => 'false',
            ]);
            $api->setAuthentication(new ApiKeyAuthentication('user_token ' . $this->userToken, 'Authorization'));
            $session = $this->guard($api->getResponse($this->url('/initSession')), '/initSession');
            if (! is_string($session['session_token'] ?? null) || $session['session_token'] === '') {
                throw new IncompleteRemoteReadException('invalid_response');
            }
            // Nach der Anmeldung trägt nur noch das Sitzungstoken; das Benutzer-Token geht nicht weiter mit.
            $api->setAuthentication(new ApiKeyAuthentication($session['session_token'], 'Session-Token'));
            $this->api = $api;
            $this->sessionOpen = true;
        }

        return $this->api;
    }

    /** Sitzung beenden; je Lauf und Prüfung entsteht eine neue, liegen bleiben soll keine. */
    public function __destruct() {
        if ($this->sessionOpen && $this->api !== null) {
            try {
                $this->api->getResponse($this->url('/killSession'));
            } catch (Throwable) {
                // Ablaufende Sitzungen räumt GLPI selbst auf.
            }
        }
    }

    /** Maskierte Namen (GLPI vor 10.0.3, `&#38;`) entschlüsseln; rohe Texte bleiben, wie sie sind. */
    public static function unmask(string $text): string {
        return preg_match('/&#\d+;/', $text) === 1 ? StringHelper::htmlEntitiesToText($text) : $text;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return GlpiApiException::class;
    }

    protected function apiLabel(): string {
        return 'GLPI';
    }
}
