<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CashRegisterController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission;
use App\Models\{CashEntry, CashRegister, Invoice, User};
use App\Services\Finance\CashBookService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use InvalidArgumentException;

/** Kassenbuch (MVP-414): Kassen, append-only Einträge, Storno, Tagesabschluss. */
class CashRegisterController extends Controller {
    public function __construct(private readonly CashBookService $cashBook) {}

    public function index(): View {
        Gate::authorize(Permission::CashView->value);

        $registers = CashRegister::query()->orderBy('name')->get();
        $balances = [];
        $lastClosings = [];
        foreach ($registers as $register) {
            $balances[$register->id] = $this->cashBook->balance($register);
            $lastClosings[$register->id] = $register->lastClosingDate();
        }

        return view('cash-registers.index', [
            'registers' => $registers,
            'balances' => $balances,
            'lastClosings' => $lastClosings,
        ]);
    }

    public function show(Request $request, CashRegister $cashRegister): View {
        Gate::authorize(Permission::CashView->value);

        $entries = $cashRegister->entries()
            ->with(['invoice:id,number', 'reversalOf:id,seq_no'])
            ->orderByDesc('seq_no')
            ->paginate(50);

        $reversedIds = CashEntry::query()
            ->where('cash_register_id', $cashRegister->id)
            ->whereNotNull('reversal_of_id')
            ->pluck('reversal_of_id')
            ->all();

        return view('cash-registers.show', [
            'register' => $cashRegister,
            'entries' => $entries,
            'reversedIds' => array_flip(array_map(intval(...), $reversedIds)),
            'balance' => $this->cashBook->balance($cashRegister),
            'lastClosing' => $cashRegister->lastClosingDate(),
            'closings' => $cashRegister->closings()->limit(10)->get(),
        ]);
    }

    public function create(): View {
        Gate::authorize(Permission::CashManage->value);

        return view('cash-registers._register_form_dialog', ['register' => null]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(Permission::CashManage->value);

        /** @var User $auth */
        $auth = Auth::user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'opened_on' => ['required', 'date'],
        ]);

        $register = CashRegister::create([
            ...$data,
            'organization_id' => $auth->organization_id,
            'currency' => 'EUR',
            'active' => true,
        ]);

        return redirect()->route('cash-registers.show', $register)->with('status', __('Kasse angelegt.'));
    }

    // ── Buchungen ────────────────────────────────────────────────────────────

    public function entryForm(CashRegister $cashRegister): View {
        Gate::authorize(Permission::CashManage->value);

        return view('cash-registers._entry_form_dialog', [
            'register' => $cashRegister,
            'openInvoices' => Invoice::query()
                ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID])
                ->orderByDesc('issued_on')
                ->limit(100)
                ->get(['id', 'number', 'total']),
        ]);
    }

    public function storeEntry(Request $request, CashRegister $cashRegister): RedirectResponse {
        Gate::authorize(Permission::CashManage->value);

        /** @var User $auth */
        $auth = Auth::user();
        $request->merge(['invoice_id' => Sqid::decodeOrNumeric(Invoice::class, $request->input('invoice_id'))]);
        $data = $request->validate([
            'booked_on' => ['required', 'date'],
            'direction' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'purpose' => ['required', 'string', 'max:500'],
            'counterparty' => ['nullable', 'string', 'max:180'],
            'invoice_id' => ['nullable', new \App\Rules\ExistsInCurrentOrganization('invoices')],
        ]);

        try {
            $this->cashBook->record($cashRegister, [
                'booked_on' => (string) $data['booked_on'],
                'direction' => (string) $data['direction'],
                'amount' => $data['amount'],
                'tax_rate' => $data['tax_rate'] ?? null,
                'purpose' => (string) $data['purpose'],
                'counterparty' => $data['counterparty'] ?? null,
                'invoice_id' => isset($data['invoice_id']) ? (int) $data['invoice_id'] : null,
                'created_by' => (int) $auth->id,
            ]);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('cash-registers.show', $cashRegister)->with('error', $e->getMessage());
        }

        return redirect()->route('cash-registers.show', $cashRegister)->with('status', __('Buchung erfasst.'));
    }

    public function reverseForm(CashRegister $cashRegister, CashEntry $entry): View {
        Gate::authorize(Permission::CashManage->value);
        abort_unless($entry->cash_register_id === $cashRegister->id, 404);

        return view('cash-registers._reverse_dialog', ['register' => $cashRegister, 'entry' => $entry]);
    }

    public function reverseEntry(Request $request, CashRegister $cashRegister, CashEntry $entry): RedirectResponse {
        Gate::authorize(Permission::CashManage->value);
        abort_unless($entry->cash_register_id === $cashRegister->id, 404);

        /** @var User $auth */
        $auth = Auth::user();
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:400'],
        ]);

        try {
            $this->cashBook->reverse($entry, $data['reason'], (int) $auth->id);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('cash-registers.show', $cashRegister)->with('error', $e->getMessage());
        }

        return redirect()->route('cash-registers.show', $cashRegister)->with('status', __('Storno-Gegenbuchung erfasst.'));
    }

    // ── Tagesabschluss ───────────────────────────────────────────────────────

    public function closeForm(CashRegister $cashRegister): View {
        Gate::authorize(Permission::CashManage->value);

        return view('cash-registers._close_dialog', [
            'register' => $cashRegister,
            'expected' => $this->cashBook->balanceAsOf($cashRegister, now()),
        ]);
    }

    public function closeDay(Request $request, CashRegister $cashRegister): RedirectResponse {
        Gate::authorize(Permission::CashManage->value);

        /** @var User $auth */
        $auth = Auth::user();
        $data = $request->validate([
            'closing_date' => ['required', 'date', 'before_or_equal:today'],
            'counted_balance' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $closing = $this->cashBook->closeDay(
                $cashRegister,
                \Carbon\Carbon::parse($data['closing_date']),
                (float) $data['counted_balance'],
                $data['note'] ?? null,
                (int) $auth->id,
            );
        } catch (InvalidArgumentException $e) {
            return redirect()->route('cash-registers.show', $cashRegister)->with('error', $e->getMessage());
        }

        $message = (float) $closing->difference === 0.0
            ? __('Tagesabschluss erfasst — Kassensturz ohne Differenz.')
            : __('Tagesabschluss erfasst — Differenz :diff.', ['diff' => number_format((float) $closing->difference, 2, ',', '.')]);

        return redirect()->route('cash-registers.show', $cashRegister)->with('status', $message);
    }
}
