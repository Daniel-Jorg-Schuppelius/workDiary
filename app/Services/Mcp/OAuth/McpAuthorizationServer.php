<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : McpAuthorizationServer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mcp\OAuth;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Enums\Api\ApiAbility;
use App\Models\Mcp\{McpOAuthClient, McpOAuthCode, McpOAuthRefreshToken};
use App\Models\Platform\{Organization, User};
use App\Services\Mcp\Exceptions\McpOAuthException;
use App\Settings\SettingsRegistry;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Autorisierungsserver für den MCP-Endpunkt (MVP-1065): OAuth 2.1 mit
 * Authorization Code + PKCE (nur S256), dynamischer Client-Registrierung
 * (RFC 7591, nur öffentliche Clients) und Widerruf (RFC 7009).
 *
 * Ausgegeben werden Sanctum-Token mit den Scopes `mcp:read`/`mcp:write` —
 * dieselben, die die Tokenverwaltung kennt und widerruft. Laravel Passport
 * scheidet aus: sein Token-Trait kollidiert mit dem von Sanctum am Nutzermodell.
 *
 * Härtung: Opt-in je Organisation (`mcp.enabled`), Codes und Refresh-Token nur
 * als Hash, Refresh-Token als Familie — ein wieder eingelöstes rotiertes Token
 * oder ein wieder eingelöster Code widerruft die ganze Anmeldung.
 */
final class McpAuthorizationServer {
    /** @var list<string> */
    public const SCOPES = [ApiAbility::McpRead->value, ApiAbility::McpWrite->value];

    private const CODE_TTL_MINUTES = 10;

    private const ACCESS_TTL_MINUTES = 60;

    private const REFRESH_TTL_DAYS = 30;

    private const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '::1'];

    public function enabledFor(Organization $organization): bool {
        return filter_var(app(SettingsRegistry::class)->effective('mcp.enabled', $organization)->value, FILTER_VALIDATE_BOOL);
    }

    /** @param  list<string>  $redirectUris */
    public function register(string $name, array $redirectUris): McpOAuthClient {
        foreach ($redirectUris as $uri) {
            if (! self::isAllowedRedirectUri($uri)) {
                throw new McpOAuthException('invalid_redirect_uri', (string) __('mcp.oauth.error.redirect_uri'));
            }
        }

        return McpOAuthClient::query()->create([
            'client_key' => (string) Str::uuid(),
            'name' => $name,
            'redirect_uris' => array_values(array_unique($redirectUris)),
        ]);
    }

    /**
     * https (eingeschränkt auf `mcp.redirect_domains`), http nur auf Loopback,
     * eigene Schemata nur aus `mcp.custom_schemes`; nie mit Fragment oder
     * Zugangsdaten in der URL.
     */
    public static function isAllowedRedirectUri(string $uri): bool {
        $parts = parse_url($uri);
        if (! is_array($parts) || ! isset($parts['scheme']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        return match ($scheme) {
            'https' => $host !== '' && self::domainAllowed($scheme . '://' . $host),
            'http' => in_array($host, self::LOOPBACK_HOSTS, true),
            default => in_array($scheme, (array) config('mcp.custom_schemes', []), true),
        };
    }

    /**
     * Ziel ohne Warnung auf der Zustimmungsseite: bekannte Assistenten-Hosts,
     * die eigene Maschine und die erlaubten Desktop-Schemata.
     */
    public static function isTrustedRedirect(string $uri): bool {
        $scheme = strtolower((string) parse_url($uri, PHP_URL_SCHEME));
        $host = strtolower(trim((string) parse_url($uri, PHP_URL_HOST), '[]'));

        return match ($scheme) {
            'https' => in_array($host, (array) config('mcp.trusted_redirect_hosts', []), true),
            'http' => in_array($host, self::LOOPBACK_HOSTS, true),
            default => in_array($scheme, (array) config('mcp.custom_schemes', []), true),
        };
    }

    /**
     * Prüft eine Autorisierungsanfrage. Bis Client und Rücksprungadresse
     * feststehen, sind Fehler nicht zurückleitbar.
     *
     * @param  array<string, mixed>  $params
     * @return array{client: McpOAuthClient, redirect_uri: string, code_challenge: string, scopes: list<string>, state: string|null}
     */
    public function authorizationRequest(array $params): array {
        $client = McpOAuthClient::query()->where('client_key', self::text($params, 'client_id'))->first();
        if ($client === null) {
            throw new McpOAuthException('invalid_client', (string) __('mcp.oauth.error.client'));
        }
        $redirectUri = self::text($params, 'redirect_uri');
        if (! $client->allowsRedirect($redirectUri) || ! self::isAllowedRedirectUri($redirectUri)) {
            throw new McpOAuthException('invalid_request', (string) __('mcp.oauth.error.redirect_uri'));
        }
        if (self::text($params, 'response_type') !== 'code') {
            throw new McpOAuthException('unsupported_response_type', (string) __('mcp.oauth.error.response_type'), redirectable: true);
        }
        $challenge = self::text($params, 'code_challenge');
        if (self::text($params, 'code_challenge_method') !== 'S256' || ! self::isPkceString($challenge)) {
            throw new McpOAuthException('invalid_request', (string) __('mcp.oauth.error.pkce'), redirectable: true);
        }
        $resource = self::text($params, 'resource');
        if ($resource !== '' && rtrim($resource, '/') !== url('/mcp')) {
            throw new McpOAuthException('invalid_target', (string) __('mcp.oauth.error.resource'), redirectable: true);
        }

        return [
            'client' => $client,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $challenge,
            'scopes' => $this->requestedScopes(self::text($params, 'scope')),
            'state' => isset($params['state']) && is_string($params['state']) ? $params['state'] : null,
        ];
    }

    /** @param  list<string>  $scopes */
    public function issueCode(McpOAuthClient $client, User $user, string $redirectUri, string $challenge, array $scopes): string {
        $code = Str::random(64);
        McpOAuthCode::query()->create([
            'mcp_oauth_client_id' => $client->id,
            'user_id' => $user->id,
            'code_hash' => CryptoHelper::hash($code),
            'redirect_uri' => $redirectUri,
            'code_challenge' => $challenge,
            'scopes' => $scopes,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ]);
        $user->audit('mcp.authorized', ['client' => $client->name, 'scopes' => $scopes, 'redirect_host' => parse_url($redirectUri, PHP_URL_HOST)]);

        return $code;
    }

    /**
     * Code gegen Token tauschen. Ein zweites Einlösen widerruft die beim ersten
     * Mal entstandene Anmeldung — der Code ist dann offenbar abgeflossen.
     *
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string, scope: string}
     */
    public function exchangeCode(string $clientId, string $code, string $redirectUri, string $verifier): array {
        $client = $this->client($clientId);

        $tokens = DB::transaction(function () use ($client, $code, $redirectUri, $verifier): ?array {
            $row = McpOAuthCode::query()->where('code_hash', CryptoHelper::hash($code))->where('mcp_oauth_client_id', $client->id)->lockForUpdate()->first();
            if ($row === null) {
                throw new McpOAuthException('invalid_grant', (string) __('mcp.oauth.error.grant'));
            }
            if ($row->used_at !== null) {
                // Muss bestehen bleiben — deshalb kein Wurf innerhalb der Transaktion.
                $this->revokeFamily($row->family);

                return null;
            }
            $user = $row->user;
            if ($row->expires_at->isPast()
                || ! hash_equals($row->redirect_uri, $redirectUri)
                || ! self::isPkceString($verifier)
                || ! hash_equals($row->code_challenge, OAuth2AuthorizationCodeGrant::pkceChallenge($verifier))
                || ! $this->userMayConnect($user)) {
                throw new McpOAuthException('invalid_grant', (string) __('mcp.oauth.error.grant'));
            }

            $family = (string) Str::uuid();
            $row->update(['used_at' => now(), 'family' => $family]);

            return $this->issueTokens($client, $user, $row->scopes, $family);
        });

        return $tokens ?? throw new McpOAuthException('invalid_grant', (string) __('mcp.oauth.error.grant'));
    }

    /**
     * Refresh-Token einlösen und rotieren. Ein schon rotiertes Token ist ein
     * Diebstahlsignal und beendet die ganze Familie; ebenso ein Token, dessen
     * Zugriffstoken in der Tokenverwaltung widerrufen wurde.
     *
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string, scope: string}
     */
    public function refresh(string $clientId, string $refreshToken): array {
        $client = $this->client($clientId);

        $tokens = DB::transaction(function () use ($client, $refreshToken): ?array {
            $row = McpOAuthRefreshToken::query()->where('token_hash', CryptoHelper::hash($refreshToken))->where('mcp_oauth_client_id', $client->id)->lockForUpdate()->first();
            if ($row === null) {
                throw new McpOAuthException('invalid_grant', (string) __('mcp.oauth.error.grant'));
            }
            $user = $row->user;
            if ($row->rotated_at !== null || $row->personal_access_token_id === null || $row->expires_at->isPast() || ! $this->userMayConnect($user)) {
                $this->revokeFamily($row->family);

                return null;
            }

            $row->update(['rotated_at' => now()]);
            PersonalAccessToken::query()->whereKey($row->personal_access_token_id)->delete();

            return $this->issueTokens($client, $user, $row->scopes, $row->family);
        });

        return $tokens ?? throw new McpOAuthException('invalid_grant', (string) __('mcp.oauth.error.grant'));
    }

    /** Widerruf (RFC 7009): Zugriffs- oder Refresh-Token des Clients beenden die Anmeldung; Unbekanntes wird still ignoriert. */
    public function revoke(string $clientId, string $token): void {
        $client = McpOAuthClient::query()->where('client_key', $clientId)->first();
        if ($client === null || $token === '') {
            return;
        }
        $family = McpOAuthRefreshToken::query()->where('token_hash', CryptoHelper::hash($token))->where('mcp_oauth_client_id', $client->id)->value('family');
        if ($family === null) {
            $access = PersonalAccessToken::findToken($token);
            $family = $access === null ? null : McpOAuthRefreshToken::query()
                ->where('personal_access_token_id', $access->id)->where('mcp_oauth_client_id', $client->id)->value('family');
        }
        $this->revokeFamily(is_string($family) ? $family : null);
    }

    /** Alle Zugriffstoken und Refresh-Token einer Anmeldung. */
    private function revokeFamily(?string $family): void {
        if ($family === null) {
            return;
        }
        $rows = McpOAuthRefreshToken::query()->where('family', $family);
        PersonalAccessToken::query()->whereIn('id', (clone $rows)->whereNotNull('personal_access_token_id')->pluck('personal_access_token_id'))->delete();
        $rows->delete();
    }

    /** @phpstan-assert-if-true User $user */
    private function userMayConnect(?User $user): bool {
        $organization = $user?->organization;

        // Ein deaktiviertes Konto tauscht weder einen offenen Code noch erneuert es ein Token (pub-2).
        return $user instanceof User && $user->canLogin() && $organization instanceof Organization && $this->enabledFor($organization);
    }

    /**
     * @param  list<string>  $scopes
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string, scope: string}
     */
    private function issueTokens(McpOAuthClient $client, User $user, array $scopes, string $family): array {
        $access = $user->createToken(mb_substr('MCP · ' . $client->name, 0, 255), $scopes, now()->addMinutes(self::ACCESS_TTL_MINUTES));
        $refresh = Str::random(64);
        McpOAuthRefreshToken::query()->create([
            'mcp_oauth_client_id' => $client->id,
            'user_id' => $user->id,
            'personal_access_token_id' => $access->accessToken->id,
            'family' => $family,
            'token_hash' => CryptoHelper::hash($refresh),
            'scopes' => $scopes,
            'expires_at' => now()->addDays(self::REFRESH_TTL_DAYS),
        ]);
        $client->update(['last_used_at' => now()]);

        return [
            'access_token' => $access->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TTL_MINUTES * 60,
            'refresh_token' => $refresh,
            'scope' => implode(' ', $scopes),
        ];
    }

    private function client(string $clientId): McpOAuthClient {
        return McpOAuthClient::query()->where('client_key', $clientId)->first()
            ?? throw new McpOAuthException('invalid_client', (string) __('mcp.oauth.error.client'), 401);
    }

    /**
     * Ohne Angabe nur Lesen; Unbekanntes fällt heraus, bleibt nichts übrig, ist die Anfrage ungültig.
     *
     * @return list<string>
     */
    private function requestedScopes(string $scope): array {
        if (trim($scope) === '') {
            return [ApiAbility::McpRead->value];
        }
        $scopes = array_values(array_intersect(self::SCOPES, preg_split('/\s+/', trim($scope)) ?: []));
        if ($scopes === []) {
            throw new McpOAuthException('invalid_scope', (string) __('mcp.oauth.error.scope'), redirectable: true);
        }

        return $scopes;
    }

    private static function domainAllowed(string $origin): bool {
        $domains = (array) config('mcp.redirect_domains', ['*']);

        return in_array('*', $domains, true) || in_array($origin, array_map(static fn ($d): string => rtrim(strtolower((string) $d), '/'), $domains), true);
    }

    /** PKCE-Verifier bzw. -Challenge nach RFC 7636: 43–128 Zeichen aus dem ungeschützten Zeichensatz. */
    private static function isPkceString(string $value): bool {
        return preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $value) === 1;
    }

    /** @param  array<string, mixed>  $params */
    private static function text(array $params, string $key): string {
        return isset($params[$key]) && is_string($params[$key]) ? $params[$key] : '';
    }
}
