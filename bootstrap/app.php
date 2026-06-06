<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : app.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Middleware\{EnsureNewSystemAccess, EnsureValidLicense, ForcePasswordChange, HandleDatabaseUnavailable, PrepareInstaller, RedirectIfNotInstalled, RequiresFeature, SecurityHeaders, SetLocale, SetOrganizationContext};
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
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
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
        ]);

        // Auch der API-Stack (Sanctum-Tokens) MUSS die Organisation an den
        // Container binden, sonst läuft OrganizationScope als No-Op und die
        // API leakt Mandantengrenzen (siehe tests/Feature/Tenant/ApiTenantTest).
        $middleware->api(append: [
            SecurityHeaders::class,
            SetOrganizationContext::class,
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
        ]);

        // Token-Endpunkte ohne Session/CSRF: Backup-Heartbeat (MVP-046 §5).
        $middleware->validateCsrfTokens(except: [
            'admin/backup/heartbeat',
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
