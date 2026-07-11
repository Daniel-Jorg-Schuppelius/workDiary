<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SsoController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Auth;

use App\Enums\Auth\SsoProtocol;
use App\Http\Controllers\Auth\Concerns\ResolvesWorkMode;
use App\Http\Controllers\Controller;
use App\Models\{Organization, SsoConnection, User};
use App\Services\Auth\Sso\{OidcClient, SamlClient, SsoLoginException, SsoLoginService};
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Öffentliche SSO-Endpunkte (Feature 057, MVP-120/121): SP-initiierter Start
 * je Organisation (Slug), OIDC-Callback, SAML-ACS und SP-Metadata. Alles
 * Sicherheitsrelevante (Policy, Modul-Gating, Mandantengrenze, Claims-/
 * Signaturprüfung) läuft serverseitig in den Services; SSO legt nie Konten
 * an und vergibt nie Rollen. Nach IdP-Login wird die lokale 2FA nicht erneut
 * abgefragt — MFA ist dann Sache des Identitätsanbieters.
 */
class SsoController extends Controller {
    use ResolvesWorkMode;

    public function __construct(
        private readonly OidcClient $oidc,
        private readonly SamlClient $saml,
        private readonly SsoLoginService $login,
    ) {}

    /** Einstieg von der Login-Seite: Organisations-Kennung abfragen. */
    public function discover(Request $request): View|RedirectResponse {
        $slug = trim((string) $request->query('org', ''));
        if ($slug !== '') {
            $organization = Organization::query()->where('slug', $slug)->first();
            if ($organization instanceof Organization && $this->activeConnection($organization) !== null) {
                return redirect()->route('sso.start', ['slug' => $organization->slug]);
            }

            return redirect()->route('sso.discover')->withErrors(['org' => __('sso.error.org_without_sso')])->withInput();
        }

        return view('auth.sso-discover');
    }

    /** SP-initiierter Login-Start (OIDC bevorzugt, sonst SAML). */
    public function start(Request $request, string $slug): RedirectResponse {
        $organization = $this->organization($slug);
        $connection = $this->activeConnection($organization);

        if (! $connection instanceof SsoConnection) {
            abort(404);
        }

        try {
            if ($connection->isOidc()) {
                $flow = $this->oidc->authorizationRequest($connection);
                $request->session()->put('sso.oidc', [
                    'connection_id' => $connection->id,
                    'state' => $flow['state'],
                    'nonce' => $flow['nonce'],
                    'verifier' => $flow['verifier'],
                ]);

                return redirect()->away($flow['url']);
            }

            $flow = $this->saml->loginRequest($connection);
            $request->session()->put('sso.saml', [
                'connection_id' => $connection->id,
                'request_id' => $flow['request_id'],
            ]);

            return redirect()->away($flow['url']);
        } catch (SsoLoginException $e) {
            return $this->failed($e->getMessage());
        }
    }

    /** OIDC-Redirect-Callback: state prüfen, Code tauschen, Token validieren. */
    public function oidcCallback(Request $request): RedirectResponse {
        /** @var array{connection_id?: int, state?: string, nonce?: string, verifier?: string} $flow */
        $flow = (array) $request->session()->pull('sso.oidc', []);
        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        if (
            $flow === [] || $code === '' || $state === ''
            || ! hash_equals((string) ($flow['state'] ?? ''), $state)
        ) {
            return $this->failed(__('sso.error.flow_expired'));
        }

        $connection = SsoConnection::query()
            ->withoutGlobalScopes()
            ->whereKey((int) ($flow['connection_id'] ?? 0))
            ->where('active', true)
            ->where('protocol', SsoProtocol::Oidc->value)
            ->first();

        if (! $connection instanceof SsoConnection) {
            return $this->failed(__('sso.error.flow_expired'));
        }

        try {
            $result = $this->oidc->exchangeAndVerify(
                $connection,
                $code,
                (string) ($flow['verifier'] ?? ''),
                (string) ($flow['nonce'] ?? ''),
            );
            $claims = $result['claims'];

            $email = is_string($claims['email'] ?? null) ? $claims['email'] : null;
            $user = $this->login->resolveUser($connection, [
                'subject' => (string) $claims['sub'],
                'email' => $email,
            ]);
        } catch (SsoLoginException $e) {
            return $this->failed($e->getMessage());
        }

        return $this->completeLogin($request, $connection, $user, [
            'id_token' => $result['id_token'],
            'discovery' => true,
        ]);
    }

    /** SAML Assertion Consumer Service (POST-Binding). */
    public function samlAcs(Request $request, string $slug): RedirectResponse {
        $organization = $this->organization($slug);
        $connection = $this->connection($organization, SsoProtocol::Saml, activeOnly: true);

        /** @var array{connection_id?: int, request_id?: string} $flow */
        $flow = (array) $request->session()->pull('sso.saml', []);
        $expectedRequestId = ((int) ($flow['connection_id'] ?? 0)) === $connection->id
            ? (string) ($flow['request_id'] ?? '')
            : '';

        $samlResponse = (string) $request->input('SAMLResponse', '');
        if ($samlResponse === '') {
            return $this->failed(__('sso.error.saml_invalid'));
        }

        try {
            $identity = $this->saml->validateResponse($connection, $samlResponse, $expectedRequestId ?: null);
            $user = $this->login->resolveUser($connection, $identity);
        } catch (SsoLoginException $e) {
            return $this->failed($e->getMessage());
        }

        return $this->completeLogin($request, $connection, $user);
    }

    /** SP-Metadata für die Registrierung beim IdP (öffentlich, auch vor Aktivierung). */
    public function metadata(string $slug): Response {
        $organization = $this->organization($slug);
        $connection = $this->connection($organization, SsoProtocol::Saml, activeOnly: false);

        try {
            $xml = $this->saml->metadata($connection);
        } catch (SsoLoginException) {
            abort(404);
        }

        return response($xml, 200, ['Content-Type' => 'application/samlmetadata+xml']);
    }

    /**
     * Gemeinsamer Abschluss: Session-Fixation verhindern, Nachweise/Audit
     * schreiben, Arbeitsmodus anwenden. OIDC: end_session-Daten für den
     * RP-initiated Logout in der neuen Session parken.
     *
     * @param array{id_token?: string, discovery?: bool} $logoutHint
     */
    private function completeLogin(Request $request, SsoConnection $connection, User $user, array $logoutHint = []): RedirectResponse {
        Auth::login($user);
        $request->session()->regenerate();
        $this->login->recordLogin($connection, $user);

        if ($connection->isOidc() && ($logoutHint['discovery'] ?? false)) {
            try {
                $endSession = $this->oidc->discovery($connection)['end_session_endpoint'] ?? null;
                if (is_string($endSession) && $endSession !== '') {
                    $request->session()->put('sso.logout', [
                        'end_session_endpoint' => $endSession,
                        'id_token' => $logoutHint['id_token'] ?? '',
                    ]);
                }
            } catch (SsoLoginException) {
                // Logout-Hint ist optional — Login nicht daran scheitern lassen.
            }
        }

        return $this->applyWorkModeAndRedirect($request, $user);
    }

    private function failed(string $message): RedirectResponse {
        return redirect()->route('login')->withErrors(['username' => $message]);
    }

    private function organization(string $slug): Organization {
        $organization = Organization::query()->where('slug', $slug)->first();
        abort_unless($organization instanceof Organization, 404);

        // Modul-Gating auch für Gast-Routen: Organisation als aktueller
        // Kontext binden, dann den regulären Lizenz-Resolver fragen.
        app()->instance('currentOrganization', $organization);
        abort_unless(app(FeatureFlagResolver::class)->isEnabled('module.sso'), 403, __('sso.error.module_disabled'));

        return $organization;
    }

    private function activeConnection(Organization $organization): ?SsoConnection {
        return SsoConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->orderByRaw("CASE protocol WHEN 'oidc' THEN 0 ELSE 1 END")
            ->first();
    }

    private function connection(Organization $organization, SsoProtocol $protocol, bool $activeOnly): SsoConnection {
        $query = SsoConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('protocol', $protocol->value);

        if ($activeOnly) {
            $query->where('active', true);
        }

        $connection = $query->first();
        abort_unless($connection instanceof SsoConnection, 404);

        return $connection;
    }
}
