<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuaranteeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Guarantee\{GuaranteeDirection, GuaranteeStatus};
use App\Http\Requests\SaveGuaranteeRequest;
use App\Models\{Customer, Project, Supplier, User};
use App\Models\Guarantee\Guarantee;
use App\Models\Invoicing\InvoiceRetention;
use App\Services\Guarantee\GuaranteeService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

/**
 * Bürgschaftsregister (Feature 114, MVP-603).
 *
 * Autorisierung über das Abrechnungsrecht: Bürgschaften sind Sicherheiten
 * für Geld, keine Projektdaten. Sie tauchen deshalb unter „Abrechnung &
 * Finanzen" auf und nicht am Projekt.
 */
class GuaranteeController extends Controller {
    public function __construct(private readonly GuaranteeService $guarantees) {}

    public function index(Request $request): View {
        $this->authorizeBilling();

        $filters = [
            'direction' => (string) $request->query('direction', ''),
            'status' => (string) $request->query('status', GuaranteeStatus::Active->value),
        ];

        $query = Guarantee::query()->with(['customer:id,name,company', 'supplier:id,name,company', 'issuerSupplier:id,name,company', 'project:id,name', 'responsible:id,name']);
        if (GuaranteeDirection::tryFrom($filters['direction']) !== null) {
            $query->where('direction', $filters['direction']);
        }
        if (GuaranteeStatus::tryFrom($filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }

        $guarantees = $query->orderByRaw('expires_on IS NULL, expires_on')->paginate(50)->withQueryString();

        // Zähler über den GESAMTEN Bestand, nicht die gefilterte Sicht: Sie
        // beantworten „gibt es irgendwo ein Problem?" — ein Filter darf diese
        // Frage nicht verdecken.
        $active = Guarantee::query()->where('status', GuaranteeStatus::Active->value);

        return view('guarantees.index', [
            'guarantees' => $guarantees,
            'filters' => $filters,
            'activeIssued' => (clone $active)->where('direction', GuaranteeDirection::Issued->value)->count(),
            'activeReceived' => (clone $active)->where('direction', GuaranteeDirection::Received->value)->count(),
            'expiringSoon' => (clone $active)->whereNotNull('expires_on')
                ->whereDate('expires_on', '<=', now()->addDays(90)->toDateString())->count(),
            'returnDue' => (clone $active)->whereHas('retention', fn ($q) => $q->where('status', \App\Enums\Invoicing\RetentionStatus::Released->value))->count(),
        ]);
    }

    public function form(Request $request, ?Guarantee $guarantee = null): View {
        $this->authorizeBilling();
        $organizationId = $request->user()?->organization_id;

        return view('guarantees._form_dialog', [
            'guarantee' => $guarantee,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'company']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name', 'company']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            // Nutzerliste ausdrücklich org-gefiltert: User trägt keinen
            // globalen Org-Scope (Login/2FA laufen vor dem Org-Kontext).
            'users' => User::query()->where('organization_id', $organizationId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SaveGuaranteeRequest $request): RedirectResponse {
        $this->authorizeBilling();

        $guarantee = Guarantee::query()->create($request->validated() + [
            'organization_id' => $request->user()?->organization_id,
            'created_by' => $request->user()?->id,
        ]);
        $guarantee->audit('guarantee.created');

        return redirect()->route('guarantees.index')->with('status', __('guarantee.created'));
    }

    public function update(SaveGuaranteeRequest $request, Guarantee $guarantee): RedirectResponse {
        $this->authorizeBilling();
        $guarantee->update($request->validated());

        return redirect()->route('guarantees.index')->with('status', __('guarantee.updated'));
    }

    public function returned(Request $request, Guarantee $guarantee): RedirectResponse {
        $this->authorizeBilling();
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        try {
            $this->guarantees->markReturned($guarantee, $request->user(), $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('guarantee.returned'));
    }

    public function drawn(Request $request, Guarantee $guarantee): RedirectResponse {
        $this->authorizeBilling();
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        try {
            $this->guarantees->markDrawn($guarantee, $request->user(), $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('guarantee.drawn'));
    }

    /** Bürgschaft löst einen Sicherheitseinbehalt ab (MVP-602 ↔ MVP-603). */
    public function secure(Request $request, Guarantee $guarantee): RedirectResponse {
        $this->authorizeBilling();
        $data = $request->validate(['retention' => ['required', 'string']]);

        $retention = InvoiceRetention::query()
            ->whereKey(Sqid::decodeOrNumeric(InvoiceRetention::class, (string) $data['retention']))
            ->first();
        abort_if($retention === null, 404);

        try {
            $this->guarantees->secureRetention($guarantee, $retention, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('guarantee.secured'));
    }

    private function authorizeBilling(): void {
        abort_unless(Auth::user()?->canManageBilling() ?? false, 403);
    }
}
