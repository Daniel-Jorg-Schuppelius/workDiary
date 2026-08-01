<?php
/*
 * Created on   : Mon Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserOrgScopingRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Architektur-Gate gegen Cross-Tenant-User-Leaks (Bauturbo Welle D,
 * „User-Modell org-scoping absichern"): Das {@see \App\Models\User}-Modell
 * trägt aus Sicherheitsgründen bewusst KEINEN globalen `OrganizationScope`
 * (Authenticatable-/Org-Wechsel-Sonderfall, siehe
 * ../WorkDiary-Architecture/security/tenant-audit-2026.md, Allow-List Z. 111).
 *
 * Folge: Jede org-Daten-Query, die eine LISTE/Collection von Usern rendert
 * (Mitglieder-/Assignee-/Empfänger-/Report-Picker, Dropdowns, plucks), muss die
 * Mandantengrenze SELBST ziehen — sonst listet sie Nutzer ALLER Organisationen
 * (genau die wiederkehrende Whitebox-Bug-Klasse, zuletzt A17: sechs Reporting-
 * Controller). Der kanonische Weg ist {@see \App\Models\User::scopeInCurrentOrganization()}
 * bzw. {@see \App\Models\User::scopeForOrganization()} oder ein expliziter
 * `->where('organization_id', …)`-Filter.
 *
 * Dieses Gate erkennt rohe `User::query()`/`User::where()`/`User::pluck()`/
 * `User::all()`-Aufrufe in den web-/dienstnahen Schichten ohne einen solchen
 * Org-Marker und verlangt Umstellung oder begründete Aufnahme in die Allow-List.
 *
 * Bewusst NICHT erfasst (komplementäre Absicherung, kein Doppel):
 *  - id-gebundene Auflösung einer bestimmten Zeile (`whereKey`/`find`/`findOrFail`/
 *    `whereIn('id', …)`/`Auth::id()`): Die Mandantengrenze wird dort über die
 *    Herkunft der ID gezogen — Request-FKs über die Validierungsregel
 *    {@see \App\Rules\ExistsInCurrentOrganization} (eigenes Gate
 *    {@see OrgScopedExistsRuleTest}), Modell-/Aggregat-IDs sind bereits gescopt.
 *  - `app/Console`, `app/Jobs`, `app/Auth`, `app/Models`, `app/Legacy`: laufen
 *    ohne Web-Org-Session bzw. sind Auth-/Infrastruktur (z. B. Login,
 *    Passwort-Reset, SCIM-/SSO-Provisionierung, Installer, Trait-Fallback).
 */
class UserOrgScopingRuleTest extends TestCase {
    /**
     * Verzeichnisse, in denen org-Daten-User-Queries erwartet werden (Web-
     * Controller, Anwendungsdienste, View-Support, Plugin-Controller/-Dienste).
     *
     * @var array<int, string>
     */
    private const SCANNED_DIRS = [
        'app/Http/Controllers',
        'app/Services',
        'app/Support',
        'app/Plugins',
    ];

    /**
     * Einstiegs-Muster für Query-Ausdrücke auf dem User-Modell. Nur der reale
     * `App\Models\User` (Wortgrenze davor), nicht LegacyUser/CustomerUser/SsoUser.
     *
     * @var array<int, string>
     */
    private const ENTRY_PATTERNS = [
        'User::query(',
        'User::where(',
        'User::whereRaw(',
        'User::pluck(',
        'User::all(',
    ];

    /**
     * Marker, die eine Query als org-gescopt ausweisen.
     *
     * @var array<int, string>
     */
    private const SAFE_MARKERS = [
        'organization_id',
        'scopeForOrganization',
        '->forOrganization(',
        'scopeInCurrentOrganization',
        '->inCurrentOrganization(',
    ];

    /**
     * Id-gebundene Auflösungen: Scope ergibt sich aus der Herkunft der ID
     * (Validierungs-Gate / bereits gescopte Aggregate), nicht Teil dieser
     * Bug-Klasse.
     *
     * @var array<int, string>
     */
    private const ID_BOUND_MARKERS = [
        'whereKey',
        '->find',
        '::find',
        "whereIn('id",
        'whereIn("id',
        'Auth::id()',
    ];

    /**
     * Bewusst nicht org-gescopte User-Queries in den gescannten Verzeichnissen
     * (Datei → Begründung). Jede Erweiterung braucht eine (A)-Begründung
     * (Auth/Uniqueness/Plattform/öffentlicher Token) im Audit-Doc.
     *
     * @var array<string, string>
     */
    private const ALLOW_LIST = [
        // E-Mail ist die globale Login-Identität — Eindeutigkeits-/Dedup-Prüfungen
        // sind BEWUSST org-übergreifend (zwei Orgs dürfen keine Kollision haben).
        'app/Services/Applications/RecruitingService.php' => 'E-Mail-Eindeutigkeit vor Konto-Anlage aus Bewerbung (global, Login-Identität).',
        'app/Services/Scim/ScimUserService.php' => 'SCIM-userName-/E-Mail-Uniqueness (global, RFC 7644 uniqueness).',
        'app/Services/Import/Specs/UserSpec.php' => 'Import-Dedup per E-Mail (global, Login-Identität); Anlage setzt organization_id.',
        'app/Services/Install/OrganizationProvisioner.php' => 'Installer: bestehendes Konto per E-Mail suchen (läuft vor/ohne Org-Kontext).',
        // Plattformweite Betreiber-Sichten (globaler Admin, keine Org-Bindung).
        'app/Http/Controllers/Admin/DemoTenantController.php' => 'Plattform-Admin listet Plattform-Admins (is_platform_admin), Cross-Tenant per Definition.',
        'app/Http/Controllers/Admin/LicenseAdminController.php' => 'Plattformweite Nutzerzahl als Lizenz-Fallback (globaler Betreiber-Kontext).',
        'app/Services/Security/SecurityOverviewService.php' => 'Basis-Query wird org-bedingt gefiltert; NULL-Org = bewusste plattformweite Betreiber-Sicht.',
        'app/Services/Release/CodeIntegrityService.php' => 'Integritäts-Alarm an ALLE Plattform-Admins (is_platform_admin), installationsweit per Definition (Feature 095).',
        'app/Services/Release/IntegrityLockdownService.php' => 'Lockdown adressiert Plattform-Admins (is_platform_admin), installationsweit per Definition (Feature 095).',
        'app/Services/Security/SecurityCrisisEscalator.php' => 'Sicherheits-Eskalation an Plattform-Admins (is_platform_admin), installationsweit per Definition (Feature 096/097).',
        // Org-bedingter Filter; Expense trägt immer organization_id (Fallback ohne Org unerreichbar).
        'app/Services/Expense/ApproverResolver.php' => 'Basis-Query wird bei vorhandener Expense-Org gefiltert (Expense ist tenant-scoped).',
        // Öffentliche, sessionlose Token-Route: Auflösung über den Feed-Token, danach Org-Bindung.
        'app/Http/Controllers/IcsFeedController.php' => 'Persönlicher ICS-Feed: Lookup über calendar_feed_token (Public-Route, bindet danach Org).',
        // Korrelierte Sortier-Subquery (orderBy): whereColumn bindet users.id an
        // die bereits org-gescopten time_entries des Projekts — kein Cross-Tenant-Leak,
        // löst nur den Namen des jeweils sichtbaren Zeiteintrags auf.
        'app/Http/Controllers/ProjectController.php' => 'Korrelierte orderBy-Subquery über whereColumn an org-gescopte time_entries gebunden (Sortierung nach Nutzername).',
    ];

    public function test_no_unscoped_user_list_queries_in_org_contexts(): void {
        $root = (string) realpath(__DIR__ . '/../../..');
        $violations = [];

        foreach (self::SCANNED_DIRS as $dir) {
            $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir);
            if (! is_dir($absolute)) {
                continue;
            }

            foreach ($this->phpFiles($absolute) as $file) {
                // Legacy (separate DB) und die reinen Authentifizierungs-Controller
                // (Login/Passwort-Reset/2FA/Registrierung) laufen vor der
                // Org-Session — E-Mail/Name sind dort die pre-Auth-Identität.
                if (str_contains($file, DIRECTORY_SEPARATOR . 'Legacy' . DIRECTORY_SEPARATOR)
                    || str_contains($file, DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'Auth' . DIRECTORY_SEPARATOR)) {
                    continue;
                }

                $relative = str_replace([$root . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], ['', '/'], $file);
                if (array_key_exists($relative, self::ALLOW_LIST)) {
                    continue;
                }

                $source = (string) file_get_contents($file);
                foreach ($this->unscopedUserQueries($source) as [$line, $snippet]) {
                    $violations[] = "$relative:$line — $snippet";
                }
            }
        }

        sort($violations);

        $this->assertSame([], $violations, sprintf(
            "Ungescopte User-Listen-Query in Org-Kontext gefunden (Cross-Tenant-Leak-Klasse).\n"
            . "Kanonisch scopen (User::inCurrentOrganization()/scopeForOrganization() bzw. ->where('organization_id', …))\n"
            . "oder mit (A)-Begründung in die Allow-List eintragen (siehe tenant-audit-2026.md):\n%s",
            implode("\n", $violations),
        ));
    }

    /**
     * Findet in einer Quelldatei alle User-Query-Einstiege, deren Statement
     * (bis zum nächsten `;`) weder einen Org-Marker noch eine id-gebundene
     * Auflösung enthält.
     *
     * @return list<array{0: int, 1: string}>
     */
    private function unscopedUserQueries(string $source): array {
        $hits = [];

        foreach (self::ENTRY_PATTERNS as $needle) {
            $offset = 0;
            while (($pos = strpos($source, $needle, $offset)) !== false) {
                $offset = $pos + 1;

                // Wortgrenze davor: kein alphanumerisches Zeichen (schließt
                // LegacyUser::/CustomerUser::/SsoUser:: etc. aus).
                $before = $pos > 0 ? $source[$pos - 1] : ' ';
                if (preg_match('/[A-Za-z0-9_]/', $before) === 1) {
                    continue;
                }

                $end = strpos($source, ';', $pos);
                $statement = $end !== false ? substr($source, $pos, $end - $pos) : substr($source, $pos, 240);

                if ($this->containsAny($statement, self::SAFE_MARKERS)
                    || $this->containsAny($statement, self::ID_BOUND_MARKERS)) {
                    continue;
                }

                $line = substr_count(substr($source, 0, $pos), "\n") + 1;
                $flat = trim((string) preg_replace('/\s+/', ' ', $statement));
                $hits[] = [$line, mb_substr($flat, 0, 160)];
            }
        }

        return $hits;
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> */
    private function phpFiles(string $directory): array {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }
}
