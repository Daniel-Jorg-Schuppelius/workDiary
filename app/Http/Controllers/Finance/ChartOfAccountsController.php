<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChartOfAccountsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\{AccountType, BalanceSide, BwaGroup, EuerCategory};
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Accounting\{AccountingAccount, AccountingTaxCode};
use App\Services\Accounting\{ChartOfAccountsService, ChartOfAccountsTemplateService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Kontenplan (Feature 125, MVP-672). Pflege und CSV-Import; ein bebuchtes
 * Konto wird stillgelegt, nicht gelöscht.
 */
class ChartOfAccountsController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ChartOfAccountsService $accounts,
        private readonly ChartOfAccountsTemplateService $templates,
    ) {}

    public function index(Request $request): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerView->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        $query = AccountingAccount::query()->where('organization_id', $organization->id);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->whereLikeEscaped('number', $search)->orWhereLikeEscaped('name', $search);
            });
        }

        $type = AccountType::tryFrom((string) $request->query('type', ''));
        if ($type instanceof AccountType) {
            $query->where('type', $type->value);
        }

        if ($request->boolean('only_active', true)) {
            $query->where('is_active', true);
        }

        return view('finance.accounting.accounts', [
            'accounts' => $query->orderBy('number')->paginate(50)->withQueryString(),
            'types' => AccountType::cases(),
            'canConfigure' => Gate::allows(Permission::AccountingLedgerConfigure->value),
            'search' => $search,
            'selectedType' => $type,
            'onlyActive' => $request->boolean('only_active', true),
            'templates' => $this->templates->available(),
            'hasAccounts' => AccountingAccount::query()->where('organization_id', $organization->id)->exists(),
            // Steuerkennzeichen samt UStVA-Kennziffern (MVP-688).
            'taxCodes' => AccountingTaxCode::query()
                ->where('organization_id', $organization->id)
                ->orderBy('code')
                ->get(),
        ]);
    }

    /** Dialog: Kennziffern eines Steuerkennzeichens pflegen (MVP-688). */
    public function taxCodeForm(AccountingTaxCode $taxCode): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        abort_unless((int) $taxCode->organization_id === (int) $organization->id, 404);

        return view('finance.accounting._tax_code_dialog', ['taxCode' => $taxCode]);
    }

    public function updateTaxCode(Request $request, AccountingTaxCode $taxCode): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        abort_unless((int) $taxCode->organization_id === (int) $organization->id, 404);

        $data = $request->validate([
            'ustva_base_field' => ['nullable', 'string', 'max:8'],
            'ustva_tax_field' => ['nullable', 'string', 'max:8'],
        ]);

        $taxCode->update([
            'ustva_base_field' => $data['ustva_base_field'] ?: null,
            'ustva_tax_field' => $data['ustva_tax_field'] ?: null,
        ]);

        return back()->with('status', __('accounting.filing.fields.flash.saved'));
    }

    public function form(?AccountingAccount $account = null): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);

        return view('finance.accounting._account_dialog', [
            'account' => $account,
            'types' => AccountType::cases(),
            'sides' => BalanceSide::cases(),
            'euerCategories' => EuerCategory::cases(),
            'bwaGroups' => BwaGroup::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        $this->accounts->create($organization, $this->validated($request, $organization));

        return back()->with('status', __('accounting.ledger.flash.account_saved'));
    }

    public function update(Request $request, AccountingAccount $account): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        abort_unless((int) $account->organization_id === (int) $organization->id, 404);

        $data = $this->validated($request, $organization, $account);
        $account->update([
            'number' => $data['number'],
            'name' => $data['name'],
            'type' => AccountType::from((string) $data['type']),
            'normal_balance' => BalanceSide::from((string) $data['normal_balance']),
            'is_open_item' => (bool) ($data['is_open_item'] ?? false),
            'is_bank' => (bool) ($data['is_bank'] ?? false),
            'is_cash' => (bool) ($data['is_cash'] ?? false),
            'is_clearing' => (bool) ($data['is_clearing'] ?? false),
            'euer_category' => $this->euerCategory($data),
            'deductible_percent' => number_format((float) ($data['deductible_percent'] ?? 100), 2, '.', ''),
            'datev_account' => $data['datev_account'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('status', __('accounting.ledger.flash.account_saved'));
    }

    /**
     * Die Kategorie hängt am Konto und darf leer bleiben — ein leeres Feld
     * ist ein bewusster Klärungsfall, keine stille Null.
     *
     * @param  array<string, mixed>  $data
     */
    private function euerCategory(array $data): ?EuerCategory {
        $value = $data['euer_category'] ?? null;

        return is_string($value) && $value !== '' ? EuerCategory::from($value) : null;
    }

    /** Stilllegen statt löschen — die Historie muss ihr Konto behalten. */
    public function deactivate(AccountingAccount $account): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        abort_unless((int) $account->organization_id === (int) $organization->id, 404);

        $this->accounts->deactivate($account);

        return back()->with('status', __('accounting.ledger.flash.account_deactivated'));
    }

    /**
     * Kontenplan aus einer Vorlage anlegen — additiv: vorhandene Konten und
     * Regeln bleiben unverändert.
     */
    public function applyTemplate(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        $data = $request->validate(['template' => ['required', 'string', 'max:32']]);
        $result = $this->templates->apply($organization, (string) $data['template']);

        return back()->with('status', __('accounting.template.flash.applied', [
            'accounts' => $result['accounts'],
            'tax_codes' => $result['tax_codes'],
            'rules' => $result['rules'],
            'skipped' => $result['skipped'],
        ]));
    }

    public function import(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:4096']]);
        $path = $request->file('file')?->getRealPath();
        abort_if($path === null || $path === false, 422);

        $result = $this->accounts->importCsv($organization, $path);

        return back()->with('status', __('accounting.ledger.flash.imported', [
            'imported' => $result['imported'],
            'updated' => $result['updated'],
            'errors' => count($result['errors']),
        ]));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, \App\Models\Organization $organization, ?AccountingAccount $account = null): array {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:16'],
            'name' => ['required', 'string', 'max:191'],
            'type' => ['required', 'string', 'in:' . implode(',', array_column(AccountType::cases(), 'value'))],
            'normal_balance' => ['required', 'string', 'in:' . implode(',', array_column(BalanceSide::cases(), 'value'))],
            'is_open_item' => ['nullable', 'boolean'],
            'is_bank' => ['nullable', 'boolean'],
            'is_cash' => ['nullable', 'boolean'],
            'is_clearing' => ['nullable', 'boolean'],
            'euer_category' => ['nullable', 'string', 'in:' . implode(',', array_column(EuerCategory::cases(), 'value'))],
            'bwa_group' => ['nullable', 'string', 'in:' . implode(',', array_column(BwaGroup::cases(), 'value'))],
            'deductible_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'datev_account' => ['nullable', 'string', 'max:16'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        // Kontonummern sind je Organisation eindeutig — ein rohes `unique:`
        // würde über Mandantengrenzen hinweg prüfen.
        $duplicate = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->where('number', $data['number'])
            ->when($account !== null, fn ($query) => $query->whereKeyNot($account?->id))
            ->exists();

        if ($duplicate) {
            abort(422, (string) __('accounting.ledger.error.account_number_taken'));
        }

        return $data;
    }
}
