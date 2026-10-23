<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SelfBillingCreditController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Invoicing;

use App\Enums\Invoicing\SelfBillingStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Invoicing\{SelfBillingCredit, SelfBillingCreditLine};
use App\Models\Supplier\Supplier;
use App\Services\Invoicing\EInvoice\SelfBillingXmlGenerator;
use App\Services\Invoicing\{SelfBillingPdfRenderer, SelfBillingService};
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Gutschriften im Gutschriftsverfahren (MVP-1280): Entwurf, Positionen, Ausstellen, Storno, PDF und XRechnung. */
class SelfBillingCreditController extends Controller {
    public function __construct(private readonly SelfBillingService $service) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::InvoiceViewAny->value);
        $status = SelfBillingStatus::tryFrom($request->string('status')->toString());

        return view('invoicing.self-billing.index', [
            'credits' => SelfBillingCredit::query()
                ->with('supplier:id,name,company')
                ->when($status !== null, static fn (Builder $q) => $q->where('status', $status?->value))
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString(),
            'status' => $status,
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize(Permission::InvoiceCreate->value);
        $supplierId = Sqid::decode(Supplier::class, $request->string('supplier')->toString());

        return view('invoicing.self-billing._form_dialog', [
            'suppliers' => Supplier::query()->whereNotNull('self_billing_agreed_on')->orderBy('name')->get(['id', 'name', 'company']),
            'selected' => $supplierId,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(Permission::InvoiceCreate->value);
        $data = $request->validate([
            'supplier_id' => ['required', 'string', 'max:64'],
            'service_from' => ['required', 'date'],
            'service_until' => ['required', 'date', 'after_or_equal:service_from'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $supplierId = Sqid::decode(Supplier::class, (string) $data['supplier_id']);
        $supplier = $supplierId !== null ? Supplier::query()->find($supplierId) : null;
        if ($supplier === null) {
            throw ValidationException::withMessages(['supplier_id' => (string) __('invoicing.self_billing.errors.supplier')]);
        }
        $credit = $this->service->createDraft($supplier, $this->authUser(), $data);

        return redirect()->route('invoices.self-billing.show', $credit)
            ->with('success', __('invoicing.self_billing.flash.created'))
            ->with('open_dialog', route('invoices.self-billing.lines.create', $credit));
    }

    public function show(SelfBillingCredit $selfBillingCredit, SelfBillingXmlGenerator $xml): View {
        Gate::authorize(Permission::InvoiceViewAny->value);
        $selfBillingCredit->load(['supplier', 'lines', 'incoming', 'issuer']);

        return view('invoicing.self-billing.show', [
            'credit' => $selfBillingCredit,
            'totals' => $selfBillingCredit->documentTotals(),
            'blockers' => $selfBillingCredit->isDraft() ? $xml->preflight($selfBillingCredit) : [],
        ]);
    }

    public function createLine(SelfBillingCredit $selfBillingCredit): View {
        Gate::authorize(Permission::InvoiceCreate->value);

        return view('invoicing.self-billing._line_dialog', ['credit' => $selfBillingCredit]);
    }

    public function storeLine(Request $request, SelfBillingCredit $selfBillingCredit): RedirectResponse {
        Gate::authorize(Permission::InvoiceCreate->value);
        $data = $request->validate([
            'description' => ['required', 'string', 'max:500'],
            'quantity' => ['required', 'numeric', 'min:0.0001', 'max:9999999'],
            'unit' => ['nullable', 'string', 'max:20'],
            'unit_price' => ['required', 'numeric', 'min:-9999999', 'max:9999999'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:99'],
        ]);
        $this->service->addLine($selfBillingCredit, $data);

        return redirect()->route('invoices.self-billing.show', $selfBillingCredit)->with('success', __('invoicing.self_billing.flash.line_added'));
    }

    public function destroyLine(SelfBillingCredit $selfBillingCredit, SelfBillingCreditLine $line): RedirectResponse {
        Gate::authorize(Permission::InvoiceCreate->value);
        $this->service->removeLine($selfBillingCredit, $line);

        return redirect()->route('invoices.self-billing.show', $selfBillingCredit)->with('success', __('invoicing.self_billing.flash.line_removed'));
    }

    public function issue(SelfBillingCredit $selfBillingCredit): RedirectResponse {
        Gate::authorize(Permission::InvoiceIssue->value);
        $credit = $this->service->issue($selfBillingCredit, $this->authUser());

        return redirect()->route('invoices.self-billing.show', $credit)->with('success', __('invoicing.self_billing.flash.issued', ['number' => $credit->number]));
    }

    public function cancelDialog(SelfBillingCredit $selfBillingCredit): View {
        Gate::authorize(Permission::InvoiceIssue->value);

        return view('invoicing.self-billing._cancel_dialog', ['credit' => $selfBillingCredit]);
    }

    public function cancel(Request $request, SelfBillingCredit $selfBillingCredit): RedirectResponse {
        Gate::authorize(Permission::InvoiceIssue->value);
        $data = $request->validate(['reason' => ['required', 'string', 'min:2', 'max:255']]);
        $this->service->cancel($selfBillingCredit, $this->authUser(), $data['reason']);

        return redirect()->route('invoices.self-billing.show', $selfBillingCredit)->with('success', __('invoicing.self_billing.flash.cancelled'));
    }

    public function pdf(SelfBillingCredit $selfBillingCredit, SelfBillingPdfRenderer $renderer): Response {
        Gate::authorize(Permission::InvoiceViewAny->value);

        return response($renderer->output($selfBillingCredit), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $renderer->filename($selfBillingCredit) . '"',
        ]);
    }

    public function xml(SelfBillingCredit $selfBillingCredit, SelfBillingXmlGenerator $xml): Response {
        Gate::authorize(Permission::InvoiceViewAny->value);
        abort_if($selfBillingCredit->isDraft(), 404);

        return response($xml->generate($selfBillingCredit), 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $selfBillingCredit->number . '.xml"',
        ]);
    }
}
