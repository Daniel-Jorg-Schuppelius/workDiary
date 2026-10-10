<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Enums\Sales\CommissionStatus;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\AssignCommissionRequest;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Sales\{CommissionAgent, CommissionRule, InvoiceCommission};
use App\Services\Sales\Commission\CommissionDocuments;
use App\Services\Sales\{CommissionAccrualService, CommissionRuleResolver};
use App\Services\Sales\Contracts\CommissionDocument;
use App\Support\{MorphMap, Sqid};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Provisionszeilen je Beleg (Feature 146, MVP-729) und die manuelle Zuordnung
 * Beleg → Vertriebsperson. Beleg ist die lokale Rechnung oder eine gespiegelte
 * Rechnung des Buchhaltungsprogramms (MVP-1119); beide laufen über denselben
 * Dialog.
 *
 * Die Liste zeigt zusaetzlich die **bezahlten Rechnungen ohne Provision** —
 * ohne sie bliebe der haeufigste Fehlerfall (niemand zustaendig, weil der
 * Kunde nicht aus der Lead-Pipeline kam) unsichtbar.
 */
class CommissionController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly CommissionAccrualService $accrual,
        private readonly CommissionRuleResolver $resolver,
        private readonly CommissionDocuments $documents,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', CommissionRule::class);

        $status = CommissionStatus::tryFrom((string) $request->query('status', ''));

        $commissions = InvoiceCommission::query()
            ->with(['user:id,name', 'agent:id,name,company', 'customer:id,name,company', 'rule:id,name', 'settlementRun:id,period'])
            ->when($status !== null, fn ($q) => $q->where('status', $status?->value))
            ->orderByDesc('earned_on')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('sales.commissions.index', [
            'commissions' => $commissions,
            'assignUrls' => collect($commissions->items())
                ->filter(static fn (InvoiceCommission $row): bool => $row->document_type !== null && $row->document_id !== null)
                ->mapWithKeys(fn (InvoiceCommission $row): array => [$row->id => $this->assignUrl((string) $row->document_type, (int) $row->document_id)]),
            'status' => $status,
            'unassigned' => $this->unassignedRows(),
            'canManage' => Gate::allows('create', CommissionRule::class),
        ]);
    }

    /** Zuordnungs-Dialog einer lokalen Rechnung. */
    public function assignForm(Invoice $invoice): View {
        return $this->assignDialog($this->documents->of($invoice), route('commissions.assign', $invoice));
    }

    /** Zuordnungs-Dialog eines Belegs jeder Quelle (MVP-1119). */
    public function assignDocumentForm(string $type, string $document): View {
        return $this->assignDialog($this->document($type, $document), route('commissions.document.assign', ['type' => $type, 'document' => $document]));
    }

    /**
     * Manuelle Zuordnung setzen oder loesen. Bei einer bereits bezahlten
     * Rechnung wird die Provision unmittelbar neu berechnet (offene Zeilen
     * werden zurueckgerechnet, festgeschriebene bleiben stehen).
     */
    public function assign(AssignCommissionRequest $request, Invoice $invoice): RedirectResponse {
        return $this->assignTo($request, $this->documents->of($invoice));
    }

    public function assignDocument(AssignCommissionRequest $request, string $type, string $document): RedirectResponse {
        return $this->assignTo($request, $this->document($type, $document));
    }

    private function assignDialog(CommissionDocument $document, string $action): View {
        Gate::authorize('create', CommissionRule::class);

        return view('sales.commissions._assign_dialog', [
            'number' => $document->number(),
            'action' => $action,
            'currentUser' => $document->salesUserId() !== null ? Sqid::encode(User::class, $document->salesUserId()) : null,
            'currentAgent' => $document->salesAgentId() !== null ? Sqid::encode(CommissionAgent::class, $document->salesAgentId()) : null,
            'users' => User::query()
                ->where('organization_id', $this->currentOrganization()->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'agents' => CommissionAgent::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'company']),
            'suggestion' => $this->resolver->assignmentFor($document),
        ]);
    }

    private function assignTo(AssignCommissionRequest $request, CommissionDocument $document): RedirectResponse {
        Gate::authorize('create', CommissionRule::class);

        $data = $request->validated();
        $this->accrual->assign(
            $document,
            isset($data['user_id']) ? User::query()->findOrFail((int) $data['user_id']) : null,
            isset($data['commission_agent_id']) ? CommissionAgent::query()->findOrFail((int) $data['commission_agent_id']) : null,
        );

        return back()->with('success', __('commission.flash.assigned'));
    }

    private function document(string $type, string $token): CommissionDocument {
        $class = MorphMap::classFor($type);
        $id = $class !== null ? Sqid::decode($class, $token) : null;
        $document = $id !== null ? $this->documents->find($type, (int) $this->currentOrganization()->id, $id) : null;
        abort_if($document === null, 404);

        return $document;
    }

    private function assignUrl(string $type, int $id): ?string {
        $class = MorphMap::classFor($type);

        return $class !== null ? route('commissions.document.assign.form', ['type' => $type, 'document' => Sqid::encode($class, $id)]) : null;
    }

    /**
     * Bezahlte Belege ohne Provisionszeile — der Arbeitsvorrat der manuellen
     * Zuordnung.
     *
     * @return list<array{number: string, customer: ?string, paidOn: ?\Illuminate\Support\Carbon, assignUrl: ?string}>
     */
    private function unassignedRows(): array {
        $documents = $this->documents->unassignedPaid((int) $this->currentOrganization()->id, 50);
        $names = Customer::query()
            ->whereIn('id', array_filter(array_map(static fn (CommissionDocument $document): ?int => $document->customerId(), $documents)))
            ->pluck('name', 'id');

        return array_map(fn (CommissionDocument $document): array => [
            'number' => $document->number(),
            'customer' => $document->customerId() !== null ? $names->get($document->customerId()) : null,
            'paidOn' => $document->paidOn(),
            'assignUrl' => $this->assignUrl($document->documentType(), $document->documentId()),
        ], $documents);
    }
}
