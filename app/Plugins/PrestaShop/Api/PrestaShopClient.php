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
use App\Plugins\Support\TaskSync\IncompleteRemoteReadException;
use Generator;

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

    /** Leseprobe mit Leserecht auf Bestellungen (Healthcheck, Verbindung prüfen). */
    public function probe(): void {
        $this->list('orders', ['display' => '[id]', 'limit' => '0,1']);
    }

    /**
     * @param  array<string, int|string>  $query
     * @return list<array<string, mixed>>
     */
    private function list(string $resource, array $query): array {
        $body = $this->guard($this->api()->getResponse($this->baseUrl . '/api/' . $resource, $query + ['output_format' => 'JSON']), '/api/' . $resource);
        if ($body === []) {
            return [];
        }
        $rows = $body[$resource] ?? null;
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new IncompleteRemoteReadException('invalid_response');
        }

        return array_values(array_filter($rows, 'is_array'));
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
