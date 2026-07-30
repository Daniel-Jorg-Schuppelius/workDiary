<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConnectionOAuthController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Throwable;

/**
 * Gemeinsamer OAuth-Verbindungsflow der ORG-GEBUNDENEN Plugin-Admin-Panels
 * (Vollreview W3a; Muster: {@see Intake\IntakeOAuthController} für die
 * Cloud-Dokumenteingänge). Der OAuth-`state` ist kurzlebig, einmalig
 * einlösbar und an Organisation UND Sitzung gebunden; der PKCE-Verifier
 * wandert mit dem state durch den Cache. Tokens erscheinen nie in Logs,
 * Fehlermeldungen oder Audit-Payloads. Provider-Eigenheiten (Extra-Params,
 * Identitätsabgleich, Webhook-Abmeldung) liefern die Subklassen als Hooks.
 */
abstract class ConnectionOAuthController extends Controller {
    use ResolvesPluginOrgContext;

    /** OAuth-Grant-Builder des Plugins (Container-Singleton = Test-Austauschpunkt). */
    abstract protected function oauth(): PluginOAuthGrant;

    abstract protected function isConfigured(): bool;

    /**
     * Modellklasse der org-gebundenen Verbindung.
     *
     * @return class-string<Model>
     */
    abstract protected function connectionModel(): string;

    /** Cache-Prefix des OAuth-state (bleibt je Plugin stabil, ohne ':'). */
    abstract protected function stateCachePrefix(): string;

    /** Routen-Name der Admin-Übersicht (Redirect-Ziel des Flows). */
    abstract protected function overviewRouteName(): string;

    /** Plugin-Schlüssel für Audit-Events (`<key>.connected`) und Flash-Lang-Keys (`<key>.flash.*`). */
    abstract protected function pluginKey(): string;

    /** Status-Wert nach erfolgreichem Verbinden (Modell-Konstante). */
    abstract protected function connectedStatus(): string;

    /** Status-Wert nach dem Trennen (Modell-Konstante). */
    abstract protected function disconnectedStatus(): string;

    /** PKCE nutzen? (Todoist unterstützt es nicht.) */
    protected function usesPkce(): bool {
        return true;
    }

    /**
     * Provider-spezifische Zusatzparameter der Authorize-URL.
     *
     * @return array<string, string>
     */
    protected function extraAuthorizeParams(): array {
        return [];
    }

    /** Beim erneuten Verbinden das alte Refresh-Token behalten, wenn der Provider keins liefert. */
    protected function keepsRefreshTokenOnReconnect(): bool {
        return false;
    }

    /**
     * Fehlerzustands-Resets nach erfolgreichem Token-Tausch (Subklassen ohne
     * Health-Spalten überschreiben auf die vorhandenen Felder).
     *
     * @return array<string, mixed>
     */
    protected function connectionErrorResets(): array {
        return [
            'last_error' => null,
            'last_error_at' => null,
            'consecutive_failures' => 0,
            'disabled_at' => null,
        ];
    }

    /**
     * Flash-Text je Ereignis; Default: Lang-Keys `<pluginKey>.flash.<name>`.
     *
     * @param  array<string, string>  $replace
     */
    protected function flashMessage(string $name, array $replace = []): string {
        $message = __($this->pluginKey() . '.flash.' . $name, $replace);

        return is_string($message) ? $message : $name;
    }

    /** Hook nach dem Persistieren der Tokens (z. B. Kontoidentität laden). */
    protected function afterConnected(Model $connection, User $admin): void {}

    /** Hook vor dem Trennen (z. B. Webhook-Abmeldung — Token ist noch gültig). */
    protected function beforeDisconnect(Model $connection): void {}

    /** Startet den OAuth-Flow: org- und sitzungsgebundener Einmal-state (+ PKCE). */
    public function startOAuth(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        if (! $this->isConfigured()) {
            return back()->with('error', $this->flashMessage('not_configured'));
        }

        ['state' => $state, 'verifier' => $verifier] = $this->handshake()
            ->start((int) $organization->id, (int) $admin->id, withPkce: $this->usesPkce());

        $oauth = $this->oauth();
        $url = $oauth->grant()->getAuthorizationUrl($state, $oauth->scopes(), $this->extraAuthorizeParams(), $verifier);

        return redirect()->away($url);
    }

    /** OAuth-Callback: state prüfen (einmalig!), Code (+ PKCE) tauschen, Verbindung speichern. */
    public function oauthCallback(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $code = (string) $request->query('code', '');

        // Einmalig einlösen (Replay-Schutz); Bindung an Organisation UND
        // startenden Benutzer — nur der Admin, der den Flow begonnen hat,
        // kann ihn in seiner Sitzung abschließen.
        $payload = $this->handshake()->redeem((string) $request->query('state', ''), (int) $organization->id, (int) $admin->id);
        if ($payload === null) {
            return $this->backToOverview()->with('error', $this->flashMessage('state_invalid'));
        }

        if ($code === '') {
            return $this->backToOverview()->with('error', $this->flashMessage('oauth_denied'));
        }

        $oauth = $this->oauth();
        try {
            $token = $oauth->grant()->exchangeAuthorizationCode($code, OAuthStateHandshake::verifierFrom($payload));
        } catch (Throwable $e) {
            // Nur die Fehlerklasse — nie Payload/Token.
            return $this->backToOverview()->with('error', $this->flashMessage('oauth_failed', ['class' => class_basename($e)]));
        }

        $model = $this->connectionModel();
        $connection = $model::query()->firstOrNew(['organization_id' => $organization->id]);
        $connection->forceFill([
            'access_token' => $token->getAccessToken(),
            'refresh_token' => $token->getRefreshToken()
                ?? ($this->keepsRefreshTokenOnReconnect() ? $connection->getAttribute('refresh_token') : null),
            'token_expires_at' => $token->getExpiresAt(),
            'scopes' => $token->getScope() ?? implode(' ', $oauth->scopes()),
            'status' => $this->connectedStatus(),
            ...$this->connectionErrorResets(),
            'connected_by' => $admin->id,
            'connected_at' => now(),
            'disconnected_by' => null,
            'disconnected_at' => null,
        ])->save();

        $this->afterConnected($connection, $admin);

        // Alle Verbindungsmodelle nutzen den Auditable-Trait.
        // @phpstan-ignore method.notFound
        $connection->audit($this->pluginKey() . '.connected', ['by_user_id' => (int) $admin->id]);

        return $this->backToOverview()->with('success', $this->flashMessage('connected'));
    }

    /** Trennt die Verbindung (auditiert); Zuordnungen/Referenzen bleiben erhalten. */
    public function disconnect(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $model = $this->connectionModel();
        $connection = $model::query()->where('organization_id', $organization->id)->first();
        if ($connection !== null) {
            $this->beforeDisconnect($connection);
            $connection->forceFill([
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
                'status' => $this->disconnectedStatus(),
                'disconnected_by' => $admin->id,
                'disconnected_at' => now(),
            ])->save();
            // Alle Verbindungsmodelle nutzen den Auditable-Trait.
            // @phpstan-ignore method.notFound
            $connection->audit($this->pluginKey() . '.disconnected', ['by_user_id' => (int) $admin->id]);
        }

        return $this->backToOverview()->with('success', $this->flashMessage('disconnected'));
    }

    protected function backToOverview(): RedirectResponse {
        return redirect()->route($this->overviewRouteName());
    }

    protected function handshake(): OAuthStateHandshake {
        return new OAuthStateHandshake($this->stateCachePrefix());
    }
}
