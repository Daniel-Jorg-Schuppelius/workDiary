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

use App\Http\Controllers\Controller;
use App\Legacy\Http\Requests\RunLegacyMigrationRequest;
use App\Models\AuditLog;
use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LegacyMigrationController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', AuditLog::class);

        return view('admin.legacy-migration', [
            'stats' => $this->stats(),
            'writeEnabled' => (bool) config('app.legacy_write_enabled'),
        ]);
    }

    public function run(RunLegacyMigrationRequest $request): RedirectResponse
    {
        $type = $request->validated()['type'];
        $options = match ($type) {
            'users' => ['--users' => true],
            'diary' => ['--diary' => true],
            'shifts' => ['--shifts' => true],
            'assignments' => ['--assignments' => true],
            default => [],
        };

        try {
            Artisan::call('legacy:import', $options);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('Import fehlgeschlagen. Bitte die Log-Dateien prüfen.'));
        }

        return back()->with('success', __('Import abgeschlossen.'));
    }

    /** @return array<string, mixed> */
    private function stats(): array
    {
        $notConfigured = ['configured' => false, 'users' => null, 'diary' => null, 'shifts' => null, 'assignments' => null];

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
        ];
    }

    private function isLegacyReachable(): bool
    {
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
