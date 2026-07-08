<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Isms;

use App\Enums\Isms\{AuditKind, AuditStatus, CorrectiveActionStatus, FindingKind, FindingStatus};
use App\Http\Controllers\Controller;
use App\Models\Isms\{IsmsAudit, IsmsAuditFinding, IsmsCorrectiveAction, IsmsRequirement, IsmsScope};
use App\Models\User;
use App\Services\Isms\AuditService;
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Audits inkl. Feststellungen und Korrekturmaßnahmen (Feature 046,
 * Inkrement C): Listenseite mit Filtern (Scope/Status/Art/Jahr) und
 * Aufklappbereich je Audit (Findings mit Maßnahmen — Muster
 * conformity/index), Modal-CRUD, Statuswechsel-Dropdowns. Geschäftsregeln
 * (reportIssued nur mit Ergebnis, Feststellungen nur bei laufendem Audit,
 * Abschluss- und Wirksamkeitsregeln) erzwingt zentral der AuditService.
 * Autorisierung über IsmsAuditPolicy (isms.viewAny/view/manage; Findings
 * und Maßnahmen via manageFindings am Audit).
 */
class AuditController extends Controller {
    public function __construct(
        private readonly AuditService $service,
        private readonly SqidEncoder $sqids,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', IsmsAudit::class);

        $scopes = IsmsScope::query()->orderByDesc('is_default')->orderBy('name')->get();

        $filters = [
            'scope' => (string) $request->query('scope', 'all'),
            'status' => (string) $request->query('status', 'all'),
            'kind' => (string) $request->query('kind', 'all'),
            'year' => (string) $request->query('year', 'all'),
        ];

        $query = IsmsAudit::query()
            ->with([
                'scope',
                'leadAuditor',
                'findings' => fn($q) => $q->orderBy('finding_no'),
                'findings.requirement',
                'findings.correctiveActions' => fn($q) => $q->orderBy('id'),
                'findings.correctiveActions.owner',
            ])
            ->withCount(['findings', 'openFindings']);

        $scopeFilter = $this->resolveScope($filters['scope'], $scopes);
        if ($scopeFilter !== null) {
            $query->where('isms_scope_id', $scopeFilter->id);
        } else {
            $filters['scope'] = 'all';
        }
        if (AuditStatus::tryFrom($filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }
        if (AuditKind::tryFrom($filters['kind']) !== null) {
            $query->where('kind', $filters['kind']);
        }
        if (ctype_digit($filters['year'])) {
            $year = (int) $filters['year'];
            $query->where(function ($q) use ($year): void {
                $q->whereYear('planned_on', $year)->orWhereYear('performed_from', $year);
            });
        }

        // Jahresauswahl aus den vorhandenen Plan-/Durchführungsdaten.
        $years = IsmsAudit::query()
            ->get(['planned_on', 'performed_from'])
            ->flatMap(fn(IsmsAudit $a): array => array_filter([
                $a->planned_on?->year,
                $a->performed_from?->year,
            ]))
            ->unique()
            ->sortDesc()
            ->values();

        return view('isms.audits.index', [
            'audits' => $query->orderByDesc('audit_no')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'years' => $years,
            'scopes' => $scopes,
            'canManage' => Gate::allows('create', IsmsAudit::class),
        ]);
    }

    // ── Audits ─────────────────────────────────────────────────────────────

    public function create(): View {
        Gate::authorize('create', IsmsAudit::class);

        return view('isms.audits._form_dialog', [
            'audit' => null,
            'scopes' => IsmsScope::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'auditorOptions' => $this->userOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', IsmsAudit::class);

        $data = $this->validateAudit($request, withScope: true);

        /** @var User $creator */
        $creator = Auth::user();
        $scope = $this->resolveScope($data['scope'], null)
            ?? IsmsScope::query()->orderByDesc('is_default')->firstOrFail();
        $this->service->createAudit($creator, $scope, $data);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.audit_created'));
    }

    public function edit(IsmsAudit $audit): View {
        Gate::authorize('update', $audit);

        return view('isms.audits._form_dialog', [
            'audit' => $audit,
            'scopes' => IsmsScope::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'auditorOptions' => $this->userOptions(),
        ]);
    }

    public function update(Request $request, IsmsAudit $audit): RedirectResponse {
        Gate::authorize('update', $audit);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->updateAudit($audit, $actor, $this->validateAudit($request, withScope: false));

        return redirect()
            ->back()
            ->with('success', __('isms.flash.audit_updated'));
    }

    /** Statuswechsel entlang AuditStatus::allowedTransitions(). */
    public function transition(Request $request, IsmsAudit $audit): RedirectResponse {
        Gate::authorize('transition', $audit);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::enum(AuditStatus::class)],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->transitionAudit($audit, AuditStatus::from($data['status']), $actor);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.audit_transitioned'));
    }

    public function destroy(IsmsAudit $audit): RedirectResponse {
        Gate::authorize('delete', $audit);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->deleteAudit($audit, $actor);

        return redirect()
            ->route('isms.audits.index')
            ->with('success', __('isms.flash.audit_deleted'));
    }

    // ── Feststellungen ─────────────────────────────────────────────────────

    public function createFinding(IsmsAudit $audit): View {
        Gate::authorize('manageFindings', $audit);

        return view('isms.audits._finding_dialog', [
            'audit' => $audit,
            'finding' => null,
            'requirements' => $this->requirementOptions(),
        ]);
    }

    public function storeFinding(Request $request, IsmsAudit $audit): RedirectResponse {
        Gate::authorize('manageFindings', $audit);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->createFinding($audit, $actor, $this->validateFinding($request));

        return redirect()
            ->back()
            ->with('success', __('isms.flash.finding_created'));
    }

    public function editFinding(IsmsAuditFinding $finding): View {
        $audit = $finding->ismsAudit()->firstOrFail();
        Gate::authorize('manageFindings', $audit);

        return view('isms.audits._finding_dialog', [
            'audit' => $audit,
            'finding' => $finding,
            'requirements' => $this->requirementOptions(),
        ]);
    }

    public function updateFinding(Request $request, IsmsAuditFinding $finding): RedirectResponse {
        Gate::authorize('manageFindings', $finding->ismsAudit()->firstOrFail());

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->updateFinding($finding, $actor, $this->validateFinding($request));

        return redirect()
            ->back()
            ->with('success', __('isms.flash.finding_updated'));
    }

    /** Statuswechsel entlang FindingStatus::allowedTransitions() (Abschlussregeln im Service). */
    public function transitionFinding(Request $request, IsmsAuditFinding $finding): RedirectResponse {
        Gate::authorize('manageFindings', $finding->ismsAudit()->firstOrFail());

        $data = $request->validate([
            'status' => ['required', 'string', Rule::enum(FindingStatus::class)],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->transitionFinding($finding, FindingStatus::from($data['status']), $actor);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.finding_transitioned'));
    }

    public function destroyFinding(IsmsAuditFinding $finding): RedirectResponse {
        Gate::authorize('manageFindings', $finding->ismsAudit()->firstOrFail());

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->deleteFinding($finding, $actor);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.finding_deleted'));
    }

    // ── Korrekturmaßnahmen ─────────────────────────────────────────────────

    public function createAction(IsmsAuditFinding $finding): View {
        Gate::authorize('manageFindings', $finding->ismsAudit()->firstOrFail());

        return view('isms.audits._action_dialog', [
            'finding' => $finding,
            'action' => null,
            'owners' => $this->userOptions(),
        ]);
    }

    public function storeAction(Request $request, IsmsAuditFinding $finding): RedirectResponse {
        Gate::authorize('manageFindings', $finding->ismsAudit()->firstOrFail());

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->createAction($finding, $actor, $this->validateAction($request));

        return redirect()
            ->back()
            ->with('success', __('isms.flash.action_created'));
    }

    public function editAction(IsmsCorrectiveAction $action): View {
        $finding = $action->finding()->firstOrFail();
        Gate::authorize('manageFindings', $finding->ismsAudit()->firstOrFail());

        return view('isms.audits._action_dialog', [
            'finding' => $finding,
            'action' => $action,
            'owners' => $this->userOptions(),
        ]);
    }

    public function updateAction(Request $request, IsmsCorrectiveAction $action): RedirectResponse {
        Gate::authorize('manageFindings', $action->finding()->firstOrFail()->ismsAudit()->firstOrFail());

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->updateAction($action, $actor, $this->validateAction($request));

        return redirect()
            ->back()
            ->with('success', __('isms.flash.action_updated'));
    }

    /**
     * Statuswechsel inkl. Wirksamkeitsprüfung — effective/ineffective
     * erfordern die Pflicht-Notiz (Service); ineffective setzt die
     * Feststellung zurück auf inCorrection.
     */
    public function transitionAction(Request $request, IsmsCorrectiveAction $action): RedirectResponse {
        Gate::authorize('manageFindings', $action->finding()->firstOrFail()->ismsAudit()->firstOrFail());

        $data = $request->validate([
            'status' => ['required', 'string', Rule::enum(CorrectiveActionStatus::class)],
            'effectiveness_note' => ['nullable', 'string', 'max:5000'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->transitionAction(
            $action,
            CorrectiveActionStatus::from($data['status']),
            $actor,
            $data['effectiveness_note'] ?? null,
        );

        return redirect()
            ->back()
            ->with('success', __('isms.flash.action_transitioned'));
    }

    public function destroyAction(IsmsCorrectiveAction $action): RedirectResponse {
        Gate::authorize('manageFindings', $action->finding()->firstOrFail()->ismsAudit()->firstOrFail());

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->deleteAction($action, $actor);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.action_deleted'));
    }

    // ── Validierung & Optionen ─────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function validateAudit(Request $request, bool $withScope): array {
        /** @var User $actor */
        $actor = Auth::user();

        return $request->validate([
            ...($withScope ? ['scope' => ['required', 'string', 'max:64']] : []),
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'norm' => ['nullable', 'string', 'max:64'],
            'edition' => ['nullable', 'string', 'max:16'],
            'kind' => ['required', 'string', Rule::enum(AuditKind::class)],
            'planned_on' => ['nullable', 'date'],
            'isms_audit_program_id' => [
                'nullable', 'integer',
                Rule::exists('isms_audit_programs', 'id')->where('organization_id', $actor->organization_id),
            ],
            'performed_from' => ['nullable', 'date'],
            'performed_to' => ['nullable', 'date', 'after_or_equal:performed_from'],
            'lead_auditor_user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $actor->organization_id),
            ],
            'auditors' => ['nullable', 'string', 'max:5000'],
            'criteria' => ['nullable', 'string', 'max:10000'],
            'independence_note' => ['nullable', 'string', 'max:10000'],
            'summary' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFinding(Request $request): array {
        return $request->validate([
            'kind' => ['required', 'string', Rule::enum(FindingKind::class)],
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            // Org-sichere Auflösung erneut im Service (org-gescopte Query).
            'isms_requirement_id' => ['nullable', 'integer'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAction(Request $request): array {
        /** @var User $actor */
        $actor = Auth::user();

        return $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'root_cause' => ['nullable', 'string', 'max:10000'],
            'action_plan' => ['nullable', 'string', 'max:10000'],
            'owner_user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $actor->organization_id),
            ],
            'due_on' => ['nullable', 'date'],
        ]);
    }

    /**
     * Mitglieder der Organisation (Lead-Auditor/Maßnahmen-Owner-Auswahl).
     *
     * @return Collection<int, User>
     */
    private function userOptions(): Collection {
        /** @var User $user */
        $user = Auth::user();

        return User::query()
            ->where('organization_id', $user->organization_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Anforderungen der Organisation für die Referenz-Auswahl im
     * Feststellungs-Dialog.
     *
     * @return Collection<int, IsmsRequirement>
     */
    private function requirementOptions(): Collection {
        return IsmsRequirement::query()
            ->orderBy('norm')
            ->orderBy('ref_no')
            ->get(['id', 'norm', 'edition', 'ref_no', 'title']);
    }

    /**
     * Löst den Scope-Parameter (Sqid) auf — ungültige/fremde Werte ergeben
     * null (Filter „alle" bzw. Default-Scope-Fallback im store()).
     *
     * @param  Collection<int, IsmsScope>|null  $scopes
     */
    private function resolveScope(mixed $sqid, ?Collection $scopes): ?IsmsScope {
        if (! is_string($sqid) || $sqid === '' || $sqid === 'all') {
            return null;
        }

        $id = $this->sqids->decode(IsmsScope::class, $sqid);
        if ($id === null) {
            return null;
        }

        return $scopes !== null
            ? $scopes->firstWhere('id', $id)
            : IsmsScope::query()->whereKey($id)->first();
    }
}
