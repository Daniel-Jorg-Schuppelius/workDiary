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

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Plugins\PluginManager;
use App\Services\Isms\SbomGenerator;
use App\Services\Licensing\FeatureFlagResolver;
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

    public function index(PluginManager $plugins, FeatureFlagResolver $features, SbomGenerator $generator): View {
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
        ]);
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
