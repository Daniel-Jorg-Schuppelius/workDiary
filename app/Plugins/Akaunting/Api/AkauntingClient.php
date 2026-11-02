<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AkauntingClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Akaunting\Api;

use APIToolkit\API\Authentication\BasicAuthentication;
use App\Plugins\Akaunting\AkauntingPlugin;
use App\Plugins\Akaunting\Exceptions\AkauntingApiException;
use App\Plugins\Support\{GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\TaskSync\{IncompleteRemoteReadException, OffsetPage, RemotePages};

/**
 * Lesender Zugriff auf die Akaunting-API: Belege (`documents`) je Art über die
 * Suche `type:<art>`, seitenweise bis `meta.last_page`. Filter und Sortierung
 * stehen im Suchtext (`updated_at>="…"`, `sort:`), denn `sort`/`direction`
 * kennen nur wenige Spalten. Versteht Akaunting den Suchtext nicht, liefert es
 * still alle Belege — deshalb prüft der Client die Art jeder Zeile.
 */
class AkauntingClient {
    use GuardsPluginApiResponses;

    /** Seiten, die der Löschabgleich je Art höchstens liest. */
    private const MAX_ID_PAGES = 500;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $email,
        private readonly string $password,
        private readonly int $companyId,
        private readonly int $pageSize,
    ) {}

    /**
     * Eine Seite der Belege einer Art (`invoice`, `bill`): ohne `$changedSince`
     * die jüngsten zuerst, sonst die seither geänderten in Änderungsfolge.
     * `$changedSince` ist Ortszeit der Akaunting-Instanz (`Y-m-d H:i:s`).
     *
     * @return array{rows: list<array<string, mixed>>, last_page: int}
     */
    public function documents(string $type, int $page, ?string $changedSince = null): array {
        $result = $this->page($type, $changedSince !== null
            ? ' updated_at>="' . $changedSince . '" sort:updated_at,id'
            : ' sort:-issued_at,-id', $page);

        return ['rows' => $result['rows'], 'last_page' => $result['last_page']];
    }

    /**
     * Alle Beleg-IDs einer Art nach ID — Grundlage des Löschabgleichs. Ändert
     * sich die Gesamtzahl beim Blättern oder fehlt etwas, wirft der Aufruf.
     *
     * @return list<string>
     */
    public function documentIds(string $type): array {
        $ids = [];
        $total = null;
        $pages = RemotePages::offset(function (int $page) use ($type, &$total): OffsetPage {
            $result = $this->page($type, ' sort:id', $page);
            if ($result['total'] === null) {
                throw new IncompleteRemoteReadException('listing_changed');
            }
            $total = $result['total'];

            return new OffsetPage($result['rows'], $result['rows'] !== [] && $page < $result['last_page'], $total);
        }, self::MAX_ID_PAGES);

        foreach ($pages as $row) {
            if (is_scalar($row['id'] ?? null)) {
                $ids[] = (string) $row['id'];
            }
        }
        if (count($ids) !== $total) {
            throw new IncompleteRemoteReadException('listing_changed');
        }

        return $ids;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, last_page: int, total: ?int}
     */
    private function page(string $type, string $searchSuffix, int $page): array {
        $body = $this->guard($this->api()->getResponse($this->baseUrl . '/api/documents', [
            'search' => 'type:' . $type . $searchSuffix,
            'limit' => $this->pageSize,
            'page' => $page,
        ]), '/api/documents');
        $rows = $body['data'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new IncompleteRemoteReadException('invalid_response');
        }
        foreach ($rows as $row) {
            if (! is_array($row) || ($row['type'] ?? null) !== $type) {
                throw new IncompleteRemoteReadException('invalid_response');
            }
        }
        $last = data_get($body, 'meta.last_page');
        $total = data_get($body, 'meta.total');

        return [
            'rows' => array_values(array_filter($rows, 'is_array')),
            'last_page' => is_numeric($last) ? (int) $last : $page,
            'total' => is_numeric($total) ? (int) $total : null,
        ];
    }

    /**
     * Eine Seite des Artikelstamms (`GET /api/items`, Feature 185, gap-09):
     * `Item`-Resource mit `id`, `type` (product/service), `name`,
     * `description`, `sale_price`, `enabled`, `taxes.data[].tax.rate`;
     * Umschlag `data` + `meta.last_page`/`meta.total`, Seitengröße über `limit`.
     *
     * @return array{rows: list<array<string, mixed>>, last_page: int, total: ?int}
     */
    public function items(int $page): array {
        $body = $this->guard($this->api()->getResponse($this->baseUrl . '/api/items', [
            'search' => 'sort:id',
            'limit' => $this->pageSize,
            'page' => $page,
        ]), '/api/items');
        $rows = $body['data'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new IncompleteRemoteReadException('invalid_response');
        }
        $last = data_get($body, 'meta.last_page');
        $total = data_get($body, 'meta.total');

        return [
            'rows' => array_values(array_filter($rows, 'is_array')),
            'last_page' => is_numeric($last) ? (int) $last : $page,
            'total' => is_numeric($total) ? (int) $total : null,
        ];
    }

    /** Leseprobe (Healthcheck). */
    public function probe(): void {
        $this->documents('invoice', 1);
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = $this->http->client(AkauntingPlugin::ID, $this->baseUrl);
            $this->api->setAuthentication(new BasicAuthentication($this->email, $this->password, ['X-Company' => (string) $this->companyId]));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return AkauntingApiException::class;
    }

    protected function apiLabel(): string {
        return 'Akaunting';
    }
}
