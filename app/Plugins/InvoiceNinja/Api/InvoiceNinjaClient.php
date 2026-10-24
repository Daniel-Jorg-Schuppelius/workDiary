<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceNinjaClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoiceNinja\Api;

use APIToolkit\API\Authentication\ApiKeyAuthentication;
use App\Plugins\InvoiceNinja\Exceptions\InvoiceNinjaApiException;
use App\Plugins\InvoiceNinja\InvoiceNinjaPlugin;
use App\Plugins\Support\{GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\TaskSync\IncompleteRemoteReadException;
use CommonToolkit\Helper\Data\StringHelper;

/**
 * Lesender Zugriff auf die Invoice-Ninja-API v5: Rechnungen, Gutschriften und
 * Ausgaben seitenweise, Währungen aus den Stammdaten (`statics`).
 */
class InvoiceNinjaClient {
    use GuardsPluginApiResponses;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $apiToken,
        private readonly int $pageSize,
    ) {}

    /**
     * Eine Seite einer Liste (`invoices`, `credits`, `expenses`).
     *
     * @param  array<string, int|string|list<string>>  $query
     * @return array{rows: list<array<string, mixed>>, total_pages: int}
     */
    public function page(string $entity, array $query, int $page): array {
        $body = $this->guard($this->api()->getResponse($this->url('/' . $entity), $query + ['per_page' => $this->pageSize, 'page' => $page]), '/' . $entity);
        $rows = $body['data'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new IncompleteRemoteReadException('invalid_response');
        }
        $pages = data_get($body, 'meta.pagination.total_pages');

        return ['rows' => array_values(array_filter($rows, 'is_array')), 'total_pages' => is_numeric($pages) ? (int) $pages : $page];
    }

    /** @return array<string, string> Währungs-ID → ISO-Code */
    public function currencies(): array {
        $body = $this->guard($this->api()->getResponse($this->url('/statics')), '/statics');
        $codes = [];
        foreach ((array) ($body['currencies'] ?? []) as $currency) {
            if (is_array($currency) && is_scalar($currency['id'] ?? null) && is_string($currency['code'] ?? null)) {
                $codes[(string) $currency['id']] = strtoupper($currency['code']);
            }
        }

        return $codes;
    }

    /**
     * Firma des Tokens: Hash-ID (Link in die Oberfläche) und Firmenwährung —
     * der letzte Rückfall, wenn weder Kunde noch Kundengruppe eine Währung
     * setzen. Der Endpunkt ist ein POST, liest aber nur.
     *
     * @return array{id: ?string, currency_id: ?string}
     */
    public function company(): array {
        $body = $this->guard($this->api()->postJson($this->url('/companies/current'), [], ['retry_non_idempotent' => true]), '/companies/current');
        $company = is_array($body['data'] ?? null) ? $body['data'] : $body;

        return [
            'id' => StringHelper::trimToNull($company['id'] ?? null),
            'currency_id' => StringHelper::trimToNull(data_get($company, 'settings.currency_id')),
        ];
    }

    /** Währung einer Kundengruppe; null, wenn die Gruppe keine setzt oder nicht lesbar ist. */
    public function groupCurrencyId(string $groupId): ?string {
        if (preg_match('/^[A-Za-z0-9]+$/', $groupId) !== 1) {
            return null;
        }
        $response = $this->api()->getResponse($this->url('/group_settings/' . $groupId));
        if (in_array($response->status(), [403, 404], true)) {
            return null;
        }
        $body = $this->guard($response, '/group_settings/{id}');

        return StringHelper::trimToNull(data_get($body, 'data.settings.currency_id') ?? data_get($body, 'settings.currency_id'));
    }

    /** Leseprobe (Healthcheck). */
    public function probe(): void {
        $this->page('invoices', [], 1);
    }

    private function url(string $path): string {
        return $this->baseUrl . '/api/v1' . $path;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = $this->http->client(InvoiceNinjaPlugin::ID, $this->baseUrl);
            $this->api->setAuthentication(new ApiKeyAuthentication($this->apiToken, 'X-API-TOKEN', ['X-Requested-With' => 'XMLHttpRequest']));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return InvoiceNinjaApiException::class;
    }

    protected function apiLabel(): string {
        return 'Invoice Ninja';
    }
}
