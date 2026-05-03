<?php

namespace App\Http\Controllers;

use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LegacyMigrationController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', \App\Models\AuditLog::class);

        $stats = $this->stats();

        return view('admin.legacy-migration', [
            'stats' => $stats,
            'writeEnabled' => (bool) config('app.legacy_write_enabled'),
        ]);
    }

    public function run(Request $request): RedirectResponse {
        Gate::authorize('viewAny', \App\Models\AuditLog::class);

        $type = (string) $request->input('type', 'all');
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
            return back()->with('error', __('Import fehlgeschlagen: ') . $e->getMessage());
        }

        return back()->with('success', __('Import abgeschlossen.'));
    }

    private function stats(): array {
        $legacyConfigured = (bool) config('database.connections.legacy.database');
        if (! $legacyConfigured) {
            return [
                'configured' => false,
                'users' => null,
                'diary' => null,
                'shifts' => null,
                'assignments' => null,
            ];
        }

        try {
            DB::connection('legacy')->getPdo();
        } catch (\Throwable) {
            return [
                'configured' => false,
                'users' => null,
                'diary' => null,
                'shifts' => null,
                'assignments' => null,
            ];
        }

        $legacyUsers = (int) DB::connection('legacy')->table('user')->count();
        $legacyDiary = (int) DB::connection('legacy')->table('tagebuch')->count();
        $legacyShifts = (int) DB::connection('legacy')->table('bereit')->count();
        $legacyAssign = (int) DB::connection('legacy')->table('notdnst')->count();

        return [
            'configured' => true,
            'users' => [
                'legacy' => $legacyUsers,
                'imported' => User::whereNotNull('legacy_user_id')->count(),
            ],
            'diary' => [
                'legacy' => $legacyDiary,
                'imported' => DiaryEntry::whereNotNull('legacy_id')->count(),
            ],
            'shifts' => [
                'legacy' => $legacyShifts,
                'imported' => OnCallShift::whereNotNull('legacy_id')->count(),
            ],
            'assignments' => [
                'legacy' => $legacyAssign,
                'imported' => EmergencyAssignment::whereNotNull('legacy_id')->count(),
            ],
        ];
    }
}
