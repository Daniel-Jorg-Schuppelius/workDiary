<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WooCommerceClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\WooCommerce\Api;

use APIToolkit\API\Authentication\BasicAuthentication;
use App\Plugins\Support\{GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\TaskSync\IncompleteRemoteReadException;
use App\Plugins\WooCommerce\Exceptions\WooCommerceApiException;
use App\Plugins\WooCommerce\WooCommercePlugin;
use Carbon\CarbonImmutable;
use Generator;

/**
 * Lesender Zugriff auf die WooCommerce-REST-API v3. Consumer Key und Secret
 * gehen als Basic-Auth — WooCommerce nimmt das nur über HTTPS an, über HTTP
 * verlangte es OAuth 1.0a; der Client lehnt HTTP deshalb ab.
 */
class WooCommerceClient {
    use GuardsPluginApiResponses;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
        private readonly int $pageSize,
        private readonly int $maxPages,
    ) {}

    /**
     * Name der Website aus dem REST-Index. Ohne Anmeldung: Außerhalb der
     * WooCommerce-Routen hielte WordPress den Consumer Key für einen
     * Benutzernamen mit Anwendungspasswort und antwortete mit 401. Der Name ist
     * entbehrlich: ein gesperrter Index ergibt null statt eines Fehlers.
     */
    public function storeName(): ?string {
        $response = $this->http->client(WooCommercePlugin::ID, $this->baseUrl)->getResponse($this->baseUrl . '/wp-json/');
        if (! $response->successful()) {
            return null;
        }
        $name = $response->json('name');

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    /**
     * Seit `$since` angelegte oder geänderte Bestellungen (UTC), aufsteigend nach
     * ID; Entwürfe des Block-Checkouts bleiben außen vor.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function orders(CarbonImmutable $since): Generator {
        $lastTotal = null;
        for ($page = 1; $page <= $this->maxPages; $page++) {
            $response = $this->api()->getResponse($this->url('/orders'), [
                'modified_after' => $since->utc()->format('Y-m-d\TH:i:s'),
                'dates_are_gmt' => 'true',
                'orderby' => 'id',
                'order' => 'asc',
                'per_page' => $this->pageSize,
                'page' => $page,
            ]);
            $orders = $this->guard($response, '/orders');
            if (! array_is_list($orders)) {
                throw new IncompleteRemoteReadException('invalid_response');
            }
            // Wandert eine Bestellung vor der Seitengrenze in den Papierkorb, rückt eine andere unbemerkt nach vorn.
            $total = $response->header('X-WP-Total');
            if (is_numeric($total)) {
                if ($lastTotal !== null && (int) $total < $lastTotal) {
                    throw new IncompleteRemoteReadException('listing_changed');
                }
                $lastTotal = (int) $total;
            }
            foreach ($orders as $order) {
                if (is_array($order) && ($order['status'] ?? null) !== 'checkout-draft') {
                    yield $order;
                }
            }
            $pages = $response->header('X-WP-TotalPages');
            if ($orders === [] || (is_numeric($pages) ? $page >= (int) $pages : count($orders) < $this->pageSize)) {
                return;
            }
        }

        throw new IncompleteRemoteReadException('page_limit');
    }

    /** Leseprobe mit Leserecht auf Bestellungen (Healthcheck, Verbindung prüfen). */
    public function probe(): void {
        $this->guard($this->api()->getResponse($this->url('/orders'), ['per_page' => 1]), '/orders');
    }

    private function url(string $path): string {
        return $this->baseUrl . '/wp-json/wc/v3' . $path;
    }

    private function api(): PluginApiClient {
        if (! str_starts_with(strtolower($this->baseUrl), 'https://')) {
            throw new WooCommerceApiException('WooCommerce: Basic-Auth nur über HTTPS.', 0, null, 'https_required');
        }
        if ($this->api === null) {
            $this->api = $this->http->client(WooCommercePlugin::ID, $this->baseUrl);
            $this->api->setAuthentication(new BasicAuthentication($this->consumerKey, $this->consumerSecret));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return WooCommerceApiException::class;
    }

    protected function apiLabel(): string {
        return 'WooCommerce';
    }
}
