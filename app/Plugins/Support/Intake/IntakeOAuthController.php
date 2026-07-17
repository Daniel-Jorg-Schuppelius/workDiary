<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeOAuthController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support\Intake;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Enums\CloudIntake\{CloudIntakeConnectionStatus, CloudIntakeProvider};
use App\Http\Controllers\Controller;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\{Organization, User};
use App\Plugins\Support\{OAuthStateHandshake, PluginOrgContext};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Throwable;

/**
 * Gemeinsamer OAuth-Flow der ORG-GEBUNDENEN Cloud-Dokumenteingänge
 * (Feature 080, Konsolidierung C7; Gegenstück: BackupTargetOAuthController
 * für die systemweiten Backupziele). Der `state` ist einmalig einlösbar und
 * org-/nutzer-/verbindungsgebunden; nach dem Token-Tausch wird die
 * Kontoidentität bestätigt. Provider-Extras (prompt=consent,
 * token_access_type) liefern die Subklassen als Hook.
 */
abstract class IntakeOAuthController extends Controller {
    abstract protected function provider(): CloudIntakeProvider;

    /** Anzeigename neu angelegter Verbindungen (z. B. "Dropbox"). */
    abstract protected function connectionName(): string;

    abstract protected function isConfigured(): bool;

    abstract protected function grant(): OAuth2AuthorizationCodeGrant;

    /** @return list<string> */
    abstract protected function scopes(): array;

    /** Bestätigt die Kontoidentität nach dem Token-Tausch. */
    abstract protected function account(CloudDocumentConnection $connection): IntakeAccount;

    /** Cache-Prefix des OAuth-state (bleibt je Plugin stabil, ohne ':'). */
    abstract protected function stateCachePrefix(): string;

    /**
     * Provider-spezifische Zusatzparameter der Authorize-URL.
     *
     * @return array<string, string>
     */
    protected function extraAuthorizeParams(): array {
        return [];
    }

    /** Status nach erfolgreichem Callback (Neuanlagen bleiben Entwurf bis Ordner + Route stehen, P8). */
    protected function connectedStatus(CloudDocumentConnection $connection): CloudIntakeConnectionStatus {
        return $connection->root_folder_id !== null && $connection->routes()->where('active', true)->exists()
            ? CloudIntakeConnectionStatus::Active
            : CloudIntakeConnectionStatus::Draft;
    }

    /** Startet den OAuth-Flow (optional `?connection=<sqid>` für Re-Auth). */
    public function startOAuth(Request $request): RedirectResponse {
        Gate::authorize('create', CloudDocumentConnection::class);
        $admin = $this->admin();
        $organization = $this->organization($admin);

        if (! $this->isConfigured()) {
            return back()->with('error', __('cloud_intake.flash.not_configured'));
        }

        $connectionId = null;
        $rawConnection = (string) $request->query('connection', '');
        if ($rawConnection !== '') {
            $connectionId = Sqid::decode(CloudDocumentConnection::class, $rawConnection);
        }

        ['state' => $state, 'verifier' => $verifier] = $this->handshake()->start(
            (int) $organization->id,
            (int) $admin->id,
            extra: ['connection_id' => $connectionId],
        );

        $url = $this->grant()->getAuthorizationUrl($state, $this->scopes(), $this->extraAuthorizeParams(), $verifier);

        return redirect()->away($url);
    }

    /** Callback: state einmalig einlösen, Code+PKCE tauschen, Konto bestätigen. */
    public function oauthCallback(Request $request): RedirectResponse {
        Gate::authorize('create', CloudDocumentConnection::class);
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $code = (string) $request->query('code', '');

        $payload = $this->handshake()->redeem((string) $request->query('state', ''), (int) $organization->id, (int) $admin->id);
        if ($payload === null) {
            return $this->backToOverview()->with('error', __('cloud_intake.flash.state_invalid'));
        }

        if ($code === '') {
            return $this->backToOverview()->with('error', __('cloud_intake.flash.oauth_denied'));
        }

        try {
            $token = $this->grant()->exchangeAuthorizationCode($code, OAuthStateHandshake::verifierFrom($payload));
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
                : $this->scopes(),
            'last_error' => null,
            'last_error_at' => null,
            'consecutive_failures' => 0,
            'disabled_at' => null,
        ])->save();

        try {
            $account = $this->account($connection);
            $connection->forceFill([
                'external_account_id' => $account->externalId,
                'external_account_label' => $account->label,
            ])->save();
        } catch (Throwable $e) {
            $connection->recordConnectionFailure(class_basename($e));

            return $this->backToOverview()->with('error', __('cloud_intake.flash.account_failed', ['class' => class_basename($e)]));
        }

        $connection->forceFill(['status' => $this->connectedStatus($connection)])->save();

        $connection->audit('cloudIntake.connected', ['by_user_id' => (int) $admin->id, 'provider' => $this->provider()->value]);

        return $this->backToOverview()->with('success', __('cloud_intake.flash.connected'));
    }

    private function handshake(): OAuthStateHandshake {
        return new OAuthStateHandshake($this->stateCachePrefix());
    }

    private function resolveConnection(Organization $organization, mixed $connectionId, User $admin): CloudDocumentConnection {
        if (is_int($connectionId) && $connectionId > 0) {
            $existing = CloudDocumentConnection::query()
                ->where('organization_id', $organization->id)
                ->where('provider', $this->provider()->value)
                ->find($connectionId);
            if ($existing !== null) {
                return $existing;
            }
        }

        return CloudDocumentConnection::query()->create([
            'organization_id' => $organization->id,
            'provider' => $this->provider(),
            'name' => $this->connectionName(),
            'status' => CloudIntakeConnectionStatus::Draft,
            'created_by_user_id' => $admin->id,
        ]);
    }

    private function backToOverview(): RedirectResponse {
        return redirect()->route('admin.cloud-intake.index');
    }

    private function admin(): User {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function organization(User $admin): Organization {
        $organization = PluginOrgContext::currentOrNull();
        abort_unless($organization instanceof Organization, 403);
        abort_unless((int) $organization->id === (int) $admin->organization_id, 403);

        return $organization;
    }
}
