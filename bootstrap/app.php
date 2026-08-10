<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : app.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Middleware\{AssignRequestId, AuthenticateScim, EnforceMaintenanceMode, EnforceSupportImpersonation, EnforceTenantStatus, EnsureNewSystemAccess, EnsureValidLicense, ForcePasswordChange, HandleDatabaseUnavailable, PrepareInstaller, RedirectIfNotInstalled, RequireTwoFactorSetup, RequiresFeature, SecurityHeaders, SetLocale, SetOrganizationContext};
use App\Legacy\Http\Middleware\{EnsureLegacyAccess, EnsureLegacyCallcenterAuthenticated, EnsureLegacyWriteAllowed};
use App\Support\DatabaseHealth;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\{Exceptions, Middleware};
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')->group(__DIR__ . '/../routes/install.php');
            Route::middleware('web')->group(__DIR__ . '/../routes/customer.php');
            Route::middleware('web')->group(__DIR__ . '/../routes/legacy.php');
            // Oeffentliches Hinweisgeber-Meldeportal: eigener schlanker Stack
            // (kein Auth/Org-Context/Locale/2FA), siehe Middleware-Gruppe unten.
            Route::middleware('whistleblowing')->group(__DIR__ . '/../routes/whistleblowing.php');
            // Oeffentlicher Karrierebereich (Feature 068, MVP-437): eigener
            // schlanker Public-Stack (kein Auth/Org-Context/Locale/2FA).
            Route::middleware('careers')->group(__DIR__ . '/../routes/careers.php');
            // SCIM-2.0-Provisioning (Feature 057): sessionlos, Bearer-Token-Auth
            // je Organisation über AuthenticateScim (kein web/api-Gruppen-Stack).
            Route::middleware(AuthenticateScim::class)->prefix('scim/v2')->group(__DIR__ . '/../routes/scim.php');
            // Oeffentlicher OCI-Punchout-Katalog (Feature 099, MVP-457):
            // sessionloser Public-Stack ohne Cookies/CSRF (Token-basiert).
            Route::middleware('b2b-catalog')->group(__DIR__ . '/../routes/b2b-catalog.php');
        },
    )
    // Legacy-Commands liegen ausserhalb des Auto-Discovery-Pfads
    // (app/Console/Commands) und muessen explizit registriert werden —
    // die Admin-Migrations-UI (legacy:import/-plan, legacy:archive) haengt daran.
    ->withCommands([
        __DIR__ . '/../app/Legacy/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Reverse-Proxy-Betrieb (Feature 096, MVP-443): ohne TrustedProxies
        // wäre Request::ip() die Proxy-IP — Rate-Limits, Security-Log/fail2ban,
        // Geo und Sessions liefen auf einer einzigen Adresse zusammen.
        // TRUSTED_PROXIES: Komma-Liste von IPs/CIDRs oder '*' (LB davor).
        $trustedProxies = (string) env('TRUSTED_PROXIES', '');
        if ($trustedProxies !== '') {
            $middleware->trustProxies(at: $trustedProxies === '*'
                ? '*'
                : array_values(array_filter(array_map('trim', explode(',', $trustedProxies)))));
        }

        // Correlation-ID für alle Requests (041-P0, MVP-053): vor allem
        // anderen, damit auch Fehler in frühen Middlewares eine ID tragen.
        $middleware->prepend(AssignRequestId::class);

        // Muss zuerst laufen, damit DB-Ausfälle in StartSession (SESSION_DRIVER=database)
        // und nachgelagerten Middlewares sauber als 503 zurückgegeben werden, ohne dass
        // beim Response-Unwind erneut DB-Schreibversuche stattfinden.
        // PrepareInstaller läuft noch davor: auf einem frischen Clone (ohne APP_KEY)
        // stellt es Schlüssel sowie datei-basierte Session/Cache bereit, bevor
        // EncryptCookies/StartSession greifen.
        $middleware->web(prepend: [
            PrepareInstaller::class,
            HandleDatabaseUnavailable::class,
        ]);
        $middleware->web(append: [
            RedirectIfNotInstalled::class,
            EnsureValidLicense::class,
            SecurityHeaders::class,
            // SetOrganizationContext vor SetLocale: damit Locales::current() die
            // Organisations-Sprache (currentOrganization) auflösen kann.
            SetOrganizationContext::class,
            SetLocale::class,
            ForcePasswordChange::class,
            RequireTwoFactorSetup::class,
            // Sperrt schreibende Aktionen bei gesperrtem/abgelaufenem Mandanten
            // (Feature 021). Läuft nach der Org-Auflösung; Auth-/Lizenz-/Logout-
            // Routen bleiben erreichbar (Aufhebung der Sperre).
            EnforceTenantStatus::class,
            // Wartungsmodus pro Mandant (Rang 65): Nicht-Admins → 503,
            // Admins arbeiten weiter (Banner im Layout).
            EnforceMaintenanceMode::class,
            // Support-Impersonation (Rang 64): Sperrliste + Scope-Grenzen,
            // Ablauf/Widerruf der Freigabe beendet die Sitzung sofort.
            EnforceSupportImpersonation::class,
            // Optionale IP-Allowlist für Plattform-Admins in admin.*-Routen
            // (Feature 096, MVP-446); leer = aus.
            \App\Http\Middleware\EnforcePlatformAdminIpAllowlist::class,
        ]);

        // Auch der API-Stack (Sanctum-Tokens) MUSS die Organisation an den
        // Container binden, sonst läuft OrganizationScope als No-Op und die
        // API leakt Mandantengrenzen (siehe tests/Feature/Tenant/ApiTenantTest).
        $middleware->api(append: [
            SecurityHeaders::class,
            SetOrganizationContext::class,
            // Wartungsmodus gilt auch für die Sanctum-API (JSON-503); die
            // tokenbasierten Ingest-Routen (Terminal/CTI/Standort) laufen ohne
            // Auth-User und bleiben hier unberührt — sie prüfen block_ingest
            // selbst nach der Token-Auflösung.
            EnforceMaintenanceMode::class,
        ]);

        // Schlanker Stack fuer das oeffentliche Hinweisgeber-Meldeportal:
        // Session/CSRF (fuer das Formular) + strikte Header, aber bewusst KEIN
        // Auth, Org-Context, Locale, 2FA, Tracking oder Reverb (Abschnitt 6.2).
        $middleware->group('whistleblowing', [
            HandleDatabaseUnavailable::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\Whistleblowing\WhistleblowingSecurityHeaders::class,
        ]);

        // Schlanker Stack fuer den oeffentlichen Karrierebereich (MVP-437):
        // wie das Meldeportal, aber mit dynamischer frame-ancestors-CSP fuer die
        // einbettbare Ansicht. Der Bewerbungs-POST ist von CSRF ausgenommen
        // (sessionlos/Embed) und schuetzt sich ueber signierten Formularzustand.
        $middleware->group('careers', [
            HandleDatabaseUnavailable::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\Careers\CareerPortalSecurityHeaders::class,
        ]);

        // Sessionloser Stack fuer den oeffentlichen OCI-Punchout-Katalog
        // (Feature 099, MVP-457): KEINE Cookies/Session/CSRF — der Einstieg ist
        // ein Cross-Site-POST des Einkaufssystems, den Browse-Zustand traegt
        // ein verschluesseltes, zeitbegrenztes Token durch den Flow.
        $middleware->group('b2b-catalog', [
            HandleDatabaseUnavailable::class,
            \App\Http\Middleware\B2bCatalog\B2bCatalogSecurityHeaders::class,
        ]);

        // SetOrganizationContext MUSS vor SubstituteBindings laufen, damit
        // der OrganizationScope beim Route-Model-Binding bereits greift —
        // sonst lädt Laravel {attachment} & Co. aus fremden Organisationen,
        // bevor unsere Tenant-Trennung überhaupt aktiv wird. StartSession
        // läuft per Priority-Liste vor SubstituteBindings, der Auth-Status
        // ist also bereits verfügbar, wenn wir die Org auflösen.
        $middleware->prependToPriorityList(SubstituteBindings::class, SetOrganizationContext::class);
        $middleware->alias([
            'legacy.callcenter.auth' => EnsureLegacyCallcenterAuthenticated::class,
            'legacy.write' => EnsureLegacyWriteAllowed::class,
            'access.legacy' => EnsureLegacyAccess::class,
            'access.new' => EnsureNewSystemAccess::class,
            // Folge zu MVP-047: Feature-Gate per Route.
            'requires-feature' => RequiresFeature::class,
            // 2FA-Einrichtungspflicht (guard-parametrierbar, z. B. two-factor.setup:customer).
            'two-factor.setup' => RequireTwoFactorSetup::class,
            // API-Token-Fähigkeiten (Feature 008 → Rang 60): Sanctum-Scopes je Route.
            // `ability:` = mindestens EINE der Abilities; `abilities:` = ALLE.
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            // Kundenportal-Bereichsfreigaben (MVP-511): portal.capability:diary.
            'portal.capability' => \App\Http\Middleware\EnsurePortalCapability::class,
        ]);

        // Token-Endpunkte ohne Session/CSRF: Backup-Heartbeat (MVP-046 §5).
        $middleware->validateCsrfTokens(except: [
            'admin/backup/heartbeat',
            // OCI-/IDS-Punchout-Hook: der externe Shop POSTet den Warenkorb
            // cross-site ohne CSRF-Token (Feature 050, MVP-096). Der Zugriff ist
            // weiterhin auth- und berechtigungsgeschützt (inventory.post).
            'oci-carts/import',
            // Aktiver Punchout-Rücksprung (MVP-096): sessionloser Cross-Site-POST,
            // Autorisierung über die signierte HOOK_URL ('signed'-Middleware).
            'oci-carts/return',
            // SAML-ACS (Feature 057, MVP-121): der IdP POSTet die Response
            // cross-site ohne CSRF-Token. Schutz kommt aus der SAML-Signatur,
            // dem InResponseTo-Abgleich (Session) und dem Replay-Cache.
            'sso/*/saml/acs',
            // Öffentlicher Karrierebereich (MVP-437): der Bewerbungs-POST ist
            // sessionlos/einbettbar; Schutz über signierten Formularzustand,
            // Honeypot, Idempotenz und Rate-Limit statt Session-CSRF.
            'karriere/*/stellen/*/bewerben',
        ]);

        // Pro-Guard-Redirect fuer nicht authentifizierte Anfragen. Ohne
        // diesen Hook schickt Laravel auch Customer-Portal-Aufrufe an die
        // interne Login-Seite, was die Trennung der Bereiche aufweicht.
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->routeIs('customer.*') || $request->is('customer-portal/*') || $request->is('customer-portal')) {
                return route('customer.login');
            }

            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Ungültige/fehlende API-Tokens (Feature 096, MVP-443): fail2ban-Signal
        // nur für die Token-Oberflächen — Web-Session-Redirects bleiben still.
        // return null ⇒ Standard-Rendering (401/redirect) bleibt unverändert.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                app(\App\Services\Security\SecurityEventLogger::class)->log(
                    \App\Enums\Security\SecurityEventType::ApiTokenInvalid,
                    ['surface' => 'api', 'has_token' => $request->bearerToken() !== null ? '1' : '0'],
                );
            }

            return null;
        });

        // Datenbank nicht erreichbar (Connection refused / timeout / Auth-Fehler):
        // Zeige eine schlanke, layout-freie Fehlerseite, statt einen
        // generischen Whoops/500-Stack auszuwerfen. Wichtig: die Antwort
        // darf NICHT auf Session/DB zugreifen (kein layouts.app).
        $exceptions->render(function (Throwable $e, Request $request) {
            // QueryException erbt von PDOException, daher genügt diese Prüfung.
            if (! ($e instanceof PDOException || $e->getPrevious() instanceof PDOException)) {
                return null;
            }

            // Nur echte Verbindungsprobleme als "DB unavailable" behandeln.
            // SQLSTATE-Codes wie 23000 (Integrity constraint), 22xxx
            // (Data exception) oder 42xxx (Syntax/Access) zeigen, dass die
            // DB erreichbar ist, aber die Query selbst fehlerhaft war.
            // Würden wir auch diese als "down" markieren, sperrte ein
            // einzelner FK-Verstoß die gesamte Anwendung für 60 s aus.
            // Rohes PDOException ohne errorInfo (typisch für Connect-
            // Fehler bevor eine Query lief) gilt weiterhin als "down".
            $pdo = $e instanceof PDOException ? $e : $e->getPrevious();
            $sqlState = '';
            if ($pdo instanceof PDOException && is_array($pdo->errorInfo ?? null) && isset($pdo->errorInfo[0])) {
                $sqlState = (string) $pdo->errorInfo[0];
            }
            $isConnectionIssue = $sqlState === ''
                || str_starts_with($sqlState, '08')    // Connection exception
                || str_starts_with($sqlState, 'HY000') // General/server gone away
                || in_array($sqlState, ['57P01', '57P02', '57P03'], true); // PG admin shutdown
            if (! $isConnectionIssue) {
                return null;
            }

            // Connection-Name aus der QueryException übernehmen, sonst Default.
            // Wir markieren die betroffene Verbindung kurzzeitig als unavailable,
            // damit Folge-Requests nicht erneut in den Connect-Timeout laufen.
            $failedConnection = $e instanceof QueryException && $e->connectionName !== ''
                ? $e->connectionName
                : DatabaseHealth::defaultConnection();
            DatabaseHealth::safeMarkUnavailable($failedConnection);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Database temporarily unavailable.',
                ], 503);
            }

            return response()->view('errors.database-unavailable', [
                'exceptionMessage' => $e->getMessage(),
            ], 503);
        });
    })->create();
