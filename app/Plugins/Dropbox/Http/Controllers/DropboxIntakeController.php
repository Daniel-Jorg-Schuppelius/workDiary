<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DropboxIntakeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Dropbox\Http\Controllers;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Enums\CloudIntake\{CloudIntakeConnectionStatus, CloudIntakeProvider};
use App\Http\Controllers\Controller;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\{Organization, User};
use App\Plugins\Dropbox\Api\{DropboxClient, DropboxOAuth};
use App\Plugins\Dropbox\DropboxConfig;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Cache, Gate};
use Illuminate\Support\Str;
use Throwable;

/**
 * OAuth-Anbindung einer Dropbox-Quelle (Feature 080, MVP-353). Der `state`
 * ist kurzlebig, einmalig einlösbar und an Organisation, Nutzer UND optional
 * eine bestehende Verbindung (Re-Auth) gebunden; der PKCE-Verifier wandert
 * mit dem state (Muster GoogleCalendar). Nach dem Token-Tausch wird die
 * Kontoidentität geladen und an der Verbindung BESTÄTIGBAR hinterlegt —
 * aktiv importiert wird erst nach Ordner-Wahl + gültiger Route (P8).
 */
class DropboxIntakeController extends Controller {
    private const STATE_TTL_SECONDS = 600;

    /** Startet den OAuth-Flow (optional `?connection=<sqid>` für Re-Auth). */
    public function startOAuth(Request $request, DropboxOAuth $oauth): RedirectResponse {
        Gate::authorize('create', CloudDocumentConnection::class);
        $admin = $this->admin();
        $organization = $this->organization($admin);

        if (! DropboxConfig::isConfigured()) {
            return back()->with('error', __('cloud_intake.flash.not_configured'));
        }

        $connectionId = null;
        $rawConnection = (string) $request->query('connection', '');
        if ($rawConnection !== '') {
            $connectionId = Sqid::decode(CloudDocumentConnection::class, $rawConnection);
        }

        $state = Str::random(40);
        $verifier = OAuth2AuthorizationCodeGrant::generatePkceVerifier();
        Cache::put($this->stateKey($state), [
            'organization_id' => (int) $organization->id,
            'user_id' => (int) $admin->id,
            'connection_id' => $connectionId,
            'pkce_verifier' => $verifier,
        ], self::STATE_TTL_SECONDS);

        // token_access_type=offline: kurzlebiges Access- + Refresh-Token.
        $url = $oauth->grant()->getAuthorizationUrl($state, $oauth->scopes(), [
            'token_access_type' => 'offline',
        ], $verifier);

        return redirect()->away($url);
    }

    /** Callback: state einmalig einlösen, Code+PKCE tauschen, Konto bestätigen. */
    public function oauthCallback(Request $request, DropboxOAuth $oauth): RedirectResponse {
        Gate::authorize('create', CloudDocumentConnection::class);
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        $payload = $state !== '' ? Cache::pull($this->stateKey($state)) : null;
        if (! is_array($payload)
            || (int) ($payload['organization_id'] ?? 0) !== (int) $organization->id
            || (int) ($payload['user_id'] ?? 0) !== (int) $admin->id) {
            return $this->backToOverview()->with('error', __('cloud_intake.flash.state_invalid'));
        }

        if ($code === '') {
            return $this->backToOverview()->with('error', __('cloud_intake.flash.oauth_denied'));
        }

        try {
            $token = $oauth->grant()->exchangeAuthorizationCode($code, (string) ($payload['pkce_verifier'] ?? '') ?: null);
        } catch (Throwable $e) {
            // Nur die Fehlerklasse — nie Payload/Token.
            return $this->backToOverview()->with('error', __('cloud_intake.flash.oauth_failed', ['class' => class_basename($e)]));
        }

        $connection = $this->resolveConnection($organization, $payload['connection_id'] ?? null, $admin);
        $scope = $token->getScope();
        $connection->forceFill([
            'access_token' => $token->getAccessToken(),
            'refresh_token' => $token->getRefreshToken() ?? $connection->refresh_token,
            'token_expires_at' => $token->getExpiresAt(),
            'granted_scopes' => $scope !== null && $scope !== ''
                ? array_values(array_filter(explode(' ', $scope)))
                : $oauth->scopes(),
            'last_error' => null,
            'last_error_at' => null,
            'consecutive_failures' => 0,
            'disabled_at' => null,
        ])->save();

        // Kontoidentität laden und zur Bestätigung hinterlegen.
        try {
            $account = (new DropboxClient($connection))->account();
            $connection->forceFill([
                'external_account_id' => $account->externalId,
                'external_account_label' => $account->label,
            ])->save();
        } catch (Throwable $e) {
            $connection->recordConnectionFailure(class_basename($e));

            return $this->backToOverview()->with('error', __('cloud_intake.flash.account_failed', ['class' => class_basename($e)]));
        }

        // Re-Auth einer eingerichteten Verbindung wird wieder lauffähig;
        // Neuanlagen bleiben Entwurf bis Ordner + Route stehen (P8).
        $connection->forceFill([
            'status' => $connection->isRunnable()
                ? CloudIntakeConnectionStatus::Active
                : ($connection->root_folder_id !== null && $connection->routes()->where('active', true)->exists()
                    ? CloudIntakeConnectionStatus::Active
                    : CloudIntakeConnectionStatus::Draft),
        ])->save();

        $connection->audit('cloudIntake.connected', ['by_user_id' => (int) $admin->id, 'provider' => CloudIntakeProvider::Dropbox->value]);

        return $this->backToOverview()->with('success', __('cloud_intake.flash.connected'));
    }

    private function resolveConnection(Organization $organization, mixed $connectionId, User $admin): CloudDocumentConnection {
        if (is_int($connectionId) && $connectionId > 0) {
            $existing = CloudDocumentConnection::query()
                ->where('organization_id', $organization->id)
                ->where('provider', CloudIntakeProvider::Dropbox->value)
                ->find($connectionId);
            if ($existing !== null) {
                return $existing;
            }
        }

        return CloudDocumentConnection::query()->create([
            'organization_id' => $organization->id,
            'provider' => CloudIntakeProvider::Dropbox,
            'name' => 'Dropbox',
            'status' => CloudIntakeConnectionStatus::Draft,
            'created_by_user_id' => $admin->id,
        ]);
    }

    private function backToOverview(): RedirectResponse {
        return redirect()->route('admin.cloud-intake.index');
    }

    private function stateKey(string $state): string {
        return 'cloud-intake-dropbox-oauth-state:' . $state;
    }

    private function admin(): User {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function organization(User $admin): Organization {
        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        abort_unless($organization instanceof Organization, 403);
        abort_unless((int) $organization->id === (int) $admin->organization_id, 403);

        return $organization;
    }
}
