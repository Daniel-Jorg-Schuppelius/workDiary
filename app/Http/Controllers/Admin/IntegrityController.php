<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrityController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Security\{FreezeIntegrityBaselineJob, RunIntegrityCheckJob};
use App\Models\IntegrityCheck;
use App\Services\Release\CodeIntegrityService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * Integritäts-Ampel + Befundliste (Feature 095, MVP-442) — nur Plattform-
 * Admin: die Baseline ist installationsweit, kein Org-Kontext. Prüf-/
 * Freeze-Läufe laufen als Queue-Jobs (vendor-Hashing dauert Sekunden bis
 * Minuten, kein Web-Timeout).
 */
class IntegrityController extends Controller {
    public function index(Request $request, CodeIntegrityService $service): View {
        abort_unless($request->user()?->isGlobalAdmin() === true, 403);

        $baseline = $service->load();
        $latest = IntegrityCheck::query()->latest('ran_at')->latest('id')->first();
        $checks = IntegrityCheck::query()
            ->latest('ran_at')->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.integrity.index', [
            'baseline' => $baseline === null ? null : [
                'source' => (string) ($baseline['source'] ?? ''),
                'generated_at' => (string) ($baseline['generated_at'] ?? ''),
                'root' => (string) ($baseline['root'] ?? ''),
                'files' => count((array) ($baseline['files'] ?? [])),
                'packages' => count((array) ($baseline['packages'] ?? [])),
            ],
            'latest' => $latest,
            'checks' => $checks,
        ]);
    }

    /** „Jetzt prüfen": Prüflauf in die Queue (Ergebnis erscheint in der Liste). */
    public function verify(Request $request): RedirectResponse {
        abort_unless($request->user()?->isGlobalAdmin() === true, 403);

        RunIntegrityCheckJob::dispatch();

        return redirect()->route('admin.integrity.index')
            ->with('status', __('Integritätsprüfung gestartet — das Ergebnis erscheint in der Befundliste.'));
    }

    /** „Baseline einfrieren": lokale Baseline (source=local) mit Provenienz. */
    public function freeze(Request $request): RedirectResponse {
        abort_unless($request->user()?->isGlobalAdmin() === true, 403);

        FreezeIntegrityBaselineJob::dispatch((int) $request->user()->id);

        return redirect()->route('admin.integrity.index')
            ->with('status', __('Baseline-Erzeugung gestartet — der neue Stand erscheint in der Befundliste.'));
    }
}
