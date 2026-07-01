<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComponentsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Console\Commands\SystemHealthCommand;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Plugins\PluginManager;
use App\Services\Isms\SbomGenerator;
use App\Services\Licensing\{FeatureFlagResolver, LicenseService};
use App\Services\Release\{ReleaseManifestService, ReleaseVerifier};
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Gate, Storage};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Geschützte Komponenten- und Versionsübersicht (Feature 044): App-Version
 * + Build, PHP/Laravel/DB, Module (Lizenz/Plan), Plugins mit Status und
 * die letzte Release-SBOM (Kennzahlen, Download, synchrone Erzeugung).
 * NUR Admin — Gate metrics.view, analog admin/metrics (Betriebsmetriken
 * sind bewusst nicht delegierbar, siehe PermissionsSeeder).
 *
 * Pfad-sicher: Download liefert ausschließlich den festen Alias
 * sbom/workdiary-latest.cdx.json — kein nutzerkontrollierter Pfad.
 */
class ComponentsController extends Controller {
    private const SBOM_DIR = 'sbom';

    public function index(
        PluginManager $plugins,
        FeatureFlagResolver $features,
        SbomGenerator $generator,
        SystemHealthCommand $health,
        LicenseService $licenses,
    ): View {
        Gate::authorize(Permission::MetricsView->value);

        $dbConnection = (string) config('database.default', '');

        /** @var array<string, string> $moduleLabels */
        $moduleLabels = (array) config('plans.labels', []);
        $modules = [];
        foreach ($moduleLabels as $code => $label) {
            $modules[] = [
                'code' => $code,
                'label' => $label,
                'enabled' => $features->isEnabled($code),
            ];
        }

        $pluginRows = [];
        foreach ($plugins->all() as $plugin) {
            $pluginRows[] = [
                'id' => $plugin->id(),
                'name' => $plugin->name(),
                'version' => (string) ($plugins->invoke($plugin, fn(): string => $plugin->version()) ?? '') ?: 'bundled',
                'enabled' => (bool) ($plugins->invoke($plugin, fn(): bool => $plugin->isEnabled()) ?? false),
            ];
        }

        return view('admin.components.index', [
            'appVersion' => (string) config('app.version', '0.1.0-dev'),
            'gitHash' => $generator->resolveGitHash(),
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => Application::VERSION,
            'dbDriver' => (string) config("database.connections.{$dbConnection}.driver", $dbConnection),
            'dbVersion' => $this->databaseVersion(),
            'modules' => $modules,
            'plugins' => $pluginRows,
            'sbom' => $this->latestSbomSummary(),
            'health' => $this->healthSummary($health, $licenses),
            'manifest' => $this->manifestSummary(),
        ]);
    }

    /**
     * Erzeugt das Release-Manifest synchron (Versionen, Prüfsummen, ggf.
     * Ed25519-Signatur) und legt es als release.json ab.
     */
    public function manifest(ReleaseManifestService $service): RedirectResponse {
        Gate::authorize(Permission::MetricsView->value);

        $document = $service->build();
        $json = JsonHelper::encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        Storage::disk('local')->put(ReleaseManifestService::STORAGE_PATH, $json);

        return redirect()
            ->route('admin.components.index')
            ->with('success', __('isms.components.manifest.flash_generated', [
                'signed' => ($document['signature']['signed'] ?? false) === true
                    ? __('isms.components.manifest.signed')
                    : __('isms.components.manifest.unsigned'),
            ]));
    }

    /** Release-Manifest herunterladen (fester Pfad, Gate-geprüft). */
    public function manifestDownload(): StreamedResponse {
        Gate::authorize(Permission::MetricsView->value);

        abort_unless(Storage::disk('local')->exists(ReleaseManifestService::STORAGE_PATH), 404);

        return Storage::disk('local')->download(ReleaseManifestService::STORAGE_PATH, 'release.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Strukturierte system:health-Zusammenfassung für die UI (Hinweis „nach
     * Update ausführen", Pending-Migrationen prominent).
     *
     * @return array{healthy: bool, failed: int, total: int, checks: list<array{name: string, ok: bool, details: string}>}
     */
    private function healthSummary(SystemHealthCommand $health, LicenseService $licenses): array {
        $checks = $health->runChecks($licenses);
        $rows = array_map(
            static fn(array $c): array => ['name' => $c[0], 'ok' => $c[1], 'details' => $c[2]],
            $checks,
        );
        $failed = count(array_filter($rows, static fn(array $c): bool => ! $c['ok']));

        return [
            'healthy' => $failed === 0,
            'failed' => $failed,
            'total' => count($rows),
            'checks' => $rows,
        ];
    }

    /**
     * Kennzahlen des letzten Release-Manifests — oder null, wenn keines erzeugt
     * wurde.
     *
     * @return array{generated_at: string|null, signed: bool, signature_valid: bool|null, artifacts: int, build: string|null}|null
     */
    private function manifestSummary(): ?array {
        if (! Storage::disk('local')->exists(ReleaseManifestService::STORAGE_PATH)) {
            return null;
        }

        $json = (string) Storage::disk('local')->get(ReleaseManifestService::STORAGE_PATH);
        $document = json_decode($json, true);
        if (! is_array($document)) {
            return null;
        }

        $result = app(ReleaseVerifier::class)->verify($document);
        $generatedAt = $document['generated_at'] ?? null;
        $build = $document['application']['build'] ?? null;

        return [
            'generated_at' => is_string($generatedAt) ? $generatedAt : null,
            'signed' => $result->signed,
            'signature_valid' => $result->signatureValid,
            'valid' => $result->valid,
            'artifacts' => $result->checkedArtifacts,
            'build' => is_string($build) ? $build : null,
        ];
    }

    /** SBOM synchron erzeugen (nur Lockfile-Parsing — schnell). */
    public function generate(SbomGenerator $generator): RedirectResponse {
        Gate::authorize(Permission::MetricsView->value);

        $json = $generator->toJson();
        $name = $generator->fileName();

        Storage::disk('local')->put(self::SBOM_DIR . '/' . $name, $json);
        Storage::disk('local')->put(self::SBOM_DIR . '/' . SbomGenerator::latestAlias(), $json);

        return redirect()
            ->route('admin.components.index')
            ->with('success', __('isms.components.flash_generated', [
                'file' => $name,
                'hash' => hash('sha256', $json),
            ]));
    }

    /** Letzte SBOM herunterladen (fester Alias-Pfad, Gate-geprüft). */
    public function download(): StreamedResponse {
        Gate::authorize(Permission::MetricsView->value);

        $path = self::SBOM_DIR . '/' . SbomGenerator::latestAlias();
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, SbomGenerator::latestAlias(), [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Kennzahlen der letzten SBOM (Alias-Datei) — oder null, wenn noch
     * keine erzeugt wurde.
     *
     * @return array{generated_at: string|null, sha256: string, composer: int, npm: int, total: int}|null
     */
    private function latestSbomSummary(): ?array {
        $path = self::SBOM_DIR . '/' . SbomGenerator::latestAlias();
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        $json = (string) Storage::disk('local')->get($path);
        $document = json_decode($json, true);
        if (! is_array($document)) {
            return null;
        }

        $composer = 0;
        $npm = 0;
        $components = is_array($document['components'] ?? null) ? $document['components'] : [];
        foreach ($components as $component) {
            $purl = is_array($component) ? ($component['purl'] ?? '') : '';
            if (is_string($purl) && str_starts_with($purl, 'pkg:composer/')) {
                $composer++;
            } elseif (is_string($purl) && str_starts_with($purl, 'pkg:npm/')) {
                $npm++;
            }
        }

        $timestamp = $document['metadata']['timestamp'] ?? null;

        return [
            'generated_at' => is_string($timestamp) ? $timestamp : null,
            'sha256' => hash('sha256', $json),
            'composer' => $composer,
            'npm' => $npm,
            'total' => count($components),
        ];
    }

    /** Server-Version der konfigurierten DB (best effort, nie fatal). */
    private function databaseVersion(): ?string {
        try {
            $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
            $version = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);

            return is_string($version) ? $version : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
