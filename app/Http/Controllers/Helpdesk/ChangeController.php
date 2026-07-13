<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChangeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveChangeRequest;
use App\Models\{Asset, Change, ChangeTemplate, Problem, ProcedureTemplate, ServiceTicket, User};
use App\Services\ServiceTicket\ChangeService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Change-/CAB-UI (Feature 065, MVP-157): Anlage als Modal, Typ-Regeln
 * erzwingt der {@see ChangeService} (standard nur aus FREIGEGEBENER
 * Vorlage, Rollback-Pflicht bei normal/emergency, PIR-Zwang bei
 * emergency), template_snapshot ist READ-ONLY.
 *
 * ENTSCHEIDUNG (Plan A3/5): Change-Freigaben laufen über die GEMEINSAME
 * Genehmigungs-Inbox (servicedesk.approvals.*, MVP-154) mit dem Recht
 * service_request.approve — bewusst KEIN eigenes change.approve-Recht,
 * damit es genau EINE Genehmigungsmechanik gibt. Dieser Controller
 * genehmigt daher nichts; ApprovalInboxController::decide() verzweigt
 * nach approvable_type auf ChangeService::decide().
 */
class ChangeController extends Controller {
    private const STATUSES = ['draft', 'pending_approval', 'approved', 'implementing', 'done', 'cancelled'];

    public function __construct(private readonly ChangeService $changes) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', Change::class);

        $type = (string) $request->query('change_type', '');
        $status = (string) $request->query('status', '');
        $outcome = (string) $request->query('outcome', '');

        $query = Change::query()
            ->with(['problem:id,title', 'template:id,name,version'])
            ->withCount(['tickets', 'assets']);
        if (in_array($type, ['standard', 'normal', 'emergency'], true)) {
            $query->where('change_type', $type);
        }
        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }
        if (in_array($outcome, Change::OUTCOMES, true)) {
            $query->where('outcome', $outcome);
        }

        // CAB-Sicht: kommende Fenster zuerst (window_from aufsteigend,
        // Changes ohne Fenster ans Ende), danach jüngste zuerst.
        $query->orderByRaw('(window_from is null) asc')
            ->orderBy('window_from')
            ->orderByDesc('id');

        return view('helpdesk.changes.index', [
            'changes' => $query->paginate(25)->withQueryString(),
            'filters' => ['change_type' => $type, 'status' => $status, 'outcome' => $outcome],
            'typeLabels' => self::typeLabels(),
            'statusLabels' => self::statusLabels(),
            'outcomeLabels' => self::outcomeLabels(),
            'canManage' => Gate::allows('create', Change::class),
        ]);
    }

    public function show(Change $change): View {
        Gate::authorize('view', $change);

        $change->load([
            'approvals.decidedBy:id,name',
            'tickets:id,ticket_no,title,status',
            'assets:id,asset_no,name',
            'problem:id,title,status',
            'template:id,name,version,approved',
            'creator:id,name',
        ]);

        $canManage = Gate::allows('update', $change);

        return view('helpdesk.changes.show', [
            'change' => $change,
            'typeLabels' => self::typeLabels(),
            'statusLabels' => self::statusLabels(),
            'outcomeLabels' => self::outcomeLabels(),
            'canManage' => $canManage,
            'assetOptions' => $canManage && in_array($change->status, ['draft', 'pending_approval', 'approved', 'implementing'], true)
                ? Asset::query()->orderBy('name')->limit(200)->get(['id', 'asset_no', 'name'])
                : collect(),
            'procedureTemplates' => $canManage && $change->status === 'approved'
                ? ProcedureTemplate::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    /** Modal — optional mit vorbelegtem Problem (?problem=Sqid). */
    public function create(Request $request): View {
        Gate::authorize('create', Change::class);

        return view('helpdesk.changes._form_dialog', [
            'preselectedProblem' => Sqid::decode(Problem::class, $request->query('problem')),
            ...$this->formOptions(),
        ]);
    }

    public function store(SaveChangeRequest $request): RedirectResponse {
        Gate::authorize('create', Change::class);

        $data = $request->validated();
        $user = $this->actor();

        // Vorlage org-gescopt laden (Global Scope + Rule als erste Linie);
        // ob sie freigegeben ist, entscheidet der Service.
        $template = isset($data['change_template_id'])
            ? ChangeTemplate::query()->find((int) $data['change_template_id'])
            : null;

        try {
            $change = $this->changes->submit([
                'title' => $data['title'],
                'change_type' => $data['change_type'],
                'reason' => $data['reason'] ?? null,
                'scope' => $data['scope'] ?? null,
                'risk' => $data['risk'] ?? null,
                'impact' => $data['impact'] ?? null,
                'urgency' => $data['urgency'] ?? null,
                'window_from' => $data['window_from'] ?? null,
                'window_to' => $data['window_to'] ?? null,
                'implementation_plan' => $data['implementation_plan'] ?? null,
                'test_plan' => $data['test_plan'] ?? null,
                'rollback_plan' => $data['rollback_plan'] ?? null,
                'problem_id' => $data['problem_id'] ?? null,
            ], $user, $this->buildApprovalChain((array) ($data['approval_steps'] ?? [])), $template);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['change_type' => $e->getMessage()]);
        }

        // Verknüpfungen: Org-Grenze ist validiert (ExistsInCurrentOrganization);
        // Assets laufen über den Service (Audit + zweite Tenant-Linie).
        $ticketIds = array_map('intval', (array) ($data['ticket_ids'] ?? []));
        if ($ticketIds !== []) {
            $change->tickets()->syncWithoutDetaching($ticketIds);
            $change->audit('change.tickets_linked', ['tickets' => $ticketIds, 'actor' => $user->id]);
        }
        foreach ((array) ($data['asset_ids'] ?? []) as $assetId) {
            $asset = Asset::query()->find((int) $assetId);
            if ($asset !== null) {
                $this->changes->attachAsset($change, $asset, $user);
            }
        }

        return redirect()->route('servicedesk.changes.show', $change)
            ->with('success', __('Change angelegt.'));
    }

    /** Umsetzung starten — optional mit Verfahrensvorlage (ProcedureRun). */
    public function implement(Request $request, Change $change): RedirectResponse {
        Gate::authorize('update', $change);

        $data = $request->validate(['procedure_template_id' => ['nullable', 'string']]);
        $procedureTemplateId = Sqid::decode(ProcedureTemplate::class, $data['procedure_template_id'] ?? null);

        try {
            $this->changes->implement($change, $this->actor(), $procedureTemplateId);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('servicedesk.changes.show', $change)
            ->with('success', __('Umsetzung gestartet.'));
    }

    /** Abschluss-Modal (Outcome + PIR). */
    public function completeForm(Change $change): View {
        Gate::authorize('update', $change);

        return view('helpdesk.changes._complete_dialog', [
            'change' => $change,
            'outcomeLabels' => self::outcomeLabels(),
        ]);
    }

    /** Abschluss — PIR-Zwang bei emergency erzwingt der Service. */
    public function complete(Request $request, Change $change): RedirectResponse {
        Gate::authorize('update', $change);

        $data = $request->validate([
            'outcome' => ['required', 'string', 'in:' . implode(',', Change::OUTCOMES)],
            'pir_notes' => ['nullable', 'string', 'max:20000'],
        ]);

        try {
            $this->changes->complete($change, $this->actor(), $data['outcome'], $data['pir_notes'] ?? null);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->withErrors(['pir_notes' => $e->getMessage()]);
        }

        return redirect()->route('servicedesk.changes.show', $change)
            ->with('success', __('Change abgeschlossen.'));
    }

    /** Asset/CI verknüpfen — Tenant-Grenze doppelt (Rule + Service). */
    public function storeAsset(Request $request, Change $change): RedirectResponse {
        Gate::authorize('update', $change);

        $data = $request->validate(['asset_id' => ['required', 'string']]);
        $assetId = Sqid::decode(Asset::class, $data['asset_id']);
        $asset = $assetId !== null ? Asset::query()->find($assetId) : null;
        if ($asset === null) {
            throw ValidationException::withMessages([
                'asset_id' => (string) __('Bitte ein Asset der eigenen Organisation wählen.'),
            ]);
        }

        $this->changes->attachAsset($change, $asset, $this->actor());

        return redirect()->route('servicedesk.changes.show', $change)
            ->with('success', __('Asset verknüpft.'));
    }

    public function destroyAsset(Change $change, Asset $asset): RedirectResponse {
        Gate::authorize('update', $change);

        $this->changes->detachAsset($change, $asset, $this->actor());

        return redirect()->route('servicedesk.changes.show', $change)
            ->with('success', __('Asset-Verknüpfung entfernt.'));
    }

    /** @return array<string, string> */
    public static function typeLabels(): array {
        return [
            'standard' => (string) __('Standard'),
            'normal' => (string) __('Normal'),
            'emergency' => (string) __('Emergency'),
        ];
    }

    /** @return array<string, string> Labels je Change-Status (Strings, kein Enum). */
    public static function statusLabels(): array {
        return [
            'draft' => (string) __('Entwurf'),
            'pending_approval' => (string) __('Wartet auf Freigabe'),
            'approved' => (string) __('Genehmigt'),
            'implementing' => (string) __('In Umsetzung'),
            'done' => (string) __('Abgeschlossen'),
            'cancelled' => (string) __('Abgebrochen'),
        ];
    }

    /** @return array<string, string> */
    public static function outcomeLabels(): array {
        return [
            'successful' => (string) __('Erfolgreich'),
            'successful_with_issues' => (string) __('Erfolgreich mit Einschränkungen'),
            'failed' => (string) __('Fehlgeschlagen'),
            'rolled_back' => (string) __('Zurückgerollt'),
            'cancelled' => (string) __('Abgebrochen'),
        ];
    }

    /** @return array<string, mixed> Auswahllisten für das Anlage-Modal. */
    private function formOptions(): array {
        return [
            // Nur FREIGEGEBENE Vorlagen anbieten — der Service erzwingt es.
            'templates' => ChangeTemplate::query()->where('approved', true)->orderBy('name')->get(['id', 'name', 'version']),
            'problems' => Problem::query()->whereNotIn('status', ['closed'])->orderByDesc('id')->limit(100)->get(['id', 'title']),
            'tickets' => ServiceTicket::query()->orderByDesc('reported_at')->limit(100)->get(['id', 'ticket_no', 'title']),
            'assets' => Asset::query()->orderBy('name')->limit(200)->get(['id', 'asset_no', 'name']),
            'orgUsers' => User::query()
                ->where('organization_id', (int) $this->actor()->organization_id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'roles' => array_values(array_filter(
                \App\Enums\User\UserRole::cases(),
                static fn(\App\Enums\User\UserRole $r): bool => $r !== \App\Enums\User\UserRole::Kunde,
            )),
        ];
    }

    /**
     * Strukturierte Step-Liste → Kette in der von
     * {@see \App\Services\ServiceTicket\ApprovalService::createChain}
     * erwarteten Form (Muster ServiceCatalogController::buildApprovalChain;
     * user-Sqids werden hier je Zielklasse dekodiert und org-gescopt geprüft).
     *
     * @param array<int, array<string, mixed>> $steps
     * @return array<int, array<string, mixed>>
     */
    private function buildApprovalChain(array $steps): array {
        $chain = [];
        foreach (array_values($steps) as $index => $step) {
            $type = (string) ($step['type'] ?? '');
            if ($type === 'user') {
                $userId = Sqid::decode(User::class, (string) ($step['user'] ?? ''));
                $exists = $userId !== null && User::query()
                    ->whereKey($userId)
                    ->where('organization_id', (int) $this->actor()->organization_id)
                    ->exists();
                if (! $exists) {
                    throw ValidationException::withMessages([
                        "approval_steps.{$index}.user" => (string) __('Bitte einen Benutzer der eigenen Organisation wählen.'),
                    ]);
                }
                $chain[] = ['approver' => ['type' => 'user', 'value' => (int) $userId]];
            } else {
                $role = (string) ($step['role'] ?? '');
                if ($role === '') {
                    throw ValidationException::withMessages([
                        "approval_steps.{$index}.role" => (string) __('Bitte eine Rolle wählen.'),
                    ]);
                }
                $chain[] = ['approver' => ['type' => 'role', 'value' => $role]];
            }
        }

        return $chain;
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
