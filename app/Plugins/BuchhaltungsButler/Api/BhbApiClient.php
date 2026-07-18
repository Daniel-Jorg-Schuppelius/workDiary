<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BhbApiClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\BuchhaltungsButler\Api;

use App\Plugins\BuchhaltungsButler\BuchhaltungsButlerPlugin;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Illuminate\Http\Client\Response;

/**
 * Typisierte Oberfläche der BuchhaltungsButler-REST-API (MVP-432). HTTP über
 * {@see PluginApiClient} (php-api-toolkit: Retry/Backoff inkl. Retry-After).
 *
 * BHB-Vertrag: HTTP Basic `<Api Client>:<Api Secret>`, dazu das Pflicht-
 * Formfeld `api_key` in JEDEM Request (Mandantenselektion); die v1-API ist
 * POST-basiert. Limit 100 req/Kunde/min → Request-Intervall aus der Config.
 * Endpunkt-Pfade sind konfigurierbar (Doku bot-gesperrt, Pilot-Verifikation).
 */
class BhbApiClient {
    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $apiClient,
        private readonly string $apiSecret,
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly float $requestInterval,
    ) {}

    /** Billigste Lese-Probe für den Healthcheck (Add-on-/Auth-Erkennung). */
    public function probe(): void {
        $this->guard(
            $this->postForm((string) config('plugins.buchhaltungsbutler.probe_path', '/receipts/get'), ['limit' => 1]),
            'probe',
        );
    }

    /**
     * Beleg-Upload (PDF + Metadaten) als Multipart-POST.
     *
     * @param array<string, string|int|float> $fields
     * @return array<string, mixed>
     */
    public function uploadReceipt(string $contents, string $filename, array $fields = []): array {
        $path = (string) config('plugins.buchhaltungsbutler.receipt_upload_path', '/receipts/add');

        $multipart = [
            ['name' => 'api_key', 'contents' => $this->apiKey],
            ['name' => 'file', 'contents' => $contents, 'filename' => $filename],
        ];
        foreach ($fields as $name => $value) {
            $multipart[] = ['name' => (string) $name, 'contents' => (string) $value];
        }

        return (array) $this->guard(
            $this->authed($path, ['multipart' => $multipart]),
            $path,
        );
    }

    // ── Intern ──────────────────────────────────────────────────────────

    /** @param array<string, string|int|float> $fields Formfelder; api_key wird immer ergänzt. */
    private function postForm(string $path, array $fields = []): Response {
        return $this->authed($path, ['form_params' => ['api_key' => $this->apiKey] + $fields]);
    }

    /** @param array<string, mixed> $options */
    private function authed(string $path, array $options = []): Response {
        $options['headers'] = array_merge(
            (array) ($options['headers'] ?? []),
            ['Authorization' => 'Basic ' . base64_encode($this->apiClient . ':' . $this->apiSecret)],
        );

        return $this->api()->requestResponse('post', $this->baseUrl . $path, $options);
    }

    /** Ein Exemplar je Client, damit das Request-Intervall zwischen Requests wirkt. */
    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = $this->http->client(BuchhaltungsButlerPlugin::ID, $this->baseUrl, $this->requestInterval);
        }

        return $this->api;
    }

    /** @return array<mixed> */
    private function guard(Response $response, string $endpoint): array {
        if (! $response->successful()) {
            throw new BhbApiException(
                $response->status(),
                sprintf('BuchhaltungsButler %s: HTTP %d %s', $endpoint, $response->status(), mb_substr((string) $response->body(), 0, 300)),
                $endpoint,
            );
        }

        return (array) ($response->json() ?? []);
    }
}
