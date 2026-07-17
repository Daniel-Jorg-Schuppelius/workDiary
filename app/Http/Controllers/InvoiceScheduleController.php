<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceScheduleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission;
use App\Models\Contract\Contract;
use App\Models\{Customer, InvoiceSchedule, InvoiceScheduleItem, User};
use App\Services\Finance\BillingModeResolver;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/** Abrechnungspläne für wiederkehrende Rechnungen (MVP-415). */
class InvoiceScheduleController extends Controller {
    public function index(BillingModeResolver $billingMode): View {
        Gate::authorize(Permission::InvoiceViewAny->value);

        $schedules = InvoiceSchedule::query()
            ->with(['customer:id,name,company', 'contract:id,title,number'])
            ->orderBy('next_run_on')
            ->paginate(25);

        $blocked = [];
        foreach ($schedules as $schedule) {
            if ($schedule->customer !== null) {
                $blocked[$schedule->id] = $billingMode->effectiveFor($schedule->customer)->isExternal();
            }
        }

        return view('invoice-schedules.index', [
            'schedules' => $schedules,
            'blocked' => $blocked,
        ]);
    }

    public function show(InvoiceSchedule $invoiceSchedule, BillingModeResolver $billingMode): View {
        Gate::authorize(Permission::InvoiceViewAny->value);

        $invoiceSchedule->load(['customer', 'contract', 'items', 'runs.invoice:id,number,status,total,currency']);

        return view('invoice-schedules.show', [
            'schedule' => $invoiceSchedule,
            'isBlocked' => $invoiceSchedule->customer !== null
                && $billingMode->effectiveFor($invoiceSchedule->customer)->isExternal(),
        ]);
    }

    public function create(): View {
        Gate::authorize(Permission::InvoiceCreate->value);

        return view('invoice-schedules._form_dialog', [
            'schedule' => null,
            'isEdit' => false,
            'customers' => $this->customerOptions(),
            'contracts' => $this->contractOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(Permission::InvoiceCreate->value);

        /** @var User $auth */
        $auth = Auth::user();
        $data = $this->validateSchedule($request);

        $schedule = InvoiceSchedule::create([
            ...$data,
            'organization_id' => $auth->organization_id,
            'status' => InvoiceSchedule::STATUS_ACTIVE,
            'created_by' => $auth->id,
        ]);

        return redirect()->route('invoice-schedules.show', $schedule)
            ->with('status', __('Abrechnungsplan angelegt — jetzt Positionen hinzufügen.'));
    }

    public function edit(InvoiceSchedule $invoiceSchedule): View {
        Gate::authorize(Permission::InvoiceUpdate->value);

        return view('invoice-schedules._form_dialog', [
            'schedule' => $invoiceSchedule,
            'isEdit' => true,
            'customers' => $this->customerOptions(),
            'contracts' => $this->contractOptions(),
        ]);
    }

    public function update(Request $request, InvoiceSchedule $invoiceSchedule): RedirectResponse {
        Gate::authorize(Permission::InvoiceUpdate->value);

        $invoiceSchedule->update($this->validateSchedule($request, $invoiceSchedule));

        return redirect()->route('invoice-schedules.show', $invoiceSchedule)
            ->with('status', __('Abrechnungsplan aktualisiert.'));
    }

    public function destroy(InvoiceSchedule $invoiceSchedule): RedirectResponse {
        Gate::authorize(Permission::InvoiceUpdate->value);

        $invoiceSchedule->delete();

        return redirect()->route('invoice-schedules.index')
            ->with('status', __('Abrechnungsplan gelöscht — bereits erzeugte Entwürfe bleiben erhalten.'));
    }

    /** Aussetzen/Fortsetzen — Beenden ist endgültig. */
    public function setStatus(Request $request, InvoiceSchedule $invoiceSchedule): RedirectResponse {
        Gate::authorize(Permission::InvoiceUpdate->value);

        $data = $request->validate([
            'status' => ['required', 'in:active,paused,ended'],
        ]);

        if ($invoiceSchedule->status === InvoiceSchedule::STATUS_ENDED) {
            return redirect()->route('invoice-schedules.show', $invoiceSchedule)
                ->with('error', __('Ein beendeter Plan kann nicht wieder aktiviert werden.'));
        }

        $invoiceSchedule->update(['status' => $data['status']]);

        return redirect()->route('invoice-schedules.show', $invoiceSchedule)
            ->with('status', __('Status aktualisiert.'));
    }

    // ── Positionsvorlagen ────────────────────────────────────────────────────

    public function itemForm(InvoiceSchedule $invoiceSchedule, ?InvoiceScheduleItem $item = null): View {
        Gate::authorize(Permission::InvoiceUpdate->value);
        $item ??= new InvoiceScheduleItem();

        return view('invoice-schedules._item_form_dialog', [
            'schedule' => $invoiceSchedule,
            'item' => $item,
        ]);
    }

    public function addItem(Request $request, InvoiceSchedule $invoiceSchedule): RedirectResponse {
        Gate::authorize(Permission::InvoiceUpdate->value);
        $data = $this->validateItem($request);

        $invoiceSchedule->items()->create([
            ...$data,
            'organization_id' => $invoiceSchedule->organization_id,
            'position' => $data['position'] ?? ((int) $invoiceSchedule->items()->max('position') + 1),
        ]);

        return redirect()->route('invoice-schedules.show', $invoiceSchedule)->with('status', __('Position hinzugefügt.'));
    }

    public function updateItem(Request $request, InvoiceSchedule $invoiceSchedule, InvoiceScheduleItem $item): RedirectResponse {
        Gate::authorize(Permission::InvoiceUpdate->value);
        abort_unless($item->invoice_schedule_id === $invoiceSchedule->id, 404);

        $item->update($this->validateItem($request));

        return redirect()->route('invoice-schedules.show', $invoiceSchedule)->with('status', __('Position aktualisiert.'));
    }

    public function removeItem(InvoiceSchedule $invoiceSchedule, InvoiceScheduleItem $item): RedirectResponse {
        Gate::authorize(Permission::InvoiceUpdate->value);
        abort_unless($item->invoice_schedule_id === $invoiceSchedule->id, 404);

        $item->delete();

        return redirect()->route('invoice-schedules.show', $invoiceSchedule)->with('status', __('Position entfernt.'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validateSchedule(Request $request, ?InvoiceSchedule $existing = null): array {
        $request->merge([
            'customer_id' => Sqid::decodeOrNumeric(Customer::class, $request->input('customer_id')),
            'contract_id' => Sqid::decodeOrNumeric(Contract::class, $request->input('contract_id')),
        ]);

        $data = $request->validate([
            'customer_id' => ['required', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'contract_id' => ['nullable', new \App\Rules\ExistsInCurrentOrganization('contracts')],
            'title' => ['required', 'string', 'max:180'],
            'interval_unit' => ['required', 'in:' . implode(',', InvoiceSchedule::UNITS)],
            'interval_count' => ['required', 'integer', 'min:1', 'max:12'],
            'billing_period_mode' => ['required', 'in:previous,current'],
            'next_run_on' => ['required', 'date'],
            'end_on' => ['nullable', 'date', 'after:next_run_on'],
        ]);

        // Beim Bearbeiten bleibt der Kunde fix — sonst wechseln Läufe still den Mandats-Kontext.
        if ($existing !== null) {
            unset($data['customer_id']);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function validateItem(Request $request): array {
        return $request->validate([
            'description' => ['required', 'string', 'max:1000'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:9999999'],
            'unit' => ['nullable', 'string', 'max:32'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'prohibits:discount_amount'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'tax_category' => ['nullable', 'in:S,AE,Z,E,G,K,O'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, Customer> */
    private function customerOptions() {
        return Customer::query()->orderBy('name')->get(['id', 'name', 'company']);
    }

    /** @return \Illuminate\Support\Collection<int, Contract> */
    private function contractOptions() {
        return Contract::query()->orderBy('title')->get(['id', 'title', 'number']);
    }
}
