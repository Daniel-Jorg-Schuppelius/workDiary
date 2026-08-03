<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OidcClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Auth\Sso;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Models\SsoConnection;
use App\Plugins\Support\PluginHttpFactory;
use Illuminate\Support\Facades\{Cache, Log};
use Illuminate\Support\Str;
use Jose\Component\Core\{AlgorithmManager, JWKSet};
use Jose\Component\Signature\Algorithm\{ES256, PS256, RS256};
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;

/**
 * OIDC-Relying-Party für den SSO-Login (Feature 057, MVP-120). Transport über
 * {@see PluginHttpFactory::coreClient} (Toolkit-Fundament, FakePluginHttp-
 * testbar); ID-Token-Validierung über die bereits vorhandene
 * web-token/jwt-library (JWKS, RS256/PS256/ES256 — nie `none`).
 * Pflichtprüfungen nach OIDC Core §3.1.3.7: iss exakt, aud=client_id,
 * Signatur, exp, nonce, azp; PKCE (S256) UND nonce gemeinsam (RFC 9700).
 * PKCE-Erzeugung über das api-toolkit (toolkit-first).
 */
class OidcClient {
    private const DISCOVERY_TTL_SECONDS = 600;
    private const JWKS_TTL_SECONDS = 600;
    private const CLOCK_LEEWAY_SECONDS = 60;

    public function __construct(private readonly PluginHttpFactory $http) {}

    /**
     * OIDC-Discovery (.well-known) mit kurzem Cache. Der Issuer der Antwort
     * MUSS exakt dem konfigurierten Issuer entsprechen (Discovery §4.3).
     *
     * @return array<string, mixed>
     */
    public function discovery(SsoConnection $connection): array {
        $issuer = rtrim((string) $connection->issuer, '/');

        /** @var array<string, mixed> $document */
        $document = Cache::remember(
            "sso.oidc.discovery.{$connection->id}",
            self::DISCOVERY_TTL_SECONDS,
            function () use ($issuer): array {
                $response = $this->http->coreClient('sso-oidc', $issuer)
                    ->getResponse($issuer . '/.well-known/openid-configuration');

                if (! $response->successful() || ! is_array($response->json())) {
                    throw new SsoLoginException(__('sso.error.discovery_failed'));
                }

                return (array) $response->json();
            }
        );

        if (rtrim((string) ($document['issuer'] ?? ''), '/') !== $issuer) {
            throw new SsoLoginException(__('sso.error.issuer_mismatch'));
        }

        foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $required) {
            if (! filled($document[$required] ?? null)) {
                throw new SsoLoginException(__('sso.error.discovery_failed'));
            }
        }

        return $document;
    }

    /**
     * Baut die Authorization-URL und liefert die in der Session zu parkenden
     * Flow-Werte zurück (state, nonce, PKCE-Verifier).
     *
     * @return array{url: string, state: string, nonce: string, verifier: string}
     */
    public function authorizationRequest(SsoConnection $connection): array {
        $discovery = $this->discovery($connection);

        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = OAuth2AuthorizationCodeGrant::generatePkceVerifier();

        $url = (string) $discovery['authorization_endpoint'] . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => (string) $connection->client_id,
            'redirect_uri' => route('sso.oidc.callback'),
            'scope' => $connection->scopeList(),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => OAuth2AuthorizationCodeGrant::pkceChallenge($verifier),
            'code_challenge_method' => 'S256',
        ]);

        return ['url' => $url, 'state' => $state, 'nonce' => $nonce, 'verifier' => $verifier];
    }

    /**
     * Tauscht den Authorization-Code und validiert das ID-Token vollständig.
     * Rückgabe: geprüfte Claims (mindestens `sub`) + rohes id_token für den
     * RP-initiated Logout (id_token_hint).
     *
     * @return array{claims: array<string, mixed>, id_token: string}
     */
    public function exchangeAndVerify(SsoConnection $connection, string $code, string $verifier, string $expectedNonce): array {
        $discovery = $this->discovery($connection);

        $tokenEndpoint = (string) $discovery['token_endpoint'];
        $client = $this->http->coreClient('sso-oidc', $tokenEndpoint);
        // Einmal-Code: kein Transport-Retry, ein zweiter Versuch träfe beim
        // IdP auf einen bereits verbrauchten Authorization-Code.
        $client->setMaxRetries(1);

        $response = $client->requestResponse('post', $tokenEndpoint, [
            'form_params' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => route('sso.oidc.callback'),
                'client_id' => (string) $connection->client_id,
                'client_secret' => (string) $connection->client_secret,
                'code_verifier' => $verifier,
            ],
        ]);

        $idToken = $response->successful() ? $response->json('id_token') : null;
        if (! is_string($idToken) || $idToken === '') {
            Log::warning('SSO/OIDC: Token-Endpoint ohne id_token.', [
                'connection_id' => $connection->id,
                'status' => $response->status(),
            ]);

            throw new SsoLoginException(__('sso.error.token_exchange_failed'));
        }

        return [
            'claims' => $this->verifyIdToken($connection, $idToken, $expectedNonce, $discovery),
            'id_token' => $idToken,
        ];
    }

    /**
     * @param array<string, mixed> $discovery
     * @return array<string, mixed>
     */
    private function verifyIdToken(SsoConnection $connection, string $idToken, string $expectedNonce, array $discovery): array {
        $serializer = new CompactSerializer();

        try {
            $jws = $serializer->unserialize($idToken);
        } catch (\Throwable) {
            throw new SsoLoginException(__('sso.error.token_invalid'));
        }

        $header = $jws->getSignature(0)->getProtectedHeader();
        if (! in_array((string) ($header['alg'] ?? ''), ['RS256', 'PS256', 'ES256'], true)) {
            throw new SsoLoginException(__('sso.error.token_invalid'));
        }

        $verifier = new JWSVerifier(new AlgorithmManager([new RS256(), new PS256(), new ES256()]));

        // JWKS mit kurzem Cache; bei Fehlschlag (Key-Rotation, unbekannte kid)
        // genau EIN frischer Abruf und zweiter Versuch.
        $valid = $verifier->verifyWithKeySet($jws, $this->jwks($connection, $discovery, false), 0);
        if (! $valid) {
            $valid = $verifier->verifyWithKeySet($jws, $this->jwks($connection, $discovery, true), 0);
        }
        if (! $valid) {
            throw new SsoLoginException(__('sso.error.token_invalid'));
        }

        /** @var array<string, mixed>|null $claims */
        $claims = json_decode($jws->getPayload() ?? '', true);
        if (! is_array($claims)) {
            throw new SsoLoginException(__('sso.error.token_invalid'));
        }

        $this->assertClaims($connection, $claims, $expectedNonce);

        return $claims;
    }

    /** @param array<string, mixed> $claims */
    private function assertClaims(SsoConnection $connection, array $claims, string $expectedNonce): void {
        $issuer = rtrim((string) $connection->issuer, '/');
        $clientId = (string) $connection->client_id;
        $now = now()->getTimestamp();

        if (rtrim((string) ($claims['iss'] ?? ''), '/') !== $issuer) {
            throw new SsoLoginException(__('sso.error.token_invalid'));
        }

        $aud = $claims['aud'] ?? null;
        $audiences = is_array($aud) ? array_map(strval(...), $aud) : [(string) $aud];
        if (! in_array($clientId, $audiences, true)) {
            throw new SsoLoginException(__('sso.error.token_invalid'));
        }

        // azp: wenn vorhanden, muss es die eigene client_id sein; bei mehreren
        // Audiences ist azp Pflicht (OIDC Core §3.1.3.7 Nr. 4/5).
        $azp = $claims['azp'] ?? null;
        if (($azp !== null && (string) $azp !== $clientId) || (count($audiences) > 1 && $azp === null)) {
            throw new SsoLoginException(__('sso.error.token_invalid'));
        }

        if ((int) ($claims['exp'] ?? 0) < $now - self::CLOCK_LEEWAY_SECONDS) {
            throw new SsoLoginException(__('sso.error.token_expired'));
        }
        if ((int) ($claims['iat'] ?? 0) > $now + self::CLOCK_LEEWAY_SECONDS) {
            throw new SsoLoginException(__('sso.error.token_invalid'));
        }

        $nonce = (string) ($claims['nonce'] ?? '');
        if ($nonce === '' || ! hash_equals($expectedNonce, $nonce)) {
            throw new SsoLoginException(__('sso.error.token_invalid'));
        }

        if (! filled($claims['sub'] ?? null)) {
            throw new SsoLoginException(__('sso.error.token_invalid'));
        }
    }

    /** @param array<string, mixed> $discovery */
    private function jwks(SsoConnection $connection, array $discovery, bool $fresh): JWKSet {
        $cacheKey = "sso.oidc.jwks.{$connection->id}";
        if ($fresh) {
            Cache::forget($cacheKey);
        }

        /** @var array<string, mixed> $keys */
        $keys = Cache::remember($cacheKey, self::JWKS_TTL_SECONDS, function () use ($discovery): array {
            $jwksUri = (string) $discovery['jwks_uri'];
            $response = $this->http->coreClient('sso-oidc', $jwksUri)->getResponse($jwksUri);

            if (! $response->successful() || ! is_array($response->json('keys'))) {
                throw new SsoLoginException(__('sso.error.jwks_failed'));
            }

            return (array) $response->json();
        });

        try {
            return JWKSet::createFromKeyData($keys);
        } catch (\Throwable) {
            throw new SsoLoginException(__('sso.error.jwks_failed'));
        }
    }
}
