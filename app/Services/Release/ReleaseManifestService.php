<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReleaseManifestService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Release;

use App\Plugins\PluginManager;
use App\Services\Isms\SbomGenerator;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Licensing\{LicenseSeal, LicenseService};
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\{DB, Storage};

/**
 * Signierte/integritätsgesicherte Release-Metadaten (Feature 022, MVP).
 *
 * Erzeugt ein `release.json`, das eine konkrete Installation einem Release
 * zuordnet: App-/Build-Version, Laufzeitversionen (PHP/Laravel/DB), aktive
 * Module + Plugins (mit Version und Kompatibilitätsangaben) sowie SHA-256-
 * Prüfsummen relevanter Artefakte (SBOM, composer.lock, package-lock.json).
 *
 * Integrität: jede Artefakt-Prüfsumme ist im Manifest hinterlegt; das
 * Manifest selbst wird kanonisch JSON-serialisiert und mit Ed25519 signiert,
 * sofern ein Private Key vorhanden ist (derselbe Schlüssel-/Mechanismus wie
 * das Lizenzsystem — {@see LicenseService::signLicense()},
 * `sodium_crypto_sign_*`). Ohne Private Key bleibt das Manifest unsigniert,
 * die Prüfsummen-Integrität ist trotzdem verifizierbar
 * ({@see ReleaseVerifier}). KEINE zusätzlichen Krypto-Pakete.
 */
class ReleaseManifestService {
    /** Speicher-Pfad (relativ zur local-Disk) für das erzeugte Manifest. */
    public const STORAGE_PATH = 'release/release.json';

    public function __construct(
        private readonly PluginManager $plugins,
        private readonly SbomGenerator $sbom,
        private readonly LicenseService $licenses,
        private readonly FeatureFlagResolver $features,
    ) {}

    /**
     * Baut das vollständige Manifest (inkl. Signatur, falls möglich).
     *
     * @return array<string, mixed>
     */
    public function build(): array {
        $manifest = $this->payload();
        $canonical = self::canonicalJson($manifest);

        $signature = $this->sign($canonical);
        $manifest['signature'] = [
            'algorithm' => 'ed25519',
            'signed' => $signature !== null,
            'value' => $signature,
            'public_key' => $signature !== null ? $this->publicKeyB64() : null,
        ];

        return $manifest;
    }

    /**
     * Der zu signierende, signatur-freie Kern des Manifests. Dieselbe Struktur
     * wird bei der Verifikation rekonstruiert und gegen die Signatur geprüft.
     *
     * @return array<string, mixed>
     */
    public function payload(): array {
        $appVersion = (string) config('app.version', '0.1.0-dev');
        $dbConnection = (string) config('database.default', '');

        return [
            'schema' => 'workdiary.release-manifest/v1',
            'generated_at' => now()->toIso8601String(),
            'application' => [
                'name' => (string) config('app.name', 'WorkDiary'),
                'version' => $appVersion,
                'build' => $this->sbom->resolveGitHash(),
                'environment' => (string) app()->environment(),
            ],
            'runtime' => [
                'php' => PHP_VERSION,
                'laravel' => Application::VERSION,
                'database_driver' => (string) config("database.connections.{$dbConnection}.driver", $dbConnection),
                'database_version' => $this->databaseVersion(),
            ],
            'modules' => $this->modules(),
            'plugins' => $this->pluginEntries($appVersion),
            'artifacts' => $this->artifactChecksums(),
            'integrity' => $this->integritySummary(),
        ];
    }

    /**
     * Root-Hash + Zählwerte der Quelltext-Baseline (Feature 095): der Root
     * wandert damit in den signierten Payload — die Datei-Hashes selbst
     * bleiben in integrity.json (als Artefakt zusätzlich hash-gedeckt).
     *
     * @return array{root: string, source: string, files: int, packages: int}|null
     */
    private function integritySummary(): ?array {
        if (! Storage::disk('local')->exists(CodeIntegrityService::STORAGE_PATH)) {
            return null;
        }
        $manifest = json_decode((string) Storage::disk('local')->get(CodeIntegrityService::STORAGE_PATH), true);
        if (! is_array($manifest) || ! isset($manifest['root'])) {
            return null;
        }

        return [
            'root' => (string) $manifest['root'],
            'source' => (string) ($manifest['source'] ?? ''),
            'files' => count((array) ($manifest['files'] ?? [])),
            'packages' => count((array) ($manifest['packages'] ?? [])),
        ];
    }

    /**
     * Aktive Module (config/plans.php-Labels) mit Aktivierungsstatus laut
     * Lizenz/Plan.
     *
     * @return list<array{code: string, label: string, enabled: bool}>
     */
    private function modules(): array {
        /** @var array<string, string> $labels */
        $labels = (array) config('plans.labels', []);

        $modules = [];
        foreach ($labels as $code => $label) {
            $modules[] = [
                'code' => (string) $code,
                'label' => (string) $label,
                'enabled' => $this->features->isEnabled((string) $code),
            ];
        }

        return $modules;
    }

    /**
     * Registrierte Plugins mit Version + deklariertem Kompatibilitätsbereich.
     *
     * @return list<array{id: string, name: string, version: string, min_app_version: string|null, max_app_version: string|null}>
     */
    private function pluginEntries(string $appVersion): array {
        $entries = [];
        foreach ($this->plugins->all() as $plugin) {
            $version = (string) ($this->plugins->invoke($plugin, fn(): string => $plugin->version()) ?? '');
            $entries[] = [
                'id' => $plugin->id(),
                'name' => $plugin->name(),
                'version' => $version !== '' ? $version : 'bundled',
                'min_app_version' => $this->plugins->invoke($plugin, fn(): ?string => $plugin->minAppVersion()),
                'max_app_version' => $this->plugins->invoke($plugin, fn(): ?string => $plugin->maxAppVersion()),
            ];
        }
        unset($appVersion); // appVersion fließt über die Kompatibilitätsprüfung ein

        return $entries;
    }

    /**
     * SHA-256-Prüfsummen der release-relevanten Artefakte (sofern vorhanden):
     * SBOM-Alias, composer.lock, package-lock.json.
     *
     * @return list<array{name: string, path: string, sha256: string, bytes: int}>
     */
    private function artifactChecksums(): array {
        $artifacts = [];

        // Storage-Artefakte über den Disk-Pfad (Root ist app/private, nicht
        // app/ — und Storage::fake bleibt in Tests konsistent).
        $candidates = [
            'composer.lock' => base_path('composer.lock'),
            'package-lock.json' => base_path('package-lock.json'),
            'sbom' => Storage::disk('local')->path('sbom/' . SbomGenerator::latestAlias()),
            'integrity' => Storage::disk('local')->path(CodeIntegrityService::STORAGE_PATH),
        ];

        foreach ($candidates as $name => $path) {
            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }
            $contents = file_get_contents($path);
            if ($contents === false) {
                continue;
            }
            $sha256 = CryptoHelper::hash($contents);
            $artifacts[] = [
                'name' => $name,
                'path' => $this->relativeArtifactPath($name, $path),
                'sha256' => $sha256,
                'bytes' => strlen($contents),
            ];
        }

        return $artifacts;
    }

    private function relativeArtifactPath(string $name, string $path): string {
        return match ($name) {
            'sbom' => 'storage/app/private/sbom/' . SbomGenerator::latestAlias(),
            'integrity' => 'storage/app/private/' . CodeIntegrityService::STORAGE_PATH,
            default => basename($path),
        };
    }

    /**
     * Kanonisches JSON für die Signatur/Hash-Bildung: stabile Schlüssel-
     * reihenfolge, keine Escapes — identisch beim Erzeugen und Verifizieren.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function canonicalJson(array $payload): string {
        return JsonHelper::encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Signiert das kanonische Manifest mit dem Lizenz-Private-Key (Ed25519).
     * Null, wenn kein Private Key vorhanden ist (Kundeninstallation) — das
     * Manifest bleibt dann unsigniert, aber prüfsummen-integer.
     */
    private function sign(string $canonical): ?string {
        $private = $this->licenses->privateKey();
        if ($private === null || $private === '') {
            return null;
        }

        $signature = sodium_crypto_sign_detached($canonical, $private);

        return LicenseService::b64Encode($signature);
    }

    /** Ob diese Instanz Manifeste signieren kann (Herausgeber, Private Key vorhanden). */
    public function canSign(): bool {
        return $this->licenses->privateKey() !== null;
    }

    /**
     * Base64 des für die Verifikation genutzten Public Keys (versiegelt oder
     * aus Config) — oder null, wenn keiner konfiguriert ist.
     */
    public function publicKeyB64(): ?string {
        $b64 = LicenseSeal::isSealed()
            ? LicenseSeal::publicKey()
            : (string) config('license.public_key', '');

        return $b64 !== '' ? $b64 : null;
    }

    private function databaseVersion(): ?string {
        try {
            $version = DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);

            return is_string($version) ? $version : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
