<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupTargetOAuthController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support\Backup;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Enums\Backup\{BackupProvider, BackupTargetStatus};
use App\Http\Controllers\Controller;
use App\Models\Backup\BackupTargetConnection;
use App\Models\User;
use App\Plugins\Contracts\BackupTarget;
use App\Plugins\PluginManager;
use App\Services\Backup\BackupNaming;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Cache, Gate};
use Illuminate\Support\Str;
use Throwable;

/**
 * Gemeinsamer OAuth-Flow der SYSTEMWEITEN Backupziele (Feature 017
 * Phase 32, MVP-363). Unterschiede zum Intake-Flow (Feature 080):
 * Plattform-Admin statt Org-Admin (Policy hart auf `is_platform_admin`),
 * state NUR nutzergebunden (keine Organisation), nach dem Token-Tausch
 * werden Kontoidentität + Quota geladen, der Pseudonym-Stammordner
 * angelegt und die Scope-Abdeckung geprüft — eine Scope-Lücke setzt das
 * Ziel sichtbar auf `blocked` (keine Sonderwege).
 */
abstract class BackupTargetOAuthController extends Controller {
    private const STATE_TTL_SECONDS = 600;

    abstract protected function provider(): BackupProvider;

    abstract protected function grant(): OAuth2AuthorizationCodeGrant;

    /** @return list<string> */
    abstract protected function scopes(): array;

    /** Scope, ohne den das Ziel `blocked` ist. */
    abstract protected function requiredScope(): string;

    abstract protected function isConfigured(): bool;

    /**
     * Provider-spezifische Zusatzparameter der Authorize-URL.
     *
     * @return array<string, string>
     */
    protected function extraAuthorizeParams(): array {
        return [];
    }

    /** Startet den OAuth-Flow (optional `?connection=<sqid>` für Re-Auth). */
    public function startOAuth(Request $request): RedirectResponse {
        Gate::authorize('create', BackupTargetConnection::class);
        $admin = $this->admin();

        if (!$this->isConfigured()) {
            return $this->backToOverview()->with('error', __('backup_targets.flash.not_configured'));
        }

        $connectionId = null;
        $rawConnection = (string) $request->query('connection', '');
        if ($rawConnection !== '') {
            $connectionId = Sqid::decode(BackupTargetConnection::class, $rawConnection);
        }

        $state = Str::random(40);
        $verifier = OAuth2AuthorizationCodeGrant::generatePkceVerifier();
        Cache::put($this->stateKey($state), [
            'user_id' => (int) $admin->id,
            'connection_id' => $connectionId,
            'pkce_verifier' => $verifier,
        ], self::STATE_TTL_SECONDS);

        $url = $this->grant()->getAuthorizationUrl($state, $this->scopes(), $this->extraAuthorizeParams(), $verifier);

        return redirect()->away($url);
    }

    /** Callback: state einmalig einlösen, Code+PKCE tauschen, Ziel einrichten. */
    public function oauthCallback(Request $request): RedirectResponse {
        Gate::authorize('create', BackupTargetConnection::class);
        $admin = $this->admin();

        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        $payload = $state !== '' ? Cache::pull($this->stateKey($state)) : null;
        if (!is_array($payload) || (int) ($payload['user_id'] ?? 0) !== (int) $admin->id) {
            return $this->backToOverview()->with('error', __('backup_targets.flash.state_invalid'));
        }

        if ($code === '') {
            return $this->backToOverview()->with('error', __('backup_targets.flash.oauth_denied'));
        }

        try {
            $token = $this->grant()->exchangeAuthorizationCode($code, (string) ($payload['pkce_verifier'] ?? '') ?: null);
        } catch (Throwable $e) {
            // Nur die Fehlerklasse — nie Payload/Token.
            return $this->backToOverview()->with('error', __('backup_targets.flash.oauth_failed', ['class' => class_basename($e)]));
        }

        $connection = $this->resolveConnection($payload['connection_id'] ?? null, $admin);
        $scope = $token->getScope();
        $grantedScopes = $scope !== null && $scope !== ''
            ? array_values(array_filter(explode(' ', $scope)))
            : $this->scopes();
        $connection->forceFill([
            'access_token' => $token->getAccessToken(),
            'refresh_token' => $token->getRefreshToken() ?? $connection->refresh_token,
            'token_expires_at' => $token->getExpiresAt(),
            'granted_scopes' => $grantedScopes,
            'last_error' => null,
            'last_error_at' => null,
            'consecutive_failures' => 0,
            'disabled_at' => null,
        ])->save();

        // Scope-Lücke ⇒ sichtbar blocked, keine Sonderwege (Konzept §Adapter).
        if (!in_array($this->requiredScope(), $grantedScopes, true)) {
            $connection->forceFill(['status' => BackupTargetStatus::Blocked])->save();
            $connection->audit('backupTarget.scopeBlocked', ['by_user_id' => (int) $admin->id, 'provider' => $this->provider()->value]);

            return $this->backToOverview()->with('error', __('backup_targets.flash.scope_missing', ['scope' => $this->requiredScope()]));
        }

        $adapter = $this->adapter();
        if ($adapter === null) {
            return $this->backToOverview()->with('error', __('backup_targets.flash.not_configured'));
        }

        // Kontoidentität + Quota bestätigen, Pseudonym-Stammordner anlegen.
        try {
            $account = $adapter->backupAccount($connection);
            $quota = $adapter->backupQuota($connection);
            $rootRef = $adapter->backupEnsureFolder($connection, app(BackupNaming::class)->pseudonym());
            $connection->forceFill([
                'external_account_id' => $account->externalId,
                'external_account_label' => $account->label,
                'quota_total' => $quota['total'],
                'quota_used' => $quota['used'],
                'quota_checked_at' => now(),
                'root_folder_ref' => $rootRef,
                'status' => BackupTargetStatus::Active,
            ])->save();
        } catch (Throwable $e) {
            $connection->recordConnectionFailure(class_basename($e));

            return $this->backToOverview()->with('error', __('backup_targets.flash.account_failed', ['class' => class_basename($e)]));
        }

        $connection->audit('backupTarget.connected', ['by_user_id' => (int) $admin->id, 'provider' => $this->provider()->value]);

        return $this->backToOverview()->with('success', __('backup_targets.flash.connected'));
    }

    private function adapter(): ?BackupTarget {
        $plugin = app(PluginManager::class)->find($this->provider()->pluginId());

        return $plugin instanceof BackupTarget ? $plugin : null;
    }

    private function resolveConnection(mixed $connectionId, User $admin): BackupTargetConnection {
        if (is_int($connectionId) && $connectionId > 0) {
            $existing = BackupTargetConnection::query()
                ->where('provider', $this->provider()->value)
                ->find($connectionId);
            if ($existing !== null) {
                return $existing;
            }
        }

        return BackupTargetConnection::query()->create([
            'provider' => $this->provider(),
            'name' => $this->provider()->label(),
            'status' => BackupTargetStatus::Draft,
            'created_by_user_id' => $admin->id,
        ]);
    }

    private function backToOverview(): RedirectResponse {
        return redirect()->route('admin.backup-targets.index');
    }

    private function stateKey(string $state): string {
        return 'backup-target-' . $this->provider()->value . '-oauth-state:' . $state;
    }

    private function admin(): User {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
