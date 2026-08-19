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

namespace App\Http\Controllers;

use App\Enums\User\Permission;
use App\Models\Patrol\{PatrolCheckpoint, PatrolRoute, PatrolRun};
use App\Models\{Site, User};
use App\Services\Patrol\PatrolService;
use App\Services\SqidEncoder;
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
    public function __construct(private readonly PatrolService $service) {}

    public function index(): View {
        Gate::authorize(Permission::DispatchViewAny->value);

        return view('patrols.index', [
            'routes' => PatrolRoute::query()
                ->with('site:id,name')
                ->withCount('checkpoints')
                ->orderBy('name')
                ->paginate(25),
            'openRuns' => PatrolRun::query()
                ->where('status', PatrolRun::STATUS_RUNNING)
                ->with(['route:id,name', 'starter:id,name'])
                ->orderByDesc('started_at')
                ->get(),
            'canManage' => Gate::allows(Permission::DispatchManage->value),
        ]);
    }

    public function show(PatrolRoute $patrolRoute): View {
        Gate::authorize(Permission::DispatchViewAny->value);
        $this->guard($patrolRoute);

        return view('patrols.show', [
            'route' => $patrolRoute->load(['site:id,name', 'checkpoints']),
            'runs' => $patrolRoute->runs()->with('starter:id,name')->withCount('scans')->orderByDesc('started_at')->limit(20)->get(),
            'canManage' => Gate::allows(Permission::DispatchManage->value),
        ]);
    }

    public function create(): View {
        Gate::authorize(Permission::DispatchManage->value);

        return view('patrols._form_dialog', [
            'sites' => Site::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, SqidEncoder $sqids): RedirectResponse {
        Gate::authorize(Permission::DispatchManage->value);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'site' => ['nullable', 'string'],
        ]);

        $siteId = null;
        if (filled($data['site'] ?? null)) {
            $siteId = $sqids->decode(Site::class, (string) $data['site']);
            abort_if($siteId === null || ! Site::query()->whereKey($siteId)->exists(), 422);
        }

        $route = PatrolRoute::query()->create([
            'organization_id' => $this->orgId(),
            'name' => $data['name'],
            'site_id' => $siteId,
            'active' => true,
            'created_by' => Auth::id(),
        ]);
        $route->audit('patrol.route_created', []);

        return redirect()->route('patrols.show', $route)->with('success', __('Route angelegt — jetzt Kontrollpunkte hinzufügen.'));
    }

    public function addCheckpoint(Request $request, PatrolRoute $patrolRoute): RedirectResponse {
        Gate::authorize(Permission::DispatchManage->value);
        $this->guard($patrolRoute);

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
        $this->guard($patrolRoute);
        abort_unless($checkpoint->patrol_route_id === $patrolRoute->id, 404);

        $issued = $this->service->reissueToken($checkpoint);

        return back()->with('patrol_token_once', $issued['token'])
            ->with('success', __('Neuer Token für „:label" — der alte ist ab sofort wertlos.', ['label' => $checkpoint->label]));
    }

    public function start(PatrolRoute $patrolRoute): RedirectResponse {
        Gate::authorize(Permission::DispatchViewAny->value);
        $this->guard($patrolRoute);

        try {
            $run = $this->service->start($patrolRoute, $this->actor());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('patrols.runs.show', $run)->with('success', __('Rundgang gestartet.'));
    }

    public function showRun(Request $request, PatrolRun $patrolRun): View|\Illuminate\Http\Response {
        Gate::authorize(Permission::DispatchViewAny->value);
        abort_unless($patrolRun->organization_id === $this->orgId(), 404);

        $patrolRun->load(['route.checkpoints', 'scans', 'starter:id,name']);
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
        abort_unless($patrolRun->organization_id === $this->orgId(), 404);

        $data = $request->validate(['token' => ['required', 'string', 'max:64']]);

        try {
            $checkpoint = $this->service->scan($patrolRun, (string) $data['token']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Kontrollpunkt „:label" bestätigt.', ['label' => $checkpoint->label]));
    }

    public function complete(Request $request, PatrolRun $patrolRun): RedirectResponse {
        Gate::authorize(Permission::DispatchViewAny->value);
        abort_unless($patrolRun->organization_id === $this->orgId(), 404);

        try {
            $this->service->complete($patrolRun, $this->actor(), (string) $request->input('deviation_note', '') ?: null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('patrols.show', PatrolRoute::query()->findOrFail($patrolRun->patrol_route_id))
            ->with('success', __('Rundgang abgeschlossen.'));
    }

    private function guard(PatrolRoute $route): void {
        abort_unless($route->organization_id === $this->orgId(), 404);
    }

    private function orgId(): int {
        $org = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        /** @var User $user */
        $user = Auth::user();

        return (int) ($org->id ?? $user->organization_id);
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
