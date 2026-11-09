<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyMigrationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresPlatformOperator;
use App\Http\Controllers\Controller;
use App\Legacy\Http\Requests\RunLegacyMigrationRequest;
use App\Legacy\Services\LegacySupportImportService;
use App\Models\Diary\{DiaryEntry, EmergencyAssignment, OnCallShift};
use App\Models\Platform\{Organization, User};
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Artisan, DB};

/**
 * Legacy-Migration: liest die installationsweite Legacy-Datenbank und legt
 * Konten an. Deshalb nur für den Plattform-Betreiber — ein Org-Admin konnte
 * sonst Legacy-Daten in den eigenen Mandanten kopieren (Sicherheitsaudit
 * 2026-09-17, tenant-legacy-1).
 */
class LegacyMigrationController extends Controller {
    use RequiresPlatformOperator;

    public function index(): View {
        $this->assertPlatformOperator();

        return view('admin.legacy-migration', [
            'stats' => $this->stats(),
            'writeEnabled' => (bool) config('app.legacy_write_enabled'),
            'organizations' => Organization::query()->withoutGlobalScopes()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function run(RunLegacyMigrationRequest $request): RedirectResponse {
        $this->assertPlatformOperator();
        $data = $request->validated();
        $options = match ($data['type']) {
            'users' => ['--users' => true],
            'diary' => ['--diary' => true],
            'shifts' => ['--shifts' => true],
            'assignments' => ['--assignments' => true],
            'support' => ['--support' => true, '--organization' => (string) $data['organization']],
            default => [],
        };

        try {
            $exitCode = Artisan::call('legacy:import', $options);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('Import fehlgeschlagen. Bitte die Log-Dateien prüfen.'));
        }

        if ($exitCode !== 0) {
            return back()->with('error', __('Import fehlgeschlagen. Bitte die Log-Dateien prüfen.'));
        }

        return back()->with('success', __('Import abgeschlossen.'));
    }

    /** @return array<string, mixed> */
    private function stats(): array {
        $notConfigured = ['configured' => false, 'users' => null, 'diary' => null, 'shifts' => null, 'assignments' => null, 'support' => null];

        if (! $this->isLegacyReachable()) {
            return $notConfigured;
        }

        return [
            'configured' => true,
            'users' => [
                'legacy' => (int) DB::connection('legacy')->table('user')->count(),
                'imported' => User::whereNotNull('legacy_user_id')->count(),
            ],
            'diary' => [
                'legacy' => (int) DB::connection('legacy')->table('tagebuch')->count(),
                'imported' => DiaryEntry::whereNotNull('legacy_id')->count(),
            ],
            'shifts' => [
                'legacy' => (int) DB::connection('legacy')->table('bereit')->count(),
                'imported' => OnCallShift::whereNotNull('legacy_id')->count(),
            ],
            'assignments' => [
                'legacy' => (int) DB::connection('legacy')->table('notdnst')->count(),
                'imported' => EmergencyAssignment::whereNotNull('legacy_id')->count(),
            ],
            'support' => $this->supportStats(),
        ];
    }

    /**
     * Aufträge und Archiv des Support-Portals; null, wenn die Altdatenbank die
     * Tabellen nicht hat (Installationen ohne Support-Portal).
     *
     * @return array{legacy: int, imported: int}|null
     */
    private function supportStats(): ?array {
        try {
            $legacy = DB::connection('legacy');

            return [
                'legacy' => (int) $legacy->table('kp_auftrag')->count() + (int) $legacy->table('kp_archiv')->count(),
                'imported' => LegacySupportImportService::importedOrderCount(),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function isLegacyReachable(): bool {
        if (! filled(config('database.connections.legacy.database'))) {
            return false;
        }

        try {
            DB::connection('legacy')->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
