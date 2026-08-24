<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SepaMandateController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\{MandateKind, MandateStatus};
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\SaveSepaMandateRequest;
use App\Models\Customer;
use App\Models\Finance\SepaMandate;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Mandatsregister (Feature 120, MVP-609).
 *
 * Ein Mandat ist die Erlaubnis des Kunden — es wird nie gelöscht, sondern
 * widerrufen. Der Widerruf ist der Nachweis, dass ab da nicht mehr eingezogen
 * werden durfte.
 */
class SepaMandateController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(): View {
        abort_unless(Gate::allows(Permission::FinancePaymentRun->value), 403);

        return view('finance.mandates.index', [
            'mandates' => SepaMandate::query()->with('customer')->orderByDesc('id')->paginate(25),
        ]);
    }

    public function form(?SepaMandate $mandate = null): View {
        abort_unless(Gate::allows(Permission::FinancePaymentRun->value), 403);

        return view('finance.mandates._form_dialog', [
            'mandate' => $mandate,
            'customers' => Customer::query()->whereNull('archived_at')->orderBy('name')->get(),
            'kinds' => MandateKind::cases(),
        ]);
    }

    public function store(SaveSepaMandateRequest $request): RedirectResponse {
        $data = $request->validated();

        SepaMandate::query()->create([
            'organization_id' => $this->currentOrganization()->id,
            'customer_id' => $data['customer_id'],
            'reference' => $data['reference'],
            'kind' => $data['kind'],
            'status' => MandateStatus::Active->value,
            'signed_on' => $data['signed_on'],
            'iban' => $data['iban'],
            'bic' => $data['bic'] ?? null,
            'account_holder' => $data['account_holder'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('status', __('sepa.mandate.created'));
    }

    public function revoke(SepaMandate $mandate): RedirectResponse {
        abort_unless(Gate::allows(Permission::FinancePaymentRun->value), 403);

        $mandate->forceFill([
            'status' => MandateStatus::Revoked->value,
            'revoked_on' => CarbonImmutable::today(),
        ])->save();

        $mandate->audit('sepaMandate.revoked', ['reference' => $mandate->reference]);

        return back()->with('status', __('sepa.mandate.revoked'));
    }
}
