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

use App\Enums\Privacy\ComplianceFindingStatus;
use App\Http\Controllers\Controller;
use App\Models\Privacy\ComplianceFinding;
use App\Services\Privacy\ComplianceAnalysisService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Compliance-/Vertragsluecken: Ampeluebersicht, regelbasierte Analyse, Entscheidungen. */
class ComplianceController extends Controller {
    public function __construct(private readonly ComplianceAnalysisService $service) {}

    public function index(): View {
        Gate::authorize('viewAny', ComplianceFinding::class);

        // Offene Lücken zuerst; die Rangfolge der Status steht in der Abfrage, damit sie über alle Seiten gilt.
        $findings = ComplianceFinding::query()
            ->with(['activity', 'agreement', 'processor'])
            ->orderByRaw("CASE status WHEN 'missing' THEN 0 WHEN 'expiring' THEN 1 WHEN 'required' THEN 2 WHEN 'in_review' THEN 3 WHEN 'deviation_accepted' THEN 4 WHEN 'present' THEN 5 WHEN 'not_applicable' THEN 6 ELSE 9 END")
            ->orderBy('requirement_key')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        // Konfigurierbarer Anforderungskatalog (Nachtrag 043c) — beim ersten
        // Aufruf werden die config-Defaults materialisiert.
        $org = request()->user()?->organization;
        $requirements = $org !== null ? $this->service->catalog($org) : collect();

        return view('privacy.compliance.index', [
            'findings' => $findings,
            // Ampel über alle Befunde, nicht nur über die Seite.
            'counts' => ComplianceFinding::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'requirements' => $requirements,
        ]);
    }

    /** Katalogeintrag umbenennen bzw. (de)aktivieren (Nachtrag 043c). */
    public function updateRequirement(Request $request, \App\Models\Privacy\PrivacyRequirement $requirement): RedirectResponse {
        Gate::authorize('manage', ComplianceFinding::class);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'active' => ['nullable', 'in:0,1'],
        ]);

        $requirement->update([
            'label' => $data['label'],
            'active' => ($data['active'] ?? '0') === '1',
            'source' => 'manual',
        ]);

        return back()->with('status', __('Anforderungskatalog aktualisiert.'));
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
            'status' => ['required', Rule::enum(ComplianceFindingStatus::class)->only(ComplianceFindingStatus::manual())],
            'justification' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
        ]);

        // Begründungspflicht fuer „nicht anwendbar"/„Abweichung akzeptiert".
        $status = ComplianceFindingStatus::from($data['status']);
        if ($status->needsJustification() && empty($data['justification'])) {
            return back()->withErrors(['justification' => __('Für diesen Status ist eine Begründung erforderlich.')]);
        }

        $this->service->override(
            $finding,
            $status,
            $data['justification'] ?? null,
            isset($data['due_at']) ? Carbon::parse($data['due_at']) : null,
        );

        return back()->with('status', __('Befund aktualisiert.'));
    }
}
