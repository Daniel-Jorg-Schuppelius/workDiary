<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PatrolController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Patrol;

use App\Enums\Patrol\PatrolRunStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Facility\Site;
use App\Models\Patrol\{PatrolCheckpoint, PatrolRoute, PatrolRun};
use App\Services\Patrol\PatrolService;
use App\Support\{ErrorText, SqidEncoder};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Wächterrundgänge (Feature 089, MVP-663–665): Routen, Durchführung mit
 * Scan-Nachweis, Abweichungs-Eskalation über offene Punkte.
 *
 * Rechte: dispatch.* (Leitstelle plant und überwacht), Modul module.planung.
 * Der Scan selbst braucht nur dispatch.viewAny — Durchführende sind keine
 * Planer.
 */
class PatrolController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly PatrolService $service) {}

    public function index(): View {
        Gate::authorize(Permission::DispatchViewAny->value);

        return view('patrols.index', [
            'routes' => PatrolRoute::query()
                ->with('site:id,name')
                ->withCount(['checkpoints' => fn ($q) => $q->whereNull('deactivated_at')])
                ->orderBy('name')
                ->paginate(25),
            'openRuns' => PatrolRun::query()
                ->where('status', PatrolRunStatus::Running)
                ->with(['route:id,name', 'starter:id,name'])
                ->orderByDesc('started_at')
                ->get(),
            'canManage' => Gate::allows(Permission::DispatchManage->value),
        ]);
    }

    public function show(PatrolRoute $patrolRoute): View {
        Gate::authorize(Permission::DispatchViewAny->value);
        $this->assertSameOrganization($patrolRoute);

        return view('patrols.show', [
            'route' => $patrolRoute->load(['site:id,name', 'responsible:id,name', 'checkpoints']),
            'runs' => $patrolRoute->runs()->with(['starter:id,name', 'abortedBy:id,name'])->withCount('scans')->orderByDesc('started_at')->limit(20)->get(),
            'canManage' => Gate::allows(Permission::DispatchManage->value),
        ]);
    }

    public function create(): View {
        Gate::authorize(Permission::DispatchManage->value);

        return view('patrols._form_dialog', $this->formOptions() + ['route' => null]);
    }

    public function store(Request $request, SqidEncoder $sqids): RedirectResponse {
        Gate::authorize(Permission::DispatchManage->value);

        $route = PatrolRoute::query()->create($this->validatedRoute($request, $sqids) + [
            'organization_id' => $this->currentOrganizationId(),
            'active' => true,
            'created_by' => Auth::id(),
        ]);
        $route->audit('patrol.route_created', []);

        return redirect()->route('patrols.show', $route)->with('success', __('Route angelegt — jetzt Kontrollpunkte hinzufügen.'));
    }

    public function edit(PatrolRoute $patrolRoute): View {
        Gate::authorize(Permission::DispatchManage->value);
        $this->assertSameOrganization($patrolRoute);

        return view('patrols._form_dialog', $this->formOptions() + ['route' => $patrolRoute->load(['site:id', 'responsible:id'])]);
    }

    public function update(Request $request, PatrolRoute $patrolRoute, SqidEncoder $sqids): RedirectResponse {
        Gate::authorize(Permission::DispatchManage->value);
        $this->assertSameOrganization($patrolRoute);

        $this->service->updateRoute($patrolRoute, $this->validatedRoute($request, $sqids));

        return redirect()->route('patrols.show', $patrolRoute)->with('success', __('patrol.route.updated'));
    }

    /** Route deaktivieren bzw. wieder freigeben (MVP-1611). */
    public function toggleActive(PatrolRoute $patrolRoute): RedirectResponse {
        Gate::authorize(Permission::DispatchManage->value);
        $this->assertSameOrganization($patrolRoute);

        $this->service->setRouteActive($patrolRoute, ! $patrolRoute->active);

        return back()->with('success', $patrolRoute->active ? __('patrol.route.activated') : __('patrol.route.deactivated'));
    }

    public function editCheckpoint(PatrolRoute $patrolRoute, PatrolCheckpoint $checkpoint): View {
        Gate::authorize(Permission::DispatchManage->value);
        $this->assertSameOrganization($patrolRoute);
        abort_unless($checkpoint->patrol_route_id === $patrolRoute->id && $checkpoint->deactivated_at === null, 404);

        return view('patrols._checkpoint_dialog', ['route' => $patrolRoute, 'checkpoint' => $checkpoint]);
    }

    public function updateCheckpoint(Request $request, PatrolRoute $patrolRoute, PatrolCheckpoint $checkpoint): RedirectResponse {
        Gate::authorize(Permission::DispatchManage->value);
        $this->assertSameOrganization($patrolRoute);
        abort_unless($checkpoint->patrol_route_id === $patrolRoute->id, 404);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:160'],
            'expected_offset_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'tolerance_minutes' => ['required', 'integer', 'min:0', 'max:240'],
        ]);

        try {
            $this->service->updateCheckpoint($checkpoint, (string) $data['label'], (int) $data['expected_offset_minutes'], (int) $data['tolerance_minutes']);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('patrols.show', $patrolRoute)->with('success', __('patrol.checkpoint.updated', ['label' => $checkpoint->label]));
    }

    /** Kontrollpunkt deaktivieren — frühere Läufe zählen ihn weiter, neue erwarten ihn nicht mehr. */
    public function deactivateCheckpoint(PatrolRoute $patrolRoute, PatrolCheckpoint $checkpoint): RedirectResponse {
        Gate::authorize(Permission::DispatchManage->value);
        $this->assertSameOrganization($patrolRoute);
        abort_unless($checkpoint->patrol_route_id === $patrolRoute->id, 404);

        $this->service->deactivateCheckpoint($checkpoint);

        return back()->with('success', __('patrol.checkpoint.deactivated_done', ['label' => $checkpoint->label]));
    }

    /** @return array{sites: \Illuminate\Support\Collection<int, Site>, users: \Illuminate\Support\Collection<int, \App\Models\Platform\User>} */
    private function formOptions(): array {
        return [
            'sites' => Site::query()->orderBy('name')->get(['id', 'name']),
            'users' => \App\Models\Platform\User::query()
                ->where('organization_id', $this->currentOrganizationId())
                ->whereNull('customer_id')
                ->whereNull('deactivated_at')
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    /** @return array{name: string, site_id: ?int, responsible_user_id: ?int} */
    private function validatedRoute(Request $request, SqidEncoder $sqids): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'site' => ['nullable', 'string'],
            'responsible' => ['nullable', 'string'],
        ]);

        $siteId = null;
        if (filled($data['site'] ?? null)) {
            $siteId = $sqids->decode(Site::class, (string) $data['site']);
            abort_if($siteId === null || ! Site::query()->whereKey($siteId)->exists(), 422);
        }

        $responsibleId = null;
        if (filled($data['responsible'] ?? null)) {
            $responsibleId = $sqids->decode(\App\Models\Platform\User::class, (string) $data['responsible']);
            abort_if($responsibleId === null || ! \App\Models\Platform\User::query()
                ->where('organization_id', $this->currentOrganizationId())
                ->whereNull('customer_id')
                ->whereKey($responsibleId)
                ->exists(), 422);
        }

        return ['name' => (string) $data['name'], 'site_id' => $siteId, 'responsible_user_id' => $responsibleId];
    }

    public function addCheckpoint(Request $request, PatrolRoute $patrolRoute): RedirectResponse {
        Gate::authorize(Permission::DispatchManage->value);
        $this->assertSameOrganization($patrolRoute);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:160'],
            'expected_offset_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'tolerance_minutes' => ['required', 'integer', 'min:0', 'max:240'],
        ]);

        $issued = $this->service->addCheckpoint(
            $patrolRoute,
            (string) $data['label'],
            (int) $data['expected_offset_minutes'],
            (int) $data['tolerance_minutes'],
        );

        // Der Klartext-Token erscheint genau EINMAL - er gehört auf den Tag
        // gedruckt/geschrieben, nicht in die Datenbank.
        return back()->with('patrol_token_once', $issued['token'])
            ->with('success', __('Kontrollpunkt „:label" angelegt.', ['label' => $issued['checkpoint']->label]));
    }

    /** Verlorener Tag: neuer Token, gleicher Punkt. */
    public function reissueToken(PatrolRoute $patrolRoute, PatrolCheckpoint $checkpoint): RedirectResponse {
        Gate::authorize(Permission::DispatchManage->value);
        $this->assertSameOrganization($patrolRoute);
        abort_unless($checkpoint->patrol_route_id === $patrolRoute->id, 404);

        try {
            $issued = $this->service->reissueToken($checkpoint);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('patrol_token_once', $issued['token'])
            ->with('success', __('Neuer Token für „:label" — der alte ist ab sofort wertlos.', ['label' => $checkpoint->label]));
    }

    public function start(PatrolRoute $patrolRoute): RedirectResponse {
        Gate::authorize(Permission::DispatchViewAny->value);
        $this->assertSameOrganization($patrolRoute);

        try {
            $run = $this->service->start($patrolRoute, $this->authUser());
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('patrols.runs.show', $run)->with('success', __('Rundgang gestartet.'));
    }

    public function showRun(Request $request, PatrolRun $patrolRun): View|\Illuminate\Http\Response {
        Gate::authorize(Permission::DispatchViewAny->value);
        abort_unless($patrolRun->organization_id === $this->currentOrganizationId(), 404);

        $patrolRun->load(['route.checkpoints', 'scans', 'starter:id,name', 'abortedBy:id,name']);
        $scansByCheckpoint = $patrolRun->scans->keyBy('patrol_checkpoint_id');

        // Rundgangsbericht (Folgepunkt aus MVP-665): der Nachweis für den
        // Auftraggeber - je Kontrollpunkt Soll, Ist und Abweichung. View →
        // Design → PDF über den zentralen Renderer, kein dompdf hier.
        if ($request->query('export') === 'pdf') {
            $bytes = app(\App\Services\DocumentDesign\DocumentDesignRenderer::class)->renderPdf(
                \App\Enums\DocumentDesign\RenderDocumentKind::Report,
                'pdf.patrol-report',
                [
                    'run' => $patrolRun,
                    'scans' => $scansByCheckpoint,
                    'missed' => $this->service->missedCheckpoints($patrolRun),
                ],
                (int) $patrolRun->organization_id,
            );

            return response($bytes, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="Rundgang-' . $patrolRun->sqid . '.pdf"',
            ]);
        }

        return view('patrols.run', [
            'run' => $patrolRun,
            'scans' => $scansByCheckpoint,
            'missed' => $this->service->missedCheckpoints($patrolRun),
        ]);
    }

    public function scan(Request $request, PatrolRun $patrolRun): RedirectResponse {
        Gate::authorize(Permission::DispatchViewAny->value);
        abort_unless($patrolRun->organization_id === $this->currentOrganizationId(), 404);

        $data = $request->validate(['token' => ['required', 'string', 'max:64']]);

        try {
            $checkpoint = $this->service->scan($patrolRun, (string) $data['token']);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('Kontrollpunkt „:label" bestätigt.', ['label' => $checkpoint->label]));
    }

    public function complete(Request $request, PatrolRun $patrolRun): RedirectResponse {
        Gate::authorize(Permission::DispatchViewAny->value);
        abort_unless($patrolRun->organization_id === $this->currentOrganizationId(), 404);

        try {
            $this->service->complete($patrolRun, $this->authUser(), (string) $request->input('deviation_note', '') ?: null);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('patrols.show', PatrolRoute::query()->findOrFail($patrolRun->patrol_route_id))
            ->with('success', __('Rundgang abgeschlossen.'));
    }

    public function abortCreate(PatrolRun $patrolRun): View {
        Gate::authorize(Permission::DispatchViewAny->value);
        abort_unless($patrolRun->organization_id === $this->currentOrganizationId(), 404);
        abort_unless($patrolRun->status->canTransitionTo(PatrolRunStatus::Aborted), 404);

        return view('patrols._abort_dialog', ['run' => $patrolRun->load('route:id,name')]);
    }

    /** Wer abschließen darf, darf auch abbrechen — dieselbe Regel wie in complete(). */
    public function abortStore(Request $request, PatrolRun $patrolRun): RedirectResponse {
        Gate::authorize(Permission::DispatchViewAny->value);
        abort_unless($patrolRun->organization_id === $this->currentOrganizationId(), 404);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $this->service->abort($patrolRun, $this->authUser(), (string) $data['reason']);
        } catch (RuntimeException $e) {
            return redirect()->route('patrols.runs.show', $patrolRun)->with('error', ErrorText::for($e));
        }

        return redirect()->route('patrols.show', PatrolRoute::query()->findOrFail($patrolRun->patrol_route_id))
            ->with('success', __('Rundgang abgebrochen.'));
    }
}
