<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisCaseController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Crisis;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crisis\{AddCrisisLinkRequest, AssignCrisisTeamRequest, MarkCrisisCommunicationSentRequest, StoreCrisisActionRequest, StoreCrisisCaseRequest, StoreCrisisCommunicationRequest, StoreCrisisContinuityImpactRequest, StoreCrisisDecisionRequest, StoreCrisisReviewRequest, StoreCrisisRoleRequest, StoreCrisisSituationReportRequest, UpdateCrisisActionRequest, UpdateCrisisCaseStatusRequest, UpdateCrisisContinuityImpactRequest};
use App\Models\Crisis\{CrisisCase, CrisisCommunication, CrisisRole, CrisisTeamAssignment};
use App\Models\User;
use App\Services\Crisis\{CrisisAlertService, CrisisDeadlineService};
use App\Support\Query\DateRange;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Krisenakten (Feature 070, MVP-212–221, D9): Dashboard, Akte, Stab,
 * Entscheidungen/Maßnahmen, Kommunikation, BCM, Verknüpfungen, Nachbereitung.
 */
class CrisisCaseController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly CrisisAlertService $alerts,
        private readonly CrisisDeadlineService $deadlines,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', CrisisCase::class);

        $status = $request->string('status')->toString();
        $statusFilter = in_array($status, CrisisCase::STATUSES, true) ? $status : '';

        return view('crisis.index', [
            'cases' => CrisisCase::query()
                ->with('responsible')
                ->when($statusFilter !== '', fn($q) => $q->where('status', $statusFilter))
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'activeCount' => CrisisCase::query()->whereIn('status', CrisisCase::ACTIVE_STATUSES)->count(),
            'overdueActions' => \App\Models\Crisis\CrisisAction::query()
                ->whereIn('status', ['open', 'in_progress'])
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count(),
            'openDecisionsExercises' => \App\Models\Crisis\CrisisExercise::query()
                ->whereNotNull('next_due_on')
                ->where('next_due_on', '<', DateRange::dayAfter(now()->addDays(30)))
                ->count(),
            'statuses' => CrisisCase::STATUSES,
            'filters' => ['status' => $statusFilter],
        ]);
    }

    public function create(): View {
        Gate::authorize('create', CrisisCase::class);

        return view('crisis._form_dialog', ['case' => new CrisisCase()]);
    }

    public function store(StoreCrisisCaseRequest $request): RedirectResponse {
        Gate::authorize('create', CrisisCase::class);
        $data = $request->validated();

        /** @var User $actor */
        $actor = Auth::user();
        $case = CrisisCase::query()->create([
            ...$data,
            'organization_id' => $this->currentOrganization()->id,
            'status' => 'reported',
            'responsible_user_id' => $actor->id,
            'created_by' => $actor->id,
        ]);
        $case->audit('crisis.reported', ['title' => $case->title, 'severity' => $case->severity]);

        return redirect()->route('crisis.show', $case)->with('status', __('Krisenakte eröffnet.'));
    }

    public function show(CrisisCase $case): View {
        Gate::authorize('view', $case);
        $case->load(['team.user', 'team.deputy', 'team.role', 'situationReports', 'decisions', 'actions.assignee', 'communications', 'continuityImpacts', 'links.linkable', 'review', 'responsible']);

        return view('crisis.show', [
            'case' => $case,
            'roles' => CrisisRole::query()->where('active', true)->orderBy('name')->get(),
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
            'deadlines' => $this->deadlines->deadlinesFor($case),
            'canManage' => Gate::allows('update', $case),
        ]);
    }

    // ── Lifecycle ────────────────────────────────────────────────────────

    public function activate(CrisisCase $case): RedirectResponse {
        Gate::authorize('approve', $case);
        if (! in_array($case->status, ['reported', 'assessed'], true)) {
            return back()->with('error', __('Nur gemeldete/bewertete Krisen werden aktiviert.'));
        }
        $case->update(['status' => 'activated', 'activated_at' => now()]);
        $case->audit('crisis.activated', []);

        return back()->with('status', __('Krise aktiviert — Meldefristen laufen ab jetzt.'));
    }

    public function updateStatus(UpdateCrisisCaseStatusRequest $request, CrisisCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        $data = $request->validated();
        $case->update(['status' => $data['status']]);

        return back()->with('status', __('Status aktualisiert.'));
    }

    public function allClear(CrisisCase $case): RedirectResponse {
        Gate::authorize('approve', $case);
        if (! $case->isActive()) {
            return back()->with('error', __('Nur aktive Krisen werden entwarnt.'));
        }
        $case->update(['status' => 'all_clear', 'all_clear_at' => now()]);
        $case->audit('crisis.all_clear', []);

        return back()->with('status', __('Entwarnung dokumentiert.'));
    }

    public function close(CrisisCase $case): RedirectResponse {
        Gate::authorize('approve', $case);
        if ($case->review()->doesntExist()) {
            return back()->with('error', __('Vor dem Abschluss braucht die Krise eine Nachbereitung.'));
        }
        $case->update(['status' => 'closed', 'closed_at' => now()]);
        $case->audit('crisis.closed', []);

        return back()->with('status', __('Krisenakte geschlossen.'));
    }

    // ── Krisenstab + Alarmierung (MVP-213) ───────────────────────────────

    public function storeRole(StoreCrisisRoleRequest $request): RedirectResponse {
        Gate::authorize('create', CrisisCase::class);
        $data = $request->validated();

        CrisisRole::query()->firstOrCreate(
            ['organization_id' => $this->currentOrganization()->id, 'name' => $data['name']],
            ['description' => $data['description'] ?? null, 'active' => true],
        );

        return back()->with('status', __('Stabsrolle angelegt.'));
    }

    public function assignTeam(AssignCrisisTeamRequest $request, CrisisCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        $data = $request->validated();

        $assignment = $case->team()->firstOrCreate([
            'organization_id' => $case->organization_id,
            'crisis_role_id' => $data['crisis_role_id'],
            'user_id' => $data['user_id'],
        ], [
            'deputy_user_id' => $data['deputy_user_id'] ?? null,
            'contact_note' => $data['contact_note'] ?? null,
        ]);
        // Notfallzugriff: die Benennung ist der auditierten Zugriffsgrund.
        $case->audit('crisis.team_assigned', ['assignment_id' => $assignment->id, 'user_id' => $data['user_id']]);

        return back()->with('status', __('Stabsmitglied benannt (Notfallzugriff auf die Akte aktiv).'));
    }

    public function removeTeam(CrisisCase $case, CrisisTeamAssignment $assignment): RedirectResponse {
        Gate::authorize('update', $case);
        abort_unless($assignment->crisis_case_id === $case->id, 404);
        $assignment->delete();
        $case->audit('crisis.team_removed', ['user_id' => $assignment->user_id]);

        return back()->with('status', __('Benennung entfernt.'));
    }

    public function alert(CrisisCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        $count = $this->alerts->alert($case, $this->actor());

        return back()->with('status', __(':count Stabsmitglieder alarmiert (Ruhezeiten überstimmt).', ['count' => $count]));
    }

    public function escalateAlert(CrisisCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        $count = $this->alerts->escalate($case, $this->actor());

        return back()->with('status', __(':count unquittierte Alarme eskaliert (inkl. Stellvertretung).', ['count' => $count]));
    }

    public function acknowledge(CrisisCase $case, CrisisTeamAssignment $assignment): RedirectResponse {
        Gate::authorize('acknowledge', $case);
        abort_unless($assignment->crisis_case_id === $case->id, 404);

        try {
            $this->alerts->acknowledge($assignment, $this->actor());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Alarm quittiert.'));
    }

    // ── Lagebild + Entscheidungen (MVP-214) ──────────────────────────────

    public function storeSituationReport(StoreCrisisSituationReportRequest $request, CrisisCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        $data = $request->validated();

        $case->situationReports()->create([
            'organization_id' => $case->organization_id,
            'version' => (int) $case->situationReports()->max('version') + 1,
            ...$data,
            'created_by' => (int) Auth::id(),
        ]);

        return back()->with('status', __('Lagebericht (neue Version) dokumentiert.'));
    }

    public function storeDecision(StoreCrisisDecisionRequest $request, CrisisCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        $data = $request->validated();

        $case->decisions()->create([
            'organization_id' => $case->organization_id,
            'decided_at' => now(),
            'decision' => $data['decision'],
            'rationale' => $data['rationale'] ?? null,
            'decided_by' => (int) Auth::id(),
        ]);

        return back()->with('status', __('Entscheidung protokolliert.'));
    }

    // ── Maßnahmen (MVP-216) ──────────────────────────────────────────────

    public function storeAction(StoreCrisisActionRequest $request, CrisisCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        $data = $request->validated();

        $case->actions()->create([
            'organization_id' => $case->organization_id,
            ...$data,
            'status' => 'open',
        ]);

        return back()->with('status', __('Maßnahme erfasst.'));
    }

    public function updateAction(UpdateCrisisActionRequest $request, CrisisCase $case, \App\Models\Crisis\CrisisAction $action): RedirectResponse {
        Gate::authorize('update', $case);
        abort_unless($action->crisis_case_id === $case->id, 404);
        $data = $request->validated();

        $action->update([
            'status' => $data['status'],
            'evidence_note' => $data['evidence_note'] ?? $action->evidence_note,
            'escalated_at' => $data['status'] === 'open' && $action->due_at !== null && $action->due_at->isPast() ? now() : $action->escalated_at,
        ]);

        return back()->with('status', __('Maßnahme aktualisiert.'));
    }

    // ── Kommunikation (MVP-217) ──────────────────────────────────────────

    public function storeCommunication(StoreCrisisCommunicationRequest $request, CrisisCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        $data = $request->validated();

        $case->communications()->create([
            'organization_id' => $case->organization_id,
            ...$data,
            'status' => 'draft',
            'created_by' => (int) Auth::id(),
        ]);

        return back()->with('status', __('Kommunikationsentwurf angelegt.'));
    }

    public function approveCommunication(CrisisCase $case, CrisisCommunication $communication): RedirectResponse {
        Gate::authorize('approve', $case);
        abort_unless($communication->crisis_case_id === $case->id, 404);
        if ($communication->status !== 'draft') {
            return back()->with('error', __('Nur Entwürfe werden freigegeben.'));
        }
        if ((int) $communication->created_by === (int) Auth::id()) {
            return back()->with('error', __('Selbstfreigabe ist nicht zulässig.'));
        }

        $communication->update(['status' => 'approved', 'approved_by' => (int) Auth::id(), 'approved_at' => now()]);
        $case->audit('crisis.communication_approved', ['audience' => $communication->audience]);

        return back()->with('status', __('Kommunikation freigegeben.'));
    }

    public function markCommunicationSent(MarkCrisisCommunicationSentRequest $request, CrisisCase $case, CrisisCommunication $communication): RedirectResponse {
        Gate::authorize('update', $case);
        abort_unless($communication->crisis_case_id === $case->id, 404);
        if ($communication->status !== 'approved') {
            return back()->with('error', __('Aussendung erst nach Freigabe.'));
        }
        $data = $request->validated();

        $communication->update(['status' => 'sent', 'channel' => $data['channel'], 'sent_at' => now()]);
        $case->audit('crisis.communication_sent', ['audience' => $communication->audience, 'channel' => $data['channel']]);

        return back()->with('status', __('Aussendung dokumentiert.'));
    }

    // ── BCM (MVP-219), Verknüpfung (MVP-218), Nachbereitung (MVP-221) ────

    public function storeContinuityImpact(StoreCrisisContinuityImpactRequest $request, CrisisCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        $data = $request->validated();

        $case->continuityImpacts()->create([
            'organization_id' => $case->organization_id,
            ...$data,
            'status' => 'down',
        ]);

        return back()->with('status', __('Kritischen Prozess erfasst.'));
    }

    public function updateContinuityImpact(UpdateCrisisContinuityImpactRequest $request, CrisisCase $case, \App\Models\Crisis\CrisisContinuityImpact $impact): RedirectResponse {
        Gate::authorize('update', $case);
        abort_unless($impact->crisis_case_id === $case->id, 404);
        $data = $request->validated();
        $impact->update($data);

        return back()->with('status', __('Wiederanlaufstatus aktualisiert.'));
    }

    public function addLink(AddCrisisLinkRequest $request, CrisisCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        $data = $request->validated();

        $map = [
            'service_ticket' => \App\Models\ServiceTicket::class,
            'isms_incident' => \App\Models\Isms\IsmsSecurityIncident::class,
            'privacy_incident' => \App\Models\Privacy\Incident::class,
            'safety_event' => \App\Models\SafetyEvent::class,
            'procedure_run' => \App\Models\ProcedureRun::class,
            'document' => \App\Models\Document::class,
        ];
        $class = $map[$data['linkable_type']];
        $id = \App\Support\Sqid::decodeOrNumeric($class, $data['linkable_sqid']);
        $target = $id !== null ? $class::query()->whereKey($id)->first() : null;
        if ($target === null) {
            return back()->with('error', __('Zielobjekt nicht gefunden.'));
        }

        $case->links()->firstOrCreate([
            'organization_id' => $case->organization_id,
            'linkable_type' => $target->getMorphClass(),
            'linkable_id' => $target->getKey(),
        ], [
            'note' => $data['note'] ?? null,
            'created_by' => (int) Auth::id(),
        ]);
        $case->audit('crisis.linked', ['type' => $data['linkable_type'], 'id' => $target->getKey()]);

        return back()->with('status', __('Vorgang verknüpft — das Fachmodul bleibt führend.'));
    }

    public function storeReview(StoreCrisisReviewRequest $request, CrisisCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        if (! in_array($case->status, ['all_clear', 'post_review'], true)) {
            return back()->with('error', __('Nachbereitung erst nach der Entwarnung.'));
        }
        if ($case->review()->exists()) {
            return back()->with('error', __('Es existiert bereits eine Nachbereitung.'));
        }

        $data = $request->validated();

        $case->review()->create([
            'organization_id' => $case->organization_id,
            ...$data,
            'reviewed_by' => (int) Auth::id(),
            'reviewed_at' => now(),
        ]);
        $case->update(['status' => 'post_review']);
        $case->audit('crisis.reviewed', []);

        return back()->with('status', __('Nachbereitung dokumentiert.'));
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
