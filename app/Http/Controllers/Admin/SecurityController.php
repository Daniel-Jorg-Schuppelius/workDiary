<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\RequiresPlatformOperator;
use App\Http\Controllers\Controller;
use App\Models\SecurityAdvisory;
use App\Services\Security\{OsvAdvisoryService, SecurityOverviewService};
use App\Support\ErrorText;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/**
 * Admin-Seite „Sicherheit" (Feature 016, MVP) — read-only Überblick
 * sicherheitsrelevanter Zustände: aktive Sessions, API-Tokens, externe
 * Integrationen, letzte Exporte, letzte Supportzugriffe sowie
 * 2FA-/Verschlüsselungs-Kennzahlen. Ergänzt um die Sicherheitslage der
 * Abhängigkeiten (OSV-Advisories, Rang 70) inkl. manueller VEX-Bewertung.
 */
class SecurityController extends Controller {
    use RequiresPlatformOperator;

    public function index(SecurityOverviewService $overview): View {
        Gate::authorize(Permission::SecurityView->value);

        // Sicherheitslage (Rang 70): offene OSV-Advisories, schwerste zuerst.
        $advisories = SecurityAdvisory::query()->open()->get()
            ->sortBy(fn(SecurityAdvisory $a): int => (int) array_search($a->severity, SecurityAdvisory::SEVERITIES, true))
            ->values();

        return view('admin.security.index', [
            'security' => $overview->collect(),
            'advisories' => $advisories,
            'advisoriesLastPull' => SecurityAdvisory::query()->where('source', 'osv')->max('updated_at'),
            // Abruf und Bewertung gelten installationsweit — nur Betreiber (assertPlatformOperator).
            'isPlatformOperator' => $this->isPlatformOperator(),
        ]);
    }

    /** Manueller OSV-Abruf von der Sicherheitsseite (Rang 70). */
    public function pullAdvisories(OsvAdvisoryService $service): RedirectResponse {
        Gate::authorize(Permission::SecurityView->value);
        // Advisories und ihre Bewertung gelten installationsweit — ein Org-Admin
        // darf sie nicht für alle Mandanten setzen
        // (Sicherheitsaudit 2026-09-17, tenant-platform-ops-4).
        $this->assertPlatformOperator();

        try {
            $result = $service->pull();
        } catch (Throwable $e) {
            return redirect()->route('admin.security.index')
                ->with('error', __('Advisory-Abruf fehlgeschlagen: :message', ['message' => ErrorText::for($e)]));
        }

        return redirect()->route('admin.security.index')
            ->with('success', __(':checked Pakete geprüft — :open offene Advisories (:new neu, :resolved behoben).', [
                'checked' => $result['checked'],
                'open' => $result['open'],
                'new' => $result['new'],
                'resolved' => $result['resolved'],
            ]));
    }

    /** Manuelle VEX-Bewertung eines Advisories festhalten (Rang 70). */
    public function updateAdvisoryStatement(Request $request, SecurityAdvisory $advisory): RedirectResponse {
        Gate::authorize(Permission::SecurityView->value);
        $this->assertPlatformOperator();

        $data = $request->validate([
            'statement' => ['nullable', 'string', 'max:1000'],
        ]);

        $advisory->update(['statement' => ($data['statement'] ?? '') !== '' ? $data['statement'] : null]);

        return redirect()->route('admin.security.index')
            ->with('success', __('Bewertung wurde gespeichert.'));
    }
}
