<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExchangeRateController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingExchangeRate;
use App\Models\Platform\User;
use App\Services\Accounting\ExchangeRateService;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Monatskurse für Fremdwährungsbelege (Feature 125, MVP-1012). */
class ExchangeRateController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly ExchangeRateService $rates) {}

    public function index(): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        return view('finance.accounting.exchange-rates', [
            'rates' => AccountingExchangeRate::query()->where('organization_id', $organization->id)
                ->orderByDesc('period')->orderBy('currency')->limit(500)->get(),
        ]);
    }

    public function form(?AccountingExchangeRate $exchangeRate = null): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        abort_if($exchangeRate !== null && (int) $exchangeRate->organization_id !== (int) $this->currentOrganizationOrAbort()->id, 404);

        return view('finance.accounting._exchange_rate_dialog', ['rate' => $exchangeRate]);
    }

    public function store(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $data = $request->validate([
            'currency' => ['required', Rule::enum(CurrencyCode::class)],
            'period' => ['required', 'date_format:Y-m'],
            'rate' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'source' => ['nullable', 'string', 'max:120'],
        ]);
        /** @var User $actor */
        $actor = $request->user();
        $this->rates->save(
            $this->currentOrganizationOrAbort(),
            $actor,
            CurrencyCode::from((string) $data['currency']),
            CarbonImmutable::createFromFormat('Y-m-d', $data['period'] . '-01') ?: CarbonImmutable::now(),
            Decimal::of((string) $data['rate'], 6),
            $data['source'] ?? null,
        );

        return redirect()->route('finance.accounting.exchange-rates.index')->with('status', __('accounting.exchange_rates.flash.saved'));
    }

    public function importForm(): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);

        return view('finance.accounting._exchange_rate_import_dialog');
    }

    public function import(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $data = $request->validate([
            'import' => ['required', 'string', 'max:20000'],
            'source' => ['nullable', 'string', 'max:120'],
        ]);
        /** @var User $actor */
        $actor = $request->user();
        $count = $this->rates->import($this->currentOrganizationOrAbort(), $actor, (string) $data['import'], $data['source'] ?? null);

        return redirect()->route('finance.accounting.exchange-rates.index')->with('status', trans_choice('accounting.exchange_rates.flash.imported', $count, ['count' => $count]));
    }
}
