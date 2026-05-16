<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HomeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller {
    public function __invoke(): View|RedirectResponse {
        $currentMode = session('work_mode', 'legacy');
        $canViewSensitive = Auth::check();
        $legacyConfigured = filled(config('database.connections.legacy.database'));

        if ($canViewSensitive && $currentMode === 'legacy' && $legacyConfigured) {
            return redirect()->route('legacy.diary.week');
        }

        if ($canViewSensitive) {
            return redirect()->route('dashboard');
        }

        $legacyOnline = false;
        $stats = [
            'entries_total' => 0,
            'entries_open' => 0,
            'entries_alert' => 0,
            'team_size' => 0,
        ];
        $entries = collect();
        $team = collect();

        if ($legacyConfigured) {
            // Probe-Ergebnis kurz cachen (file-Store, damit kein DB-Roundtrip nötig ist),
            // sonst läuft jeder Request bei nicht erreichbarer Legacy-DB in den
            // Connect-Timeout (siehe DB_CONNECT_TIMEOUT).
            $legacyOnline = (bool) Cache::store('file')->remember(
                'legacy_db_online',
                30,
                static function (): bool {
                    try {
                        DB::connection('legacy')->getPdo();

                        return true;
                    } catch (\Throwable) {
                        return false;
                    }
                }
            );
        }

        return view('home', [
            'currentMode' => $currentMode,
            'canViewSensitive' => $canViewSensitive,
            'legacyConfigured' => $legacyConfigured,
            'legacyOnline' => $legacyOnline,
            'stats' => $stats,
            'entries' => $entries,
            'team' => $team,
        ]);
    }

    public function switchMode(Request $request, string $mode): RedirectResponse {
        if (! in_array($mode, ['legacy', 'new'], true)) {
            return back()->with('success', __('Unbekannter Modus.'));
        }

        $legacyConfigured = filled(config('database.connections.legacy.database'));
        if ($mode === 'legacy' && ! $legacyConfigured) {
            $request->session()->put('work_mode', 'new');

            return back()->with('success', __('Legacy-Modus ist nicht verfügbar (Legacy-DB nicht konfiguriert).'));
        }

        $request->session()->put('work_mode', $mode);

        $origin = (string) $request->input('origin', 'home');
        $targetRoute = $this->resolveModeRoute($origin, $mode);

        return redirect()
            ->route($targetRoute)
            ->with('mode_toast', $mode === 'legacy' ? __('Legacy-Modus aktiviert.') : __('Neuer Modus aktiviert.'));
    }

    private function resolveModeRoute(string $origin, string $mode): string {
        if ($origin === 'home') {
            return 'home';
        }

        return match ($origin) {
            'legacy.diary.index', 'diary.index' => $mode === 'legacy' ? 'legacy.diary.index' : 'diary.index',
            'legacy.diary.create', 'diary.create' => $mode === 'legacy' ? 'legacy.diary.create' : 'diary.create',
            default => $mode === 'legacy' ? 'legacy.diary.index' : 'diary.index',
        };
    }
}
