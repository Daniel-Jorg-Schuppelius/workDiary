<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FreeScoutClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\FreeScout\Api;

use APIToolkit\API\Authentication\ApiKeyAuthentication;
use APIToolkit\API\Pagination\CursorPage;
use App\Plugins\FreeScout\Exceptions\FreeScoutApiException;
use App\Plugins\FreeScout\FreeScoutPlugin;
use App\Plugins\Support\{GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\TaskSync\{IncompleteRemoteReadException, RemotePages};
use Generator;

/**
 * Lesezugriff auf die REST-API von FreeScout (Modul „API & Webhooks“,
 * `/api`): Postfächer und Konversationen im HAL-Format
 * (`_embedded`, Seiten über `page`/`pageSize` bis `page.totalPages`).
 * Der API-Schlüssel gilt instanzweit und nennt kein Konto.
 */
class FreeScoutClient {
    use GuardsPluginApiResponses;

    public const OPEN_STATUSES = 'active,pending';

    public const ALL_STATUSES = 'active,pending,closed,spam';

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $pageSize,
        private readonly int $maxPages,
    ) {}

    /** @return array{id: ?string, name: ?string, version: ?string} */
    public function account(): array {
        $this->probe();

        return ['id' => null, 'name' => null, 'version' => null];
    }

    /** @return list<array{id: string, key: string, name: string}> */
    public function projects(): array {
        $mailboxes = [];
        foreach ($this->pages('/mailboxes', 'mailboxes', []) as $mailbox) {
            $id = is_array($mailbox) && is_scalar($mailbox['id'] ?? null) ? (string) $mailbox['id'] : '';
            if (ctype_digit($id) && is_string($mailbox['name'] ?? null)) {
                $mailboxes[] = ['id' => $id, 'key' => is_string($mailbox['email'] ?? null) ? $mailbox['email'] : $id, 'name' => $mailbox['name']];
            }
        }

        return $mailboxes;
    }

    /**
     * Konversationen eines Postfachs samt Nachrichten, älteste zuerst (stabile Seiten);
     * `$updatedSince` als ISO 8601 in UTC.
     *
     * @return Generator<int, mixed>
     */
    public function conversations(string $mailboxId, string $statuses, ?string $updatedSince): Generator {
        if (! ctype_digit($mailboxId)) {
            throw new IncompleteRemoteReadException('invalid_mailbox');
        }

        return $this->pages('/conversations', 'conversations', array_filter([
            'mailboxId' => $mailboxId,
            // Ohne Filter kämen auch Entwürfe und gelöschte Konversationen.
            'state' => 'published',
            'status' => $statuses,
            'updatedSince' => $updatedSince,
            'embed' => 'threads',
            'sortField' => 'createdAt',
            'sortOrder' => 'asc',
        ], static fn (?string $v): bool => $v !== null));
    }

    /**
     * Einzelne Konversation mit Nachrichten; null bei 404 oder ohne Zugriff
     * auf das Postfach (403, Benutzer-Schlüssel).
     *
     * @return array<string, mixed>|null
     */
    public function conversation(string $conversationId): ?array {
        if (! ctype_digit($conversationId)) {
            return null;
        }
        $response = $this->api()->getResponse($this->url('/conversations/' . $conversationId), ['embed' => 'threads']);
        if (in_array($response->status(), [403, 404], true)) {
            return null;
        }

        /** @var array<string, mixed> */
        return $this->guard($response, '/conversations/{id}');
    }

    /** Leseprobe der Instanz (Healthcheck). */
    public function probe(): void {
        $this->guard($this->api()->getResponse($this->url('/mailboxes'), ['pageSize' => 1]), '/mailboxes');
    }

    /**
     * @param  array<string, string>  $query
     * @return Generator<int, mixed>
     */
    private function pages(string $path, string $embedded, array $query): Generator {
        return RemotePages::all(function (?string $page) use ($path, $embedded, $query): CursorPage {
            $current = (int) ($page ?? 1);
            $body = $this->guard($this->api()->getResponse($this->url($path), $query + ['page' => $current, 'pageSize' => $this->pageSize]), $path);
            // Eine leere Seite liefert FreeScout ohne `_embedded`.
            $items = $body['_embedded'][$embedded] ?? [];
            if (! is_array($items) || ! array_is_list($items) || ! is_array($body['page'] ?? null)) {
                throw new IncompleteRemoteReadException('invalid_response');
            }
            $totalPages = $body['page']['totalPages'] ?? null;
            $more = is_int($totalPages) ? $current < $totalPages : count($items) >= $this->pageSize;

            return new CursorPage($items, $more ? (string) ($current + 1) : null);
        }, $this->maxPages);
    }

    private function url(string $path): string {
        return $this->baseUrl . '/api' . $path;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = $this->http->client(FreeScoutPlugin::ID, $this->baseUrl);
            $this->api->setAuthentication(new ApiKeyAuthentication($this->apiKey, 'X-FreeScout-API-Key'));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return FreeScoutApiException::class;
    }

    protected function apiLabel(): string {
        return 'FreeScout';
    }
}
