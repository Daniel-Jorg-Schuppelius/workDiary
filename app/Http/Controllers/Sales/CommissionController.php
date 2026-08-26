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
use App\Http\Requests\AssignCommissionRequest;
use App\Models\{Invoice, User};
use App\Models\Sales\{CommissionRule, InvoiceCommission};
use App\Services\Sales\{CommissionAccrualService, CommissionRuleResolver};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Provisionszeilen je Beleg (Feature 146, MVP-729) und die manuelle Zuordnung
 * Beleg → Vertriebsperson.
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
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', CommissionRule::class);

        $status = CommissionStatus::tryFrom((string) $request->query('status', ''));

        $commissions = InvoiceCommission::query()
            ->with(['user:id,name', 'invoice:id,number,customer_id,status', 'invoice.customer:id,name,company', 'rule:id,name', 'settlementRun:id,period'])
            ->when($status !== null, fn ($q) => $q->where('status', $status?->value))
            ->orderByDesc('earned_on')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('sales.commissions.index', [
            'commissions' => $commissions,
            'status' => $status,
            'unassigned' => $this->unassignedPaidInvoices(),
            'canManage' => Gate::allows('create', CommissionRule::class),
        ]);
    }

    /** Zuordnungs-Dialog eines einzelnen Belegs. */
    public function assignForm(Invoice $invoice): View {
        Gate::authorize('create', CommissionRule::class);

        return view('sales.commissions._assign_dialog', [
            'invoice' => $invoice,
            'users' => User::query()
                ->where('organization_id', $this->currentOrganization()->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'suggestion' => $this->resolver->assignmentFor($invoice),
        ]);
    }

    /**
     * Manuelle Zuordnung setzen oder loesen. Bei einer bereits bezahlten
     * Rechnung wird die Provision unmittelbar neu berechnet (offene Zeilen
     * werden zurueckgerechnet, festgeschriebene bleiben stehen).
     */
    public function assign(AssignCommissionRequest $request, Invoice $invoice): RedirectResponse {
        Gate::authorize('create', CommissionRule::class);

        $userId = $request->validated()['user_id'] ?? null;
        $this->accrual->assign($invoice, $userId === null ? null : User::query()->findOrFail((int) $userId));

        return back()->with('success', __('commission.flash.assigned'));
    }

    /**
     * Bezahlte Rechnungen ohne Provisionszeile — der Arbeitsvorrat der
     * manuellen Zuordnung.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Invoice>
     */
    private function unassignedPaidInvoices(): \Illuminate\Database\Eloquent\Collection {
        return Invoice::query()
            ->where('status', Invoice::STATUS_PAID)
            ->whereNotIn('type', [Invoice::TYPE_CREDIT_NOTE, Invoice::TYPE_CANCELLATION, Invoice::TYPE_PROFORMA])
            ->whereDoesntHave('commissions')
            ->with(['customer:id,name,company'])
            ->orderByDesc('paid_on')
            ->limit(50)
            ->get();
    }
}
