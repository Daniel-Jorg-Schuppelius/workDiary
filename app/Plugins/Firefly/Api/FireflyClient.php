<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FireflyClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Firefly\Api;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Plugins\Firefly\Exceptions\FireflyApiException;
use App\Plugins\Firefly\FireflyPlugin;
use App\Plugins\Support\{GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\TaskSync\IncompleteRemoteReadException;
use Carbon\CarbonImmutable;
use Generator;

/**
 * Lesender Zugriff auf die Firefly-III-API (JSON:API). Bewegungen eines
 * Zeitraums seitenweise; ändert sich die Gesamtzahl während des Blätterns
 * oder fehlen am Ende Gruppen, gilt der Lauf als unvollständig.
 */
class FireflyClient {
    use GuardsPluginApiResponses;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $accessToken,
        private readonly int $pageSize,
        private readonly int $maxPages,
    ) {}

    /** @return array{version: ?string, email: ?string} */
    public function about(): array {
        $about = $this->guard($this->api()->getResponse($this->url('/about')), '/about');
        $user = $this->guard($this->api()->getResponse($this->url('/about/user')), '/about/user');
        $version = data_get($about, 'data.version');
        $email = data_get($user, 'data.attributes.email');

        return ['version' => is_string($version) ? $version : null, 'email' => is_string($email) ? $email : null];
    }

    /**
     * Buchungsgruppen zwischen `$from` und `$to` (Tage, einschließlich).
     * Firefly blättert und zählt Buchungen (Splits), nicht Gruppen: eine
     * Gruppe kann an der Seitengrenze geteilt in zwei Seiten stehen, und
     * `total` ist die Zahl der Buchungen.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function transactionGroups(CarbonImmutable $from, CarbonImmutable $to): Generator {
        $total = null;
        $journals = [];
        for ($page = 1; $page <= $this->maxPages; $page++) {
            $body = $this->guard($this->api()->getResponse($this->url('/transactions'), [
                'type' => 'all',
                'start' => $from->toDateString(),
                'end' => $to->toDateString(),
                'limit' => $this->pageSize,
                'page' => $page,
            ]), '/transactions');
            $groups = $body['data'] ?? null;
            $count = data_get($body, 'meta.pagination.total');
            if (! is_array($groups) || ! array_is_list($groups) || ! is_numeric($count) || ($total !== null && (int) $count !== $total)) {
                throw new IncompleteRemoteReadException('listing_changed');
            }
            $total = (int) $count;
            foreach ($groups as $group) {
                if (is_array($group)) {
                    foreach ((array) data_get($group, 'attributes.transactions', []) as $split) {
                        if (is_array($split) && is_scalar($split['transaction_journal_id'] ?? null)) {
                            $journals[(string) $split['transaction_journal_id']] = true;
                        }
                    }

                    yield $group;
                }
            }
            $pages = data_get($body, 'meta.pagination.total_pages');
            if ($groups === [] || ! is_numeric($pages) || $page >= (int) $pages) {
                if (count($journals) !== $total) {
                    throw new IncompleteRemoteReadException('listing_changed');
                }

                return;
            }
        }

        throw new IncompleteRemoteReadException('page_limit');
    }

    /** Leseprobe (Healthcheck). */
    public function probe(): void {
        $this->guard($this->api()->getResponse($this->url('/about')), '/about');
    }

    private function url(string $path): string {
        return $this->baseUrl . '/api/v1' . $path;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = $this->http->client(FireflyPlugin::ID, $this->baseUrl);
            $this->api->setAuthentication(new BearerAuthentication($this->accessToken));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return FireflyApiException::class;
    }

    protected function apiLabel(): string {
        return 'Firefly III';
    }
}
