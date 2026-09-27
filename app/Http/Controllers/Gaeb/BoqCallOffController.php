<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqCallOffController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Gaeb;

use App\Enums\Gaeb\BoqCallOffStatus;
use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Gaeb\{BillOfQuantity, BoqCallOff, BoqItem};
use App\Services\Billing\BillingModeLockedException;
use App\Services\Gaeb\BoqCallOffService;
use App\Support\{ErrorText, Sqid};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Zeitvertragsarbeiten (MVP-931): Rahmen-LV, Abrufe, Abrechnung je Abruf. */
class BoqCallOffController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly BoqCallOffService $callOffs) {}

    public function index(BillOfQuantity $billOfQuantity): View {
        $this->authorizeBill($billOfQuantity, P::ProjectViewAny);

        return view('bill-of-quantities.call-offs.index', [
            'bill' => $billOfQuantity,
            'callOffs' => $billOfQuantity->callOffs()->with(['items.item', 'invoice'])->orderByDesc('number')->get(),
            'remaining' => $this->callOffs->remaining($billOfQuantity),
            'canManage' => Gate::allows(P::ProjectUpdate->value),
            'canInvoice' => Gate::allows(P::ProjectUpdate->value) && Gate::allows(P::InvoiceCreate->value),
        ]);
    }

    public function framework(Request $request, BillOfQuantity $billOfQuantity): RedirectResponse {
        $this->authorizeBill($billOfQuantity, P::ProjectUpdate);
        $billOfQuantity->forceFill(['is_framework' => $request->boolean('is_framework')])->save();

        return back()->with('success', __('gaeb.call_off.flash.framework'));
    }

    public function create(BillOfQuantity $billOfQuantity): View {
        $this->authorizeBill($billOfQuantity, P::ProjectUpdate);

        return view('bill-of-quantities.call-offs._form_dialog', ['bill' => $billOfQuantity, 'remaining' => $this->callOffs->remaining($billOfQuantity)]);
    }

    public function store(Request $request, BillOfQuantity $billOfQuantity): RedirectResponse {
        $this->authorizeBill($billOfQuantity, P::ProjectUpdate);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'ordered_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date', 'after_or_equal:ordered_on'],
            'note' => ['nullable', 'string', 'max:2000'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $quantities = [];
        foreach ((array) $data['quantities'] as $key => $value) {
            $id = Sqid::decode(BoqItem::class, (string) $key);
            if ($id !== null) {
                $quantities[$id] = $value;
            }
        }
        $callOff = $this->callOffs->create($billOfQuantity, $data, $quantities, $this->authUser());

        return redirect()->route('bill-of-quantities.call-offs.index', $billOfQuantity)
            ->with('success', __('gaeb.call_off.flash.created', ['number' => $callOff->number]));
    }

    public function transition(Request $request, BoqCallOff $callOff): RedirectResponse {
        $bill = $callOff->billOfQuantity()->firstOrFail();
        $this->authorizeBill($bill, P::ProjectUpdate);
        $data = $request->validate(['status' => ['required', Rule::enum(BoqCallOffStatus::class)]]);
        $this->callOffs->transition($callOff, BoqCallOffStatus::from($data['status']), $this->authUser());

        return back()->with('success', __('gaeb.call_off.flash.status'));
    }

    public function invoice(BoqCallOff $callOff): RedirectResponse {
        $bill = $callOff->billOfQuantity()->firstOrFail();
        $this->authorizeBill($bill, P::ProjectUpdate);
        Gate::authorize(P::InvoiceCreate->value);
        try {
            $invoice = $this->callOffs->invoice($callOff, $this->authUser());
        } catch (BillingModeLockedException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('invoices.show', $invoice)->with('success', __('gaeb.call_off.flash.invoiced', ['number' => $callOff->number]));
    }

    private function authorizeBill(BillOfQuantity $bill, P $permission): void {
        Gate::authorize($permission->value);
        abort_unless($bill->organization_id === $this->currentOrganization()->id, 404);
    }
}
