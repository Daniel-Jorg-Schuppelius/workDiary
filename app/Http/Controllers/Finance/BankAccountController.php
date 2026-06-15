<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankAccountController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\SaveBankAccountRequest;
use App\Models\Finance\BankAccount;
use App\Support\Iban;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Verwaltung der eigenen Bankkonten (Feature 045, „Priorität 3"). Anlage und
 * Bearbeitung laufen als Modal (Hausstandard). Autorisierung über
 * BankAccountPolicy (finance.config). Die IBAN liegt verschlüsselt; die
 * Eindeutigkeit je Organisation wird über `iban_hash` geprüft.
 */
class BankAccountController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', BankAccount::class);

        $accounts = BankAccount::query()->orderBy('label')->get();

        return view('finance.bank-accounts.index', compact('accounts'));
    }

    public function create(): View {
        Gate::authorize('create', BankAccount::class);

        return view('finance.bank-accounts._form_dialog', [
            'account' => new BankAccount(['is_active' => true]),
        ]);
    }

    public function store(SaveBankAccountRequest $request): RedirectResponse {
        Gate::authorize('create', BankAccount::class);

        $data = $request->validated();
        if ($this->ibanExists((string) $data['iban'])) {
            return back()->withInput()->with('error', __('bank.account.error.duplicate_iban'));
        }

        $account = new BankAccount($data);
        $account->is_active = (bool) ($data['is_active'] ?? false);
        $account->save();

        return redirect()->route('finance.bank-accounts.index')->with('success', __('bank.account.flash.created'));
    }

    public function edit(BankAccount $bankAccount): View {
        Gate::authorize('update', $bankAccount);

        return view('finance.bank-accounts._form_dialog', ['account' => $bankAccount]);
    }

    public function update(SaveBankAccountRequest $request, BankAccount $bankAccount): RedirectResponse {
        Gate::authorize('update', $bankAccount);

        $data = $request->validated();
        if ($this->ibanExists((string) $data['iban'], $bankAccount->id)) {
            return back()->withInput()->with('error', __('bank.account.error.duplicate_iban'));
        }

        $bankAccount->fill($data);
        $bankAccount->is_active = (bool) ($data['is_active'] ?? false);
        $bankAccount->save();

        return redirect()->route('finance.bank-accounts.index')->with('success', __('bank.account.flash.updated'));
    }

    public function destroy(BankAccount $bankAccount): RedirectResponse {
        Gate::authorize('delete', $bankAccount);

        $bankAccount->delete();

        return redirect()->route('finance.bank-accounts.index')->with('success', __('bank.account.flash.deleted'));
    }

    private function ibanExists(string $iban, ?int $ignoreId = null): bool {
        $hash = Iban::hash($iban);
        if ($hash === null) {
            return false;
        }

        return BankAccount::query()
            ->where('iban_hash', $hash)
            ->when($ignoreId !== null, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }
}
