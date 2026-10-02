<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : McpOAuthController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Mcp;

use App\Enums\Api\ApiAbility;
use App\Http\Controllers\Controller;
use App\Models\Platform\User;
use App\Services\Mcp\Exceptions\McpOAuthException;
use App\Services\Mcp\OAuth\McpAuthorizationServer;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * OAuth-Endpunkte des MCP-Servers (MVP-1065): Metadaten (RFC 9728/8414),
 * Registrierung (RFC 7591), Zustimmung, Token und Widerruf (RFC 7009).
 */
class McpOAuthController extends Controller {
    /** Parameter der Autorisierungsanfrage, die durch die Zustimmungsseite gereicht werden. */
    private const AUTHORIZE_PARAMS = ['client_id', 'redirect_uri', 'response_type', 'code_challenge', 'code_challenge_method', 'scope', 'state', 'resource'];

    public function __construct(private readonly McpAuthorizationServer $server) {}

    public function protectedResource(): JsonResponse {
        return response()->json([
            'resource' => url('/mcp'),
            'authorization_servers' => [url('/')],
            'scopes_supported' => McpAuthorizationServer::SCOPES,
            'bearer_methods_supported' => ['header'],
            'resource_name' => config('app.name'),
        ]);
    }

    public function authorizationServer(): JsonResponse {
        return response()->json([
            'issuer' => url('/'),
            'authorization_endpoint' => route('mcp.oauth.authorize'),
            'token_endpoint' => route('mcp.oauth.token'),
            'registration_endpoint' => route('mcp.oauth.register'),
            'revocation_endpoint' => route('mcp.oauth.revoke'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'revocation_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => McpAuthorizationServer::SCOPES,
        ]);
    }

    public function register(Request $request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'redirect_uris' => ['required', 'array', 'min:1', 'max:5'],
            'redirect_uris.*' => ['required', 'string', 'max:2000'],
            'client_name' => ['nullable', 'string', 'max:100'],
            'token_endpoint_auth_method' => ['nullable', 'in:none'],
            'grant_types' => ['nullable', 'array'],
            'grant_types.*' => ['in:authorization_code,refresh_token'],
            'response_types' => ['nullable', 'array'],
            'response_types.*' => ['in:code'],
        ]);
        if ($validator->fails()) {
            return $this->oauthError(new McpOAuthException('invalid_client_metadata', $validator->errors()->first()));
        }
        $data = $validator->validated();
        try {
            $client = $this->server->register(trim((string) ($data['client_name'] ?? '')) ?: 'MCP-Client', array_values($data['redirect_uris']));
        } catch (McpOAuthException $e) {
            return $this->oauthError($e);
        }

        return response()->json([
            'client_id' => $client->client_key,
            'client_id_issued_at' => $client->created_at?->getTimestamp(),
            'client_name' => $client->name,
            'redirect_uris' => $client->redirect_uris,
            'token_endpoint_auth_method' => 'none',
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
        ], 201);
    }

    public function authorize(Request $request): View|RedirectResponse|Response {
        /** @var User $user */
        $user = $request->user();
        try {
            $authorization = $this->server->authorizationRequest($request->query());
        } catch (McpOAuthException $e) {
            return $this->authorizeError($e, $request->query());
        }
        $refusal = $this->refusal($user);
        if ($refusal !== null) {
            return $refusal;
        }

        return view('mcp.authorize', [
            'client' => $authorization['client'],
            'scopes' => $authorization['scopes'],
            'params' => $request->only(self::AUTHORIZE_PARAMS),
            'organization' => $user->organization,
            'redirectTarget' => self::redirectTarget($authorization['redirect_uri']),
            'trusted' => McpAuthorizationServer::isTrustedRedirect($authorization['redirect_uri']),
        ]);
    }

    public function approve(Request $request): RedirectResponse|Response {
        /** @var User $user */
        $user = $request->user();
        $params = $request->only(self::AUTHORIZE_PARAMS);
        try {
            $authorization = $this->server->authorizationRequest($params);
        } catch (McpOAuthException $e) {
            return $this->authorizeError($e, $params);
        }
        $refusal = $this->refusal($user);
        if ($refusal !== null) {
            return $refusal;
        }
        if ($request->input('decision') !== 'approve') {
            return redirect()->away(self::withQuery($authorization['redirect_uri'], ['error' => 'access_denied', 'state' => $authorization['state']]));
        }
        // Unbekanntes Ziel: nur mit ausdrücklicher Bestätigung (Schutz vor Zustimmungs-Phishing).
        if (! McpAuthorizationServer::isTrustedRedirect($authorization['redirect_uri']) && ! $request->boolean('confirm_untrusted')) {
            return redirect()->route('mcp.oauth.authorize', $params)->with('error', __('mcp.oauth.untrusted_required'));
        }
        // Lesen ist immer dabei; Schreiben nur, wenn angefragt UND bestätigt.
        $scopes = array_values(array_filter($authorization['scopes'], static fn (string $scope): bool => $scope === ApiAbility::McpRead->value || $request->boolean('grant_write')));
        if (! in_array(ApiAbility::McpRead->value, $scopes, true)) {
            array_unshift($scopes, ApiAbility::McpRead->value);
        }
        $code = $this->server->issueCode($authorization['client'], $user, $authorization['redirect_uri'], $authorization['code_challenge'], $scopes);

        return redirect()->away(self::withQuery($authorization['redirect_uri'], ['code' => $code, 'state' => $authorization['state']]));
    }

    public function token(Request $request): JsonResponse {
        try {
            $tokens = match ((string) $request->input('grant_type')) {
                'authorization_code' => $this->server->exchangeCode(
                    (string) $request->input('client_id'),
                    (string) $request->input('code'),
                    (string) $request->input('redirect_uri'),
                    (string) $request->input('code_verifier'),
                ),
                'refresh_token' => $this->server->refresh((string) $request->input('client_id'), (string) $request->input('refresh_token')),
                default => throw new McpOAuthException('unsupported_grant_type', (string) __('mcp.oauth.error.grant_type')),
            };
        } catch (McpOAuthException $e) {
            return $this->oauthError($e);
        }

        return response()->json($tokens)->withHeaders(['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }

    public function revoke(Request $request): Response {
        $this->server->revoke((string) $request->input('client_id'), (string) $request->input('token'));

        return response('', 200);
    }

    /** Ohne Organisation oder ohne Freigabe der Organisation keine Anbindung. */
    private function refusal(User $user): ?Response {
        $organization = $user->organization;
        if ($organization === null) {
            return response()->view('mcp.oauth-error', ['message' => __('mcp.oauth.error.no_organization')], 403);
        }
        if (! $this->server->enabledFor($organization)) {
            return response()->view('mcp.oauth-error', ['message' => __('mcp.oauth.error.disabled')], 403);
        }

        return null;
    }

    /** Was die Zustimmungsseite als Ziel nennt: Host bzw. bei Desktop-Clients das Schema. */
    private static function redirectTarget(string $uri): string {
        $host = parse_url($uri, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : (string) parse_url($uri, PHP_URL_SCHEME) . '://';
    }

    /** @param  array<string, mixed>  $params */
    private function authorizeError(McpOAuthException $e, array $params): RedirectResponse|Response {
        if (! $e->redirectable) {
            return response()->view('mcp.oauth-error', ['message' => $e->getMessage()], 400);
        }

        return redirect()->away(self::withQuery((string) $params['redirect_uri'], [
            'error' => $e->error,
            'error_description' => $e->getMessage(),
            'state' => isset($params['state']) && is_string($params['state']) ? $params['state'] : null,
        ]));
    }

    private function oauthError(McpOAuthException $e): JsonResponse {
        return response()->json(['error' => $e->error, 'error_description' => $e->getMessage()], $e->status)
            ->withHeaders(['Cache-Control' => 'no-store']);
    }

    /** @param  array<string, string|null>  $query */
    private static function withQuery(string $uri, array $query): string {
        return $uri . (str_contains($uri, '?') ? '&' : '?') . http_build_query(array_filter($query, static fn (?string $v): bool => $v !== null));
    }
}
