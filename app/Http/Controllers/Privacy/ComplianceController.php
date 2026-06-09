<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComplianceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Models\Privacy\ComplianceFinding;
use App\Services\Privacy\ComplianceAnalysisService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Compliance-/Vertragsluecken: Ampeluebersicht, regelbasierte Analyse, Entscheidungen. */
class ComplianceController extends Controller {
    public function __construct(private readonly ComplianceAnalysisService $service) {}

    public function index(): View {
        Gate::authorize('viewAny', ComplianceFinding::class);

        $priority = ['missing' => 0, 'expiring' => 1, 'required' => 2, 'in_review' => 3, 'deviation_accepted' => 4, 'present' => 5, 'not_applicable' => 6];
        $findings = ComplianceFinding::query()
            ->with(['activity', 'agreement', 'processor'])
            ->get()
            ->sortBy(fn (ComplianceFinding $f): string => sprintf('%d_%s', $priority[$f->status] ?? 9, $f->requirement_key))
            ->values();

        return view('privacy.compliance.index', [
            'findings' => $findings,
            'counts' => $findings->groupBy('status')->map->count(),
        ]);
    }

    public function run(Request $request): RedirectResponse {
        Gate::authorize('manage', ComplianceFinding::class);
        $org = $request->user()?->organization;
        abort_unless($org !== null, 403);

        $count = $this->service->run($org);

        return back()->with('status', __(':count offene Lücke(n) erkannt.', ['count' => $count]));
    }

    public function update(Request $request, ComplianceFinding $finding): RedirectResponse {
        Gate::authorize('update', $finding);
        $data = $request->validate([
            'status' => ['required', 'in:present,in_review,not_applicable,deviation_accepted,missing'],
            'justification' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
        ]);

        // Begruendungspflicht fuer „nicht anwendbar"/„Abweichung akzeptiert".
        if (in_array($data['status'], ['not_applicable', 'deviation_accepted'], true) && empty($data['justification'])) {
            return back()->withErrors(['justification' => __('Für diesen Status ist eine Begründung erforderlich.')]);
        }

        $this->service->override(
            $finding,
            $data['status'],
            $data['justification'] ?? null,
            isset($data['due_at']) ? Carbon::parse($data['due_at']) : null,
        );

        return back()->with('status', __('Befund aktualisiert.'));
    }
}
