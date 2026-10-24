<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrestaShopClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\PrestaShop\Api;

use APIToolkit\API\Authentication\BasicAuthentication;
use App\Plugins\PrestaShop\Exceptions\PrestaShopApiException;
use App\Plugins\PrestaShop\PrestaShopPlugin;
use App\Plugins\Support\{GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\ShopOrders\RemoteOrder;
use App\Plugins\Support\TaskSync\IncompleteRemoteReadException;
use Generator;
use Illuminate\Http\Client\Response;

/**
 * Lesender Zugriff auf den PrestaShop-Webservice (JSON). Der Schlüssel geht
 * als Benutzer der Basic-Auth. Eine leere Liste kommt als `[]` statt als
 * Objekt mit leerem Ressourcenschlüssel. Die Liste kennt keine Gesamtzahl,
 * deshalb blättert der Client nach ID (`filter[id]=>[n]`) statt nach Offset —
 * eine gelöschte Bestellung verschiebt so keine Seite. Währungen und
 * Bestellstatus liest der Client einmal je Lauf.
 */
class PrestaShopClient {
    use GuardsPluginApiResponses;

    /** Höchstzahl Gutschriften je Abfrage; mehr deutet auf ein abgeschnittenes Ergebnis. */
    private const SLIP_LIMIT = 1000;

    private ?PluginApiClient $api = null;

    /** @var array<int, string>|null */
    private ?array $currencies = null;

    /** @var array<int, array{template: string, paid: bool, shipped: bool, logable: bool}>|null */
    private ?array $states = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $pageSize,
        private readonly int $maxPages,
    ) {}

    /**
     * Seit `$fromLocal` (Shop-Zeit, `Y-m-d H:i:s`) angelegte oder geänderte
     * Bestellungen, aufsteigend nach ID, seitenweise — der Aufrufer liest die
     * Kunden einer Seite gesammelt nach.
     *
     * @return Generator<int, list<array<string, mixed>>>
     */
    public function orderPages(string $fromLocal): Generator {
        $afterId = 0;
        for ($page = 0; $page < $this->maxPages; $page++) {
            $orders = $this->list('orders', [
                'display' => 'full',
                'filter[date_upd]' => '[' . $fromLocal . ',2999-12-31 23:59:59]',
                'filter[id]' => '>[' . $afterId . ']',
                'date' => 1,
                'sort' => '[id_ASC]',
                'limit' => '0,' . $this->pageSize,
            ]);
            if ($orders !== []) {
                yield $orders;
            }
            if (count($orders) < $this->pageSize) {
                return;
            }
            $lastId = end($orders)['id'] ?? null;
            if (! is_numeric($lastId) || (int) $lastId <= $afterId) {
                throw new IncompleteRemoteReadException('invalid_response');
            }
            $afterId = (int) $lastId;
        }

        throw new IncompleteRemoteReadException('page_limit');
    }

    /**
     * Kunden der genannten IDs.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    public function customers(array $ids): array {
        if ($ids === []) {
            return [];
        }
        $customers = [];
        foreach ($this->list('customers', ['display' => '[id,firstname,lastname,email,company,is_guest]', 'filter[id]' => '[' . implode('|', $ids) . ']']) as $row) {
            if (is_numeric($row['id'] ?? null)) {
                $customers[(int) $row['id']] = $row;
            }
        }

        return $customers;
    }

    /**
     * ISO-Code einer Währung. Die Liste enthält nur die Währungen des
     * Standardshops; Bestellungen anderer Shops (Multishop) tragen auch andere,
     * die der Client einzeln über alle Shops nachliest.
     */
    public function currency(int $id): ?string {
        if ($this->currencies === null) {
            $this->currencies = [];
            foreach ($this->list('currencies', ['display' => '[id,iso_code]']) as $row) {
                if (is_numeric($row['id'] ?? null) && is_string($row['iso_code'] ?? null)) {
                    $this->currencies[(int) $row['id']] = strtoupper($row['iso_code']);
                }
            }
        }
        if ($id > 0 && ! array_key_exists($id, $this->currencies)) {
            $response = $this->api()->getResponse($this->baseUrl . '/api/currencies/' . $id, ['id_shop' => 'all', 'output_format' => 'JSON']);
            $body = $response->status() === 404 ? [] : $this->guard($response, '/api/currencies/{id}');
            // Ohne `display` steht eine Einzelressource unter dem Singular, mit `display` unter dem Plural.
            $isoCode = data_get($body, 'currency.iso_code') ?? data_get($body, 'currencies.0.iso_code');
            $this->currencies[$id] = is_string($isoCode) ? strtoupper($isoCode) : '';
        }

        return ($this->currencies[$id] ?? '') !== '' ? $this->currencies[$id] : null;
    }

    /**
     * Bestellstatus mit ihren festen Merkmalen; `template` ist der technische
     * Name der Statusvorlage (z. B. `order_canceled`), nicht die Anzeige.
     * `delivery` fehlt bewusst: es schaltet nur den Lieferschein frei und steht
     * schon bei „in Vorbereitung“.
     *
     * @return array<int, array{template: string, paid: bool, shipped: bool, logable: bool}>
     */
    public function orderStates(): array {
        if ($this->states === null) {
            $this->states = [];
            foreach ($this->list('order_states', ['display' => '[id,template,paid,shipped,logable]']) as $row) {
                if (! is_numeric($row['id'] ?? null)) {
                    continue;
                }
                $template = $row['template'] ?? '';
                // Mehrsprachige Felder kommen als Liste [{id, value}]; die Vorlage ist in allen Sprachen gleich.
                if (is_array($template)) {
                    $template = is_array($template[0] ?? null) ? ($template[0]['value'] ?? '') : '';
                }
                $this->states[(int) $row['id']] = [
                    'template' => is_string($template) ? $template : '',
                    'paid' => (string) ($row['paid'] ?? '0') === '1',
                    'shipped' => (string) ($row['shipped'] ?? '0') === '1',
                    'logable' => (string) ($row['logable'] ?? '0') === '1',
                ];
            }
        }

        return $this->states;
    }

    /**
     * Welche der IDs der Shop noch führt.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    public function existingOrderIds(array $ids): array {
        if ($ids === []) {
            return [];
        }
        $present = [];
        foreach ($this->list('orders', ['display' => '[id]', 'filter[id]' => '[' . implode('|', $ids) . ']', 'limit' => '0,' . count($ids)]) as $row) {
            if (is_scalar($row['id'] ?? null) && in_array((string) $row['id'], $ids, true)) {
                $present[] = (string) $row['id'];
            }
        }

        return $present;
    }

    /**
     * Gutschriften je Bestellung als Bruttobeträge (Produkte + Versand, wie
     * die Verwaltung sie zeigt); null, wenn der Schlüssel `order_slip` nicht
     * lesen darf.
     *
     * @param  list<int>  $orderIds
     * @return array<int, list<string>>|null
     */
    public function refundsByOrder(array $orderIds): ?array {
        if ($orderIds === []) {
            return [];
        }
        $response = $this->api()->getResponse($this->baseUrl . '/api/order_slip', [
            'display' => '[id,id_order,total_products_tax_incl,total_shipping_tax_incl]',
            'filter[id_order]' => '[' . implode('|', $orderIds) . ']',
            'limit' => '0,' . self::SLIP_LIMIT,
            'output_format' => 'JSON',
        ]);
        if (self::notPermitted($response)) {
            return null;
        }
        $slips = $this->rows($this->guard($response, '/api/order_slip'), 'order_slips');
        if (count($slips) >= self::SLIP_LIMIT) {
            throw new IncompleteRemoteReadException('page_limit');
        }
        $refunds = [];
        foreach ($slips as $slip) {
            $products = RemoteOrder::amount($slip['total_products_tax_incl'] ?? null);
            $shipping = RemoteOrder::amount($slip['total_shipping_tax_incl'] ?? null);
            if (! is_numeric($slip['id_order'] ?? null) || $products === null || $shipping === null) {
                throw new IncompleteRemoteReadException('invalid_response');
            }
            $refunds[(int) $slip['id_order']][] = $products;
            $refunds[(int) $slip['id_order']][] = $shipping;
        }

        return $refunds;
    }

    /**
     * Zeitzone des Shops (`PS_TIMEZONE`); null, wenn der Schlüssel
     * `configurations` nicht lesen darf oder der Wert keine Zeitzone ist. Bei
     * mehreren Shops gilt der globale Eintrag.
     */
    public function shopTimezone(): ?string {
        $response = $this->api()->getResponse($this->baseUrl . '/api/configurations', [
            'display' => '[name,value,id_shop,id_shop_group]',
            'filter[name]' => '[PS_TIMEZONE]',
            'output_format' => 'JSON',
        ]);
        if (self::notPermitted($response)) {
            return null;
        }
        $rows = $this->rows($this->guard($response, '/api/configurations'), 'configurations');
        usort($rows, static fn (array $a, array $b): int => (int) ! empty($a['id_shop']) <=> (int) ! empty($b['id_shop']));
        $value = $rows[0]['value'] ?? null;

        return is_string($value) && in_array($value, timezone_identifiers_list(), true) ? $value : null;
    }

    /** Leseprobe mit Leserecht auf Bestellungen (Healthcheck, Verbindung prüfen). */
    public function probe(): void {
        $this->list('orders', ['display' => '[id]', 'limit' => '0,1']);
    }

    /**
     * @param  array<string, int|string>  $query
     * @return list<array<string, mixed>>
     */
    private function list(string $resource, array $query): array {
        return $this->rows($this->guard($this->api()->getResponse($this->baseUrl . '/api/' . $resource, $query + ['output_format' => 'JSON']), '/api/' . $resource), $resource);
    }

    /**
     * Zeilen einer Liste unter ihrem Sammelnamen (`orders`, `order_slips`).
     *
     * @param  array<mixed>  $body
     * @return list<array<string, mixed>>
     */
    private function rows(array $body, string $node): array {
        if ($body === []) {
            return [];
        }
        $rows = $body[$node] ?? null;
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new IncompleteRemoteReadException('invalid_response');
        }

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * Ressource für diesen Schlüssel nicht freigegeben (Code 26) oder ohne GET
     * (Code 25) — anders als ein ungültiger Schlüssel (Codes 16–21).
     */
    private static function notPermitted(Response $response): bool {
        return in_array($response->status(), [401, 405], true)
            && in_array((int) data_get($response->json(), 'errors.0.code'), [25, 26], true);
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = $this->http->client(PrestaShopPlugin::ID, $this->baseUrl);
            $this->api->setAuthentication(new BasicAuthentication($this->apiKey, ''));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return PrestaShopApiException::class;
    }

    protected function apiLabel(): string {
        return 'PrestaShop';
    }
}
