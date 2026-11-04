<?php
/*
 * Created on   : Wed Nov 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PcvisitClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\RemoteSupport\Api;

use APIToolkit\API\Authentication\{ApiKeyAuthentication, BearerAuthentication};
use App\Plugins\RemoteSupport\Exceptions\RemoteProviderException;
use App\Plugins\RemoteSupport\Providers\{RemoteProvider, RemoteSession};
use App\Plugins\RemoteSupport\RemoteSupportPlugin;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

/**
 * pcvisit GraphQL-API (`https://app.pcvisit.de/graphql`, Referenz und
 * Integrationshandbuch unter productive.api.productive.pcvisit.de): ein
 * Integrator-Token des Softwareherstellers und der Nutzer-API-Token der
 * Organisation ergeben über `SiRetrieveSignedJwt` (Kopf `x-api-key`) ein
 * JWT mit begrenzter Laufzeit, das als Bearer die Sitzungshistorie
 * `AcQuerySessionsHistory` liest (Zeitfenster in Millisekunden,
 * Cursor-Seiten). Gebucht werden nur abgeschlossene Sitzungen
 * (`state = SessionClosed`); das Gerät ist `target.computerId`, der Name
 * `computerDisplayName`. Fehler kommen als `Failure` mit `error` und
 * `comment_for_dev`; `TryAgainLater` nennt dort die Wartezeit.
 */
class PcvisitClient implements RemoteProvider {
    public const ID = 'pcvisit';

    public const DEFAULT_ENDPOINT = 'https://app.pcvisit.de/graphql';

    public const PAGE_SIZE = 100;

    public const MAX_PAGES = 200;

    /** Sitzungen ohne Zielgerät (etwa reine Beratung) tragen diesen Platzhalter nicht — sie fallen weg. */
    public const STATE_CLOSED = 'SessionClosed';

    private const JWT_CACHE_PREFIX = 'remote-support:pcvisit:jwt:1:';

    /** Das JWT gilt bis kurz vor `exp`; ohne lesbares `exp` eine knappe Stunde. */
    private const JWT_SAFETY_SECONDS = 60;

    private const JWT_DEFAULT_TTL_SECONDS = 3000;

    private const QUERY_JWT = <<<'GRAPHQL'
        query GetJWT($userApiToken: UserApiToken!, $integratorApiToken: IntegratorApiToken!) {
          SiRetrieveSignedJwt(userApiToken: $userApiToken, integratorApiToken: $integratorApiToken) {
            __typename
            ... on SignedJwtWasRetrieved { token customerUuid }
            ... on Failure { error comment_for_dev comment_for_user }
          }
        }
        GRAPHQL;

    private const QUERY_HISTORY = <<<'GRAPHQL'
        query GetWorkHistory($company: CustomerUuid!, $timeFrame: TimeFrame, $pagination: Pagination) {
          AcQuerySessionsHistory(company: $company, timeFrame: $timeFrame, pagination: $pagination) {
            __typename
            ... on SessionProtocolEdge {
              totalCount
              batchLoadingIsStillActive
              nodes {
                cursor
                value {
                  id name sessionCreatedAt sessionFinishedAt sessionCompanyUuid state type duration customDuration invoiceState
                  participants { isSessionCreator supporterContact { email } }
                  target { computerId computerDisplayName computerHostName }
                  clientCompany { clientId clientName }
                }
              }
            }
            ... on Failure { error comment_for_dev comment_for_user }
          }
        }
        GRAPHQL;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly ?string $integratorToken,
        private readonly ?string $userToken,
        private readonly string $endpoint = self::DEFAULT_ENDPOINT,
        private readonly ?int $organizationId = null,
    ) {}

    public function id(): string {
        return self::ID;
    }

    public function isConfigured(): bool {
        return $this->integratorToken !== null && $this->integratorToken !== ''
            && $this->userToken !== null && $this->userToken !== '';
    }

    public function probe(): void {
        $this->jwt(force: true);
    }

    public function fetchSessions(CarbonImmutable $from, CarbonImmutable $to): array {
        if (! $this->isConfigured()) {
            return [];
        }

        $jwt = $this->jwt();
        $sessions = [];
        $cursor = null;
        $retriedToken = false;

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $pagination = ['lengthStartingFromOffset' => self::PAGE_SIZE];
            if ($cursor !== null) {
                $pagination['cursor'] = $cursor;
            }
            $result = $this->graphql(self::QUERY_HISTORY, [
                'company' => $jwt['customer_uuid'],
                'timeFrame' => ['startDate' => $from->getTimestampMs(), 'endDate' => $to->getTimestampMs()],
                'pagination' => $pagination,
            ], new BearerAuthentication($jwt['token']), 'AcQuerySessionsHistory');

            if (($result['__typename'] ?? '') === 'Failure') {
                // Ein abgelaufenes JWT wird genau einmal erneuert.
                if (! $retriedToken && ($result['error'] ?? '') === 'TokenExpired') {
                    $retriedToken = true;
                    $jwt = $this->jwt(force: true);
                    $page--;

                    continue;
                }
                throw $this->failure($result, 'Sitzungshistorie');
            }

            $nodes = $result['nodes'] ?? null;
            if (! is_array($nodes)) {
                throw RemoteProviderException::for(self::ID, 'Sitzungshistorie ohne Feld „nodes“.');
            }

            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }
                $session = $this->mapProtocol((array) ($node['value'] ?? []));
                if ($session !== null) {
                    $sessions[] = $session;
                }
                $cursor = is_string($node['cursor'] ?? null) ? $node['cursor'] : $cursor;
            }

            // Eine Seite mit weniger Knoten als angefordert ist die letzte.
            if (count($nodes) < self::PAGE_SIZE || $cursor === null) {
                return $sessions;
            }
        }

        throw RemoteProviderException::incomplete(self::ID, 'page_limit', $sessions);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function mapProtocol(array $value): ?RemoteSession {
        $target = is_array($value['target'] ?? null) ? $value['target'] : [];
        $remoteId = trim((string) ($target['computerId'] ?? ''));
        $created = $value['sessionCreatedAt'] ?? null;
        $finished = $value['sessionFinishedAt'] ?? null;
        if ($remoteId === '' || ($value['state'] ?? '') !== self::STATE_CLOSED || ! is_numeric($created) || ! is_numeric($finished)) {
            return null;
        }

        $startedAt = CarbonImmutable::createFromTimestampMsUTC((int) $created);
        // Die in pcvisit korrigierte Dauer (Leistungserfassung) geht vor der gemessenen.
        $duration = is_numeric($value['customDuration'] ?? null) ? (int) $value['customDuration'] : (is_numeric($value['duration'] ?? null) ? (int) $value['duration'] : null);
        $endedAt = $duration !== null && $duration > 0
            ? $startedAt->addMilliseconds($duration)
            : CarbonImmutable::createFromTimestampMsUTC((int) $finished);

        $alias = trim((string) ($target['computerDisplayName'] ?? ''));
        if ($alias === '') {
            $alias = trim((string) ($target['computerHostName'] ?? ''));
        }
        $alias = ($alias !== '' && $alias !== $remoteId) ? $alias : null;

        $client = is_array($value['clientCompany'] ?? null) ? trim((string) ($value['clientCompany']['clientName'] ?? '')) : '';
        $supporter = '';
        foreach ((array) ($value['participants'] ?? []) as $participant) {
            if (is_array($participant) && ($participant['isSessionCreator'] ?? false) === true) {
                $supporter = trim((string) (($participant['supporterContact'] ?? [])['email'] ?? ''));
                break;
            }
        }
        $note = implode(' · ', array_filter([
            trim((string) ($value['name'] ?? '')),
            $client,
            $supporter,
        ]));

        return new RemoteSession(
            provider: self::ID,
            sessionId: (string) ($value['id'] ?? ($remoteId . '|' . $startedAt->getTimestamp())),
            remoteId: $remoteId,
            startedAt: $startedAt,
            endedAt: $endedAt,
            note: $note !== '' ? $note : null,
            alias: $alias,
        );
    }

    /**
     * JWT je Organisation und Nutzer-Token aus dem Cache (nur Arrays im Cache,
     * Befund 2026-10-20); erneuert, sobald `exp` näher als die Sicherheitsspanne liegt.
     *
     * @return array{token: string, customer_uuid: string, exp: int}
     */
    private function jwt(bool $force = false): array {
        $key = self::JWT_CACHE_PREFIX . (int) $this->organizationId . ':' . substr(CryptoHelper::hash((string) $this->userToken, HashAlgorithm::SHA256), 0, 16);
        if (! $force) {
            $cached = Cache::get($key);
            if (is_array($cached) && is_string($cached['token'] ?? null) && is_string($cached['customer_uuid'] ?? null)
                && is_int($cached['exp'] ?? null) && time() < $cached['exp'] - self::JWT_SAFETY_SECONDS) {
                return ['token' => $cached['token'], 'customer_uuid' => $cached['customer_uuid'], 'exp' => $cached['exp']];
            }
        }

        $result = $this->graphql(self::QUERY_JWT, [
            'userApiToken' => (string) $this->userToken,
            'integratorApiToken' => (string) $this->integratorToken,
        ], new ApiKeyAuthentication((string) $this->integratorToken, 'x-api-key'), 'SiRetrieveSignedJwt');

        if (($result['__typename'] ?? '') === 'Failure') {
            throw $this->failure($result, 'Anmeldung');
        }
        $token = $result['token'] ?? null;
        $customerUuid = $result['customerUuid'] ?? null;
        if (! is_string($token) || $token === '' || ! is_string($customerUuid) || $customerUuid === '') {
            throw RemoteProviderException::for(self::ID, 'Anmeldung ohne Token oder Kunden-UUID beantwortet.');
        }

        $exp = self::jwtExpiry($token) ?? time() + self::JWT_DEFAULT_TTL_SECONDS;
        $jwt = ['token' => $token, 'customer_uuid' => $customerUuid, 'exp' => $exp];
        $ttl = $exp - self::JWT_SAFETY_SECONDS - time();
        if ($ttl > 0) {
            Cache::put($key, $jwt, $ttl);
        }

        return $jwt;
    }

    /** `exp` aus dem JWT-Payload (Base64url), ohne Signaturprüfung — nur für die Erneuerung. */
    public static function jwtExpiry(string $token): ?int {
        $parts = explode('.', $token);
        if (count($parts) < 2) {
            return null;
        }
        $payload = CryptoHelper::base64UrlDecode($parts[1]);
        if (! is_string($payload) || $payload === '' || ! JsonHelper::isValid($payload)) {
            return null;
        }
        $claims = JsonHelper::decode($payload);
        $exp = is_array($claims) ? ($claims['exp'] ?? null) : null;

        return is_numeric($exp) ? (int) $exp : null;
    }

    /**
     * Eine GraphQL-Operation; liefert das Ergebnisobjekt unter `data.<operation>`.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function graphql(string $query, array $variables, ApiKeyAuthentication|BearerAuthentication $auth, string $operation): array {
        $api = $this->api();
        $api->setAuthentication($auth);
        // Lesender POST: das Toolkit wiederholt ihn bei 429/503 nur mit dieser Option.
        $response = $api->postJson($this->endpoint, ['query' => $query, 'variables' => $variables], ['timeout' => 30, 'retry_non_idempotent' => true]);

        return $this->result($response, $operation);
    }

    /**
     * @return array<string, mixed>
     */
    private function result(Response $response, string $operation): array {
        if (! $response->successful()) {
            throw RemoteProviderException::for(self::ID, sprintf('HTTP %d bei %s.', $response->status(), $operation));
        }
        $errors = $response->json('errors');
        if (is_array($errors) && $errors !== []) {
            $first = is_array($errors[0] ?? null) ? $errors[0] : [];
            throw RemoteProviderException::for(self::ID, sprintf('%s: %s', $operation, (string) ($first['message'] ?? 'GraphQL-Fehler')));
        }
        $result = $response->json('data.' . $operation);
        if (! is_array($result)) {
            throw RemoteProviderException::for(self::ID, sprintf('%s ohne Ergebnis beantwortet.', $operation));
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $failure
     */
    private function failure(array $failure, string $what): RemoteProviderException {
        $error = (string) ($failure['error'] ?? 'Failure');
        $comment = trim((string) ($failure['comment_for_dev'] ?? ''));

        return RemoteProviderException::for(self::ID, sprintf('%s abgelehnt: %s%s', $what, $error, $comment !== '' ? ' (' . $comment . ')' : ''));
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client(RemoteSupportPlugin::ID, $this->endpointBase());
        }

        return $this->api;
    }

    private function endpointBase(): string {
        $scheme = (string) parse_url($this->endpoint, PHP_URL_SCHEME);
        $host = (string) parse_url($this->endpoint, PHP_URL_HOST);

        return $scheme !== '' && $host !== '' ? $scheme . '://' . $host : $this->endpoint;
    }
}
