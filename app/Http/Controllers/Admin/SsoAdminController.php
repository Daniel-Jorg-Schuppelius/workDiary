<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SsoAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\Auth\{SsoProtocol, SsoProviderType};
use App\Enums\User\UserRole;
use App\Http\Controllers\Controller;
use App\Models\{Organization, OrganizationSsoDomain, ScimGroup, ScimToken, SsoConnection, Team, User};
use App\Services\Auth\Sso\{EntraIssuer, OidcClient, SamlClient, SsoLoginException};
use App\Services\Scim\ScimGroupService;
use App\Services\SqidEncoder;
use App\Support\UrlSafety;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin-Verwaltung der SSO-/Verzeichnisdienst-Anbindung (Feature 057):
 * SCIM-Bearer-Token je Organisation (MVP-121), OIDC-/SAML-Verbindungen
 * (MVP-120/121) und Break-Glass-Konten. Secrets werden encrypted at-rest
 * gespeichert und nie wieder angezeigt. Der Endpunkt-Zugang ist
 * Enterprise-gegatet (config/plans.php: `module.sso`).
 */
class SsoAdminController extends Controller {
    public function index(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connections = SsoConnection::query()
            ->where('organization_id', $organization->id)
            ->get();

        return view('admin.sso.index', [
            'tokens' => ScimToken::query()
                ->where('organization_id', $organization->id)
                ->orderByDesc('id')
                ->get(),
            'groups' => ScimGroup::query()
                ->where('organization_id', $organization->id)
                ->orderBy('display_name')
                ->get(),
            'teams' => Team::query()
                ->where('organization_id', $organization->id)
                ->orderBy('name')
                ->get(),
            'scimBaseUrl' => url('/scim/v2'),
            'issuedToken' => session('scim_issued_token'),
            'oidcConnections' => $connections->where('protocol', SsoProtocol::Oidc)->keyBy(fn(SsoConnection $c) => $c->provider_type->value),
            'samlConnection' => $connections->firstWhere('protocol', SsoProtocol::Saml),
            'ssoDomains' => OrganizationSsoDomain::query()
                ->where('organization_id', $organization->id)
                ->orderBy('domain')
                ->get(),
            'ssoStartUrl' => route('sso.start', ['slug' => $organization->slug]),
            'oidcCallbackUrl' => route('sso.oidc.callback'),
            'samlAcsUrl' => route('sso.saml.acs', ['slug' => $organization->slug]),
            'samlMetadataUrl' => route('sso.saml.metadata', ['slug' => $organization->slug]),
            'breakGlassUsers' => User::query()
                ->where('organization_id', $organization->id)
                ->whereNull('customer_id')
                ->where('sso_exempt', true)
                ->orderBy('name')
                ->get(),
            'eligibleUsers' => User::query()
                ->where('organization_id', $organization->id)
                ->whereNull('customer_id')
                ->whereNull('deactivated_at')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Legt die OIDC- bzw. SAML-Verbindung der Organisation an oder
     * aktualisiert sie (eine je Protokoll). Ein leeres Secret-Feld behält das
     * gespeicherte Secret; gespeichert wird nie ein Leerstring (encrypted).
     */
    public function saveConnection(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'protocol' => ['required', Rule::in([SsoProtocol::Oidc->value, SsoProtocol::Saml->value])],
            'provider_type' => ['nullable', Rule::in(SsoProviderType::values())],
            'tenant' => ['nullable', 'string', 'max:191'],
            'label' => ['required', 'string', 'max:120'],
            'active' => ['sometimes', 'boolean'],
            'enforced' => ['sometimes', 'boolean'],
            'allow_email_link' => ['sometimes', 'boolean'],
            'jit_provisioning' => ['sometimes', 'boolean'],
            'jit_role' => ['nullable', Rule::in([UserRole::Admin->value, UserRole::User->value, UserRole::Buchhaltung->value])],
            'allow_private_network' => ['sometimes', 'boolean'],
            'issuer' => ['nullable', 'string', 'max:500', 'url'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:2000'],
            'scopes' => ['nullable', 'string', 'max:255'],
            'idp_entity_id' => ['nullable', 'string', 'max:500'],
            'idp_sso_url' => ['nullable', 'string', 'max:500', 'url'],
            'idp_certificate' => ['nullable', 'string', 'max:10000'],
            'idp_certificate_next' => ['nullable', 'string', 'max:10000'],
        ]);

        $protocol = SsoProtocol::from((string) $data['protocol']);
        // provider_type gilt nur für OIDC; SAML bleibt neutral (`custom`).
        $providerType = $protocol === SsoProtocol::Oidc
            ? (SsoProviderType::tryFrom((string) ($data['provider_type'] ?? '')) ?? SsoProviderType::Custom)
            : SsoProviderType::Custom;

        $connection = SsoConnection::query()
            ->where('organization_id', $organization->id)
            ->where('protocol', $protocol->value)
            ->where('provider_type', $providerType->value)
            ->first();

        // Preset-Issuer ableiten: Google fix, Microsoft aus dem Tenant (beim
        // Bearbeiten ohne neuen Tenant den gespeicherten Issuer behalten),
        // custom aus der Admin-Eingabe. Ins $data schreiben, damit die
        // gemeinsame Feldprüfung den effektiven Issuer sieht.
        $tenant = trim((string) ($data['tenant'] ?? ''));
        if ($protocol === SsoProtocol::Oidc && $providerType->isPreset()) {
            if ($providerType->requiresTenant()) {
                $data['issuer'] = $tenant !== ''
                    ? $providerType->presetIssuer($tenant)
                    : ($connection?->issuer);
            } else {
                $data['issuer'] = $providerType->presetIssuer();
            }
            if (($data['scopes'] ?? null) === null || trim((string) $data['scopes']) === '') {
                $data['scopes'] = $providerType->presetScopes();
            }
        }

        $allowPrivate = $request->boolean('allow_private_network');

        $errors = $this->validateProtocolFields($protocol, $providerType, $data, $tenant, $allowPrivate, $request->boolean('allow_email_link'));
        if ($errors !== []) {
            return back()->withErrors($errors)->withInput();
        }

        $attributes = [
            'label' => (string) $data['label'],
            'provider_type' => $providerType->value,
            'active' => $request->boolean('active'),
            'enforced' => $request->boolean('enforced'),
            'allow_email_link' => $request->boolean('allow_email_link'),
            'jit_provisioning' => $request->boolean('jit_provisioning'),
            'jit_role' => (($data['jit_role'] ?? null) ?: null),
            'allow_private_network' => $allowPrivate,
            'issuer' => $protocol === SsoProtocol::Oidc ? (($data['issuer'] ?? null) ?: null) : null,
            'client_id' => $protocol === SsoProtocol::Oidc ? (($data['client_id'] ?? null) ?: null) : null,
            'scopes' => $protocol === SsoProtocol::Oidc ? (($data['scopes'] ?? null) ?: null) : null,
            'idp_entity_id' => $protocol === SsoProtocol::Saml ? (($data['idp_entity_id'] ?? null) ?: null) : null,
            'idp_sso_url' => $protocol === SsoProtocol::Saml ? (($data['idp_sso_url'] ?? null) ?: null) : null,
            'idp_certificate' => $protocol === SsoProtocol::Saml ? (($data['idp_certificate'] ?? null) ?: null) : null,
            'idp_certificate_next' => $protocol === SsoProtocol::Saml ? (($data['idp_certificate_next'] ?? null) ?: null) : null,
        ];

        // Leeres Secret-Feld = gespeichertes Secret unangetastet lassen;
        // nie '' persistieren (encrypted-Cast, „payload invalid"-Falle).
        $secret = trim((string) ($data['client_secret'] ?? ''));
        if ($protocol === SsoProtocol::Oidc && $secret !== '') {
            $attributes['client_secret'] = $secret;
        }
        if ($protocol === SsoProtocol::Saml) {
            $attributes['client_secret'] = null;
        }

        if ($connection instanceof SsoConnection) {
            $connection->fill($attributes)->save();
        } else {
            $connection = SsoConnection::query()->create($attributes + [
                'organization_id' => $organization->id,
                'protocol' => $protocol->value,
                'created_by' => $admin->id,
            ]);
        }

        return back()->with('success', __('sso.flash.connection_saved', ['protocol' => $connection->protocol->label()]));
    }

    /** Konfigurationstest: OIDC-Discovery abrufen bzw. SAML-Settings/Zertifikat prüfen. */
    public function testConnection(string $connection): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $model = $this->connectionOf($organization, $connection);

        try {
            if ($model->isOidc()) {
                app(OidcClient::class)->discovery($model);
            } else {
                app(SamlClient::class)->assertConfigured($model);
            }
        } catch (SsoLoginException $e) {
            return back()->withErrors(['connection_test' => $e->getMessage()]);
        }

        $model->audit('sso.connection_tested', ['by_user_id' => (int) $admin->id]);

        return back()->with('success', __('sso.flash.connection_ok', ['protocol' => $model->protocol->label()]));
    }

    /** Entfernt eine Verbindung samt Kontoverknüpfungen (Cascade, auditiert). */
    public function destroyConnection(string $connection): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $model = $this->connectionOf($organization, $connection);

        $model->audit('sso.connection_removed', ['by_user_id' => (int) $admin->id, 'label' => $model->label]);
        $model->delete();

        return back()->with('success', __('sso.flash.connection_removed'));
    }

    /**
     * Verknüpft eine E-Mail-Domain mit der Organisation (Login-Discovery).
     * Domains sind global eindeutig — eine fremd belegte Domain wird
     * abgelehnt (kein Hijacking der Discovery anderer Mandanten).
     */
    public function addDomain(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'domain' => ['required', 'string', 'max:191'],
        ]);

        $domain = OrganizationSsoDomain::normalize((string) $data['domain']);
        if ($domain === '' || preg_match('/^(?=.{1,253}$)([a-z0-9](-?[a-z0-9])*\.)+[a-z]{2,}$/', $domain) !== 1) {
            return back()->withErrors(['domain' => __('sso.error.domain_invalid')])->withInput();
        }

        $existing = OrganizationSsoDomain::query()->where('domain', $domain)->first();
        if ($existing instanceof OrganizationSsoDomain) {
            if ((int) $existing->organization_id !== (int) $organization->id) {
                return back()->withErrors(['domain' => __('sso.error.domain_taken')])->withInput();
            }

            return back()->with('success', __('sso.flash.domain_added'));
        }

        OrganizationSsoDomain::query()->create([
            'organization_id' => $organization->id,
            'domain' => $domain,
            'created_by' => $admin->id,
        ]);

        return back()->with('success', __('sso.flash.domain_added'));
    }

    /** Entfernt eine E-Mail-Domain-Zuordnung der Organisation. */
    public function removeDomain(string $domain): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $decoded = app(SqidEncoder::class)->decode(OrganizationSsoDomain::class, $domain);
        $model = $decoded !== null
            ? OrganizationSsoDomain::query()->whereKey($decoded)->where('organization_id', $organization->id)->first()
            : null;
        abort_unless($model instanceof OrganizationSsoDomain, 404);

        $model->delete();

        return back()->with('success', __('sso.flash.domain_removed'));
    }

    /**
     * Break-Glass-Konto setzen/entziehen: darf sich trotz SSO-Pflicht weiter
     * lokal anmelden (nicht föderiertes Notfallkonto, DoD MVP-120). Bewusst
     * nicht fillable — nur über diese auditierte Admin-Aktion.
     */
    public function toggleBreakGlass(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $decoded = app(SqidEncoder::class)->decode(User::class, (string) $request->input('user', ''));
        $user = $decoded !== null
            ? User::query()
            ->whereKey($decoded)
            ->where('organization_id', $organization->id)
            ->whereNull('customer_id')
            ->first()
            : null;
        abort_unless($user instanceof User, 404);

        $user->forceFill(['sso_exempt' => ! $user->sso_exempt])->save();
        $user->audit('sso.break_glass_changed', [
            'by_user_id' => (int) $admin->id,
            'sso_exempt' => $user->sso_exempt,
        ]);

        return back()->with('success', __($user->sso_exempt ? 'sso.flash.break_glass_added' : 'sso.flash.break_glass_removed'));
    }

    /**
     * Protokollspezifische Pflichtfelder + SSRF-Leitplanke: ohne
     * `allow_private_network` müssen IdP-URLs öffentlich routbar sein
     * (UrlSafety, Muster JTL-Plugin). Preset-Anbieter (Microsoft/Google)
     * verlangen Tenant bzw. den festen Google-Issuer.
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function validateProtocolFields(SsoProtocol $protocol, SsoProviderType $providerType, array $data, string $tenant, bool $allowPrivate, bool $allowEmailLink = false): array {
        $errors = [];

        if ($protocol === SsoProtocol::Oidc) {
            // Microsoft: ohne Tenant kein gültiger (tenant-spezifischer) Issuer.
            if ($providerType->requiresTenant() && $tenant === '' && ! filled($data['issuer'] ?? null)) {
                $errors['tenant'] = __('sso.error.tenant_required');
            }

            foreach (['issuer', 'client_id'] as $field) {
                if (! filled($data[$field] ?? null)) {
                    $errors[$field] = __('validation.required', ['attribute' => $field]);
                }
            }
            // Bei fehlendem Tenant ist der Issuer-Fehler nur Folgefehler.
            if (isset($errors['tenant'])) {
                unset($errors['issuer']);
            }

            $issuer = (string) ($data['issuer'] ?? '');
            // Konfigurationszeit-Prüfung ohne DNS (nicht blockierend); die
            // DNS-Rebinding-sichere Laufzeitprüfung sitzt im OidcClient.
            if ($issuer !== '' && ! $allowPrivate && ! UrlSafety::isAcceptableExternalHttpUrl($issuer)) {
                $errors['issuer'] = __('sso.error.url_not_public');
            }
            // Google-Preset: nur der offizielle, feste Issuer ist zulässig.
            if ($providerType === SsoProviderType::Google && $issuer !== '' && $issuer !== SsoProviderType::GOOGLE_ISSUER) {
                $errors['issuer'] = __('sso.error.google_issuer_invalid');
            }
            // Entra-Härtung (MS365-Plan G1): tenant-spezifischer Issuer Pflicht
            // (common/organizations = Issuer-Template-Falle) und kein
            // E-Mail-Linking (nOAuth — email-Claim in Fremd-Tenants frei setzbar).
            if ($issuer !== '' && EntraIssuer::isEntra($issuer)) {
                if (! EntraIssuer::isTenantSpecific($issuer)) {
                    $errors['issuer'] = __('sso.error.entra_issuer_not_tenant_specific');
                }
                if ($allowEmailLink) {
                    $errors['allow_email_link'] = __('sso.error.entra_email_link_forbidden');
                }
            }
        } else {
            foreach (['idp_entity_id', 'idp_sso_url', 'idp_certificate'] as $field) {
                if (! filled($data[$field] ?? null)) {
                    $errors[$field] = __('validation.required', ['attribute' => $field]);
                }
            }
            $ssoUrl = (string) ($data['idp_sso_url'] ?? '');
            if ($ssoUrl !== '' && ! $allowPrivate && ! UrlSafety::isAcceptableExternalHttpUrl($ssoUrl)) {
                $errors['idp_sso_url'] = __('sso.error.url_not_public');
            }
        }

        return $errors;
    }

    private function connectionOf(Organization $organization, string $sqid): SsoConnection {
        $decoded = app(SqidEncoder::class)->decode(SsoConnection::class, $sqid);
        $model = $decoded !== null
            ? SsoConnection::query()->whereKey($decoded)->where('organization_id', $organization->id)->first()
            : null;
        abort_unless($model instanceof SsoConnection, 404);

        return $model;
    }

    /** Stellt ein neues SCIM-Token aus; der Klartext wird einmalig geflasht. */
    public function issueToken(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
        ]);

        [$token, $plain] = ScimToken::issue($organization->id, (string) $data['label'], (int) $admin->id);
        $token->audit('scim.token_issued', ['by_user_id' => (int) $admin->id, 'label' => $token->label]);

        return back()->with('scim_issued_token', $plain)->with('success', __('sso.flash.token_issued'));
    }

    /** Widerruft ein SCIM-Token (sofort ungültig). */
    public function revokeToken(string $token): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $decoded = app(SqidEncoder::class)->decode(ScimToken::class, $token);
        $model = $decoded !== null
            ? ScimToken::query()->whereKey($decoded)->where('organization_id', $organization->id)->first()
            : null;
        abort_unless($model instanceof ScimToken, 404);

        if ($model->revoked_at === null) {
            $model->forceFill(['revoked_at' => Carbon::now()])->save();
            $model->audit('scim.token_revoked', ['by_user_id' => (int) $admin->id, 'label' => $model->label]);
        }

        return back()->with('success', __('sso.flash.token_revoked'));
    }

    /**
     * Ordnet eine SCIM-Gruppe einem Team zu (oder löst die Zuordnung) — der
     * bewusste Admin-Schritt, der die Mitgliederprojektion nach `team_user`
     * erlaubt. SCIM selbst vergibt weiterhin nie ein Team/Rollen. Sqid statt
     * roher IDs, alle Referenzen strikt org-gescopt.
     */
    public function mapGroupTeam(Request $request, string $group): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $decodedGroup = app(SqidEncoder::class)->decode(ScimGroup::class, $group);
        $model = $decodedGroup !== null
            ? ScimGroup::query()->whereKey($decodedGroup)->where('organization_id', $organization->id)->first()
            : null;
        abort_unless($model instanceof ScimGroup, 404);

        $teamSqid = trim((string) $request->input('team', ''));
        $team = null;
        if ($teamSqid !== '') {
            $decodedTeam = app(SqidEncoder::class)->decode(Team::class, $teamSqid);
            $team = $decodedTeam !== null
                ? Team::query()->whereKey($decodedTeam)->where('organization_id', $organization->id)->first()
                : null;
            abort_unless($team instanceof Team, 404);
        }

        app(ScimGroupService::class)->mapToTeam($model, $team);
        $model->audit('scim.group_mapped', [
            'by_user_id' => (int) $admin->id,
            'group' => $model->display_name,
            'team_id' => $team?->id,
        ]);

        return back()->with('success', __('sso.flash.group_mapped'));
    }

    private function admin(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->isAdmin(), 403);
        abort_unless($user->organization_id !== null, 422, 'Kein Organisationskontext.');

        return $user;
    }

    private function organization(User $admin): Organization {
        $org = $admin->organization;
        abort_unless($org instanceof Organization, 422, 'Kein Organisationskontext.');

        return $org;
    }
}
