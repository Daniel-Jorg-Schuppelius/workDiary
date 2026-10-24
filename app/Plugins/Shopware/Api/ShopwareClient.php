<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopwareClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Shopware\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2ClientCredentialsAuthentication;
use App\Plugins\Shopware\Exceptions\ShopwareApiException;
use App\Plugins\Shopware\ShopwarePlugin;
use App\Plugins\Support\{GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\TaskSync\IncompleteRemoteReadException;
use Carbon\CarbonImmutable;
use Generator;

/**
 * Lesender Zugriff auf die Shopware-6-Admin-API. Die Integration meldet sich
 * per Client Credentials an; das Token (zehn Minuten gültig) erneuert die
 * Authentifizierung selbst. Bestellungen kommen über die Suche mit Kriterien
 * und exakter Gesamtzahl. Die Suche ist ein POST, liest aber nur — das Toolkit
 * darf sie deshalb bei 429/503 wiederholen.
 */
class ShopwareClient {
    use GuardsPluginApiResponses;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly int $pageSize,
        private readonly int $maxPages,
    ) {}

    public function version(): ?string {
        $body = $this->guard($this->api()->getResponse($this->url('/_info/version')), '/_info/version');

        return is_string($body['version'] ?? null) ? $body['version'] : null;
    }

    /**
     * Seit `$since` angelegte oder geänderte Bestellungen (UTC), aufsteigend nach
     * ID; `updatedAt` ist bei nie geänderten Bestellungen leer, deshalb zählt
     * auch `createdAt`. Ein Zahlungsstatuswechsel (etwa die Erstattung) ändert
     * nur die Transaktion, deshalb zählt auch deren `updatedAt`.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function orders(CarbonImmutable $since): Generator {
        $from = $since->utc()->format('Y-m-d H:i:s.v');
        $seen = 0;
        $lastTotal = null;
        for ($page = 1; $page <= $this->maxPages; $page++) {
            $body = $this->guard($this->api()->postJson($this->url('/search/order'), [
                'page' => $page,
                'limit' => $this->pageSize,
                'total-count-mode' => 1,
                'sort' => [['field' => 'id', 'order' => 'ASC']],
                'filter' => [[
                    'type' => 'multi',
                    'operator' => 'or',
                    'queries' => [
                        ['type' => 'range', 'field' => 'updatedAt', 'parameters' => ['gte' => $from]],
                        ['type' => 'range', 'field' => 'createdAt', 'parameters' => ['gte' => $from]],
                        ['type' => 'range', 'field' => 'transactions.updatedAt', 'parameters' => ['gte' => $from]],
                    ],
                ]],
                'associations' => [
                    'currency' => (object) [],
                    'stateMachineState' => (object) [],
                    'lineItems' => (object) [],
                    'orderCustomer' => ['associations' => ['customer' => (object) []]],
                    'transactions' => [
                        'sort' => [['field' => 'createdAt', 'order' => 'ASC']],
                        'associations' => [
                            'stateMachineState' => (object) [],
                            'captures' => ['associations' => ['refunds' => ['associations' => ['stateMachineState' => (object) []]]]],
                        ],
                    ],
                ],
            ], ['retry_non_idempotent' => true]), '/search/order');
            $orders = $body['data'] ?? null;
            $total = $body['total'] ?? null;
            if (! is_array($orders) || ! array_is_list($orders) || ! is_int($total)) {
                throw new IncompleteRemoteReadException('invalid_response');
            }
            // Fällt eine Bestellung vor der Seitengrenze weg, rückt eine andere unbemerkt nach vorn.
            if ($lastTotal !== null && $total < $lastTotal) {
                throw new IncompleteRemoteReadException('listing_changed');
            }
            $lastTotal = $total;
            foreach ($orders as $order) {
                if (is_array($order)) {
                    $seen++;

                    yield $order;
                }
            }
            if ($seen >= $total) {
                return;
            }
            if ($orders === []) {
                throw new IncompleteRemoteReadException('listing_changed');
            }
        }

        throw new IncompleteRemoteReadException('page_limit');
    }

    /**
     * Welche der IDs der Shop noch führt (`search-ids` mit `equalsAny`).
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    public function existingOrderIds(array $ids): array {
        if ($ids === []) {
            return [];
        }
        $body = $this->guard($this->api()->postJson($this->url('/search-ids/order'), [
            'limit' => count($ids),
            'total-count-mode' => 1,
            'filter' => [['type' => 'equalsAny', 'field' => 'id', 'value' => $ids]],
        ], ['retry_non_idempotent' => true]), '/search-ids/order');
        $found = $body['data'] ?? null;
        if (! is_array($found) || ! array_is_list($found) || ! is_int($body['total'] ?? null) || $body['total'] !== count($found)) {
            throw new IncompleteRemoteReadException('invalid_response');
        }

        return array_values(array_intersect($ids, array_filter($found, 'is_string')));
    }

    /** Leseprobe mit Leserecht auf Bestellungen (Healthcheck, Verbindung prüfen). */
    public function probe(): void {
        $this->guard($this->api()->postJson($this->url('/search/order'), ['limit' => 1, 'total-count-mode' => 0], ['retry_non_idempotent' => true]), '/search/order');
    }

    private function url(string $path): string {
        return $this->baseUrl . '/api' . $path;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $grant = $this->http->clientCredentialsGrant(ShopwarePlugin::ID, $this->clientId, $this->clientSecret, $this->url('/oauth/token'));
            $this->api = $this->http->client(ShopwarePlugin::ID, $this->baseUrl);
            $this->api->setAuthentication(new OAuth2ClientCredentialsAuthentication($grant));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return ShopwareApiException::class;
    }

    protected function apiLabel(): string {
        return 'Shopware';
    }
}
