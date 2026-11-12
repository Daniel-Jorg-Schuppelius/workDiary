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

use App\Enums\Finance\{AccountType, BalanceSide, BwaGroup, EuerCategory, TaxCodeDirection};
use App\Enums\Invoicing\IncomingInvoiceTaxCase;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Accounting\{AccountingAccount, AccountingTaxCode};
use App\Services\Accounting\{ChartOfAccountsService, ChartOfAccountsTemplateService, TaxCodeService};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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
        private readonly TaxCodeService $taxCodes,
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
                ->with(['taxAccount', 'inputTaxAccount'])
                ->orderBy('code')
                ->orderBy('valid_from')
                ->get(),
        ]);
    }

    /** Dialog: Steuerkennzeichen anlegen oder ändern (MVP-688/1558). */
    public function taxCodeForm(?AccountingTaxCode $taxCode = null): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        abort_unless($taxCode === null || (int) $taxCode->organization_id === (int) $organization->id, 404);

        return view('finance.accounting._tax_code_dialog', [
            'taxCode' => $taxCode,
            'inUse' => $taxCode !== null && $this->taxCodes->isInUse($taxCode),
            'directions' => TaxCodeDirection::cases(),
            'categories' => TaxCodeService::ZERO_RATED_CATEGORIES,
            'taxCases' => array_values(array_filter(IncomingInvoiceTaxCase::cases(), static fn (IncomingInvoiceTaxCase $case): bool => $case->isSelfAssessed())),
            'accounts' => AccountingAccount::query()->where('organization_id', $organization->id)->active()->orderBy('number')->get(),
        ]);
    }

    public function storeTaxCode(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        $data = $this->validatedTaxCode($request, ['code' => ['required', 'string', 'max:16', 'regex:/^[A-Za-z0-9_.-]+$/']]);
        $this->taxCodes->create($organization, ['code' => (string) $request->input('code')] + $data);

        return back()->with('status', __('accounting.tax_codes.flash.saved'));
    }

    public function updateTaxCode(Request $request, AccountingTaxCode $taxCode): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        abort_unless((int) $taxCode->organization_id === (int) $organization->id, 404);

        $next = $this->taxCodes->update($taxCode, $this->validatedTaxCode($request));

        return back()->with('status', $next->is($taxCode)
            ? __('accounting.tax_codes.flash.saved')
            : __('accounting.tax_codes.flash.versioned', ['date' => $next->valid_from->format(\App\Support\Formats::date())]));
    }

    public function deactivateTaxCode(AccountingTaxCode $taxCode): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        abort_unless((int) $taxCode->organization_id === (int) $this->currentOrganizationOrAbort()->id, 404);

        $this->taxCodes->deactivate($taxCode);

        return back()->with('status', __('accounting.tax_codes.flash.deactivated'));
    }

    /**
     * @param  array<string, list<mixed>>  $extraRules
     * @return array{name: string, direction: TaxCodeDirection, rate: string, tax_account_id: ?int, input_tax_account_id: ?int, tax_case: ?IncomingInvoiceTaxCase, ustva_base_field: ?string, ustva_tax_field: ?string, ustva_input_tax_field: ?string, datev_tax_key: ?string, valid_from: CarbonImmutable}
     */
    private function validatedTaxCode(Request $request, array $extraRules = []): array {
        $data = $request->validate($extraRules + [
            'name' => ['required', 'string', 'max:191'],
            'direction' => ['required', 'string', Rule::enum(TaxCodeDirection::class)],
            'rate' => ['required', 'numeric', 'decimal:0,2', 'between:0,100'],
            'tax_account' => ['nullable', 'string', 'max:64'],
            // Steuerschuld des Empfängers (MVP-1621): Vorsteuerkonto, Steuerfall des Rechnungseingangs, Vorsteuer-Kennziffer.
            'input_tax_account' => ['nullable', 'string', 'max:64'],
            'tax_case' => ['nullable', 'string', Rule::enum(IncomingInvoiceTaxCase::class)],
            'ustva_input_tax_field' => ['nullable', 'string', 'max:8'],
            // BU-Schlüssel des DATEV-Buchungsstapels (MVP-1622).
            'datev_tax_key' => ['nullable', 'string', 'regex:/^[0-9]{1,4}$/'],
            // Steuerkategorie (EN 16931) — über sie findet die Buchungs-Inbox das Kennzeichen eines Umsatzes ohne Steuer.
            'tax_category' => ['nullable', 'string', Rule::in(TaxCodeService::ZERO_RATED_CATEGORIES)],
            'valid_from' => ['required', 'date'],
            'ustva_base_field' => ['nullable', 'string', 'max:8'],
            'ustva_tax_field' => ['nullable', 'string', 'max:8'],
        ]);

        return [
            'name' => (string) $data['name'],
            'direction' => TaxCodeDirection::from((string) $data['direction']),
            'rate' => (string) $data['rate'],
            // Die Zugehörigkeit zur Organisation prüft der TaxCodeService.
            'tax_account_id' => empty($data['tax_account']) ? null : Sqid::decodeOrNumeric(AccountingAccount::class, (string) $data['tax_account']),
            'input_tax_account_id' => empty($data['input_tax_account']) ? null : Sqid::decodeOrNumeric(AccountingAccount::class, (string) $data['input_tax_account']),
            'tax_case' => IncomingInvoiceTaxCase::tryFrom((string) ($data['tax_case'] ?? '')),
            'ustva_input_tax_field' => StringHelper::trimToNull($data['ustva_input_tax_field'] ?? null),
            'datev_tax_key' => StringHelper::trimToNull($data['datev_tax_key'] ?? null),
            'tax_category' => StringHelper::trimToNull($data['tax_category'] ?? null),
            'ustva_base_field' => StringHelper::trimToNull($data['ustva_base_field'] ?? null),
            'ustva_tax_field' => StringHelper::trimToNull($data['ustva_tax_field'] ?? null),
            'valid_from' => CarbonImmutable::parse((string) $data['valid_from'])->startOfDay(),
        ];
    }

    public function form(?AccountingAccount $account = null): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);

        return view('finance.accounting._account_dialog', [
            'account' => $account,
            // DATEV-Automatikkonto (MVP-1622): Kennzeichen, dessen Steuer DATEV aus dem Konto rechnet.
            'taxCodes' => AccountingTaxCode::query()
                ->where('organization_id', $this->currentOrganizationOrAbort()->id)
                ->where('is_active', true)
                ->orderBy('code')
                ->orderByDesc('valid_from')
                ->get()
                ->unique('code')
                ->values(),
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
            'is_cost_center_required' => (bool) ($data['is_cost_center_required'] ?? false),
            'euer_category' => $this->euerCategory($data),
            'bwa_group' => is_string($data['bwa_group'] ?? null) && $data['bwa_group'] !== '' ? BwaGroup::from($data['bwa_group']) : null,
            'deductible_percent' => Decimal::of((string) ($data['deductible_percent'] ?? 100), 2)->getValue(),
            'datev_account' => $data['datev_account'] ?? null,
            'default_tax_code_id' => $data['default_tax_code_id'],
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
    private function validated(Request $request, \App\Models\Platform\Organization $organization, ?AccountingAccount $account = null): array {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:16'],
            'name' => ['required', 'string', 'max:191'],
            'type' => ['required', 'string', Rule::enum(AccountType::class)],
            'normal_balance' => ['required', 'string', Rule::enum(BalanceSide::class)],
            'is_open_item' => ['nullable', 'boolean'],
            'is_bank' => ['nullable', 'boolean'],
            'is_cash' => ['nullable', 'boolean'],
            'is_clearing' => ['nullable', 'boolean'],
            'is_cost_center_required' => ['nullable', 'boolean'],
            'euer_category' => ['nullable', 'string', Rule::enum(EuerCategory::class)],
            'bwa_group' => ['nullable', 'string', Rule::enum(BwaGroup::class)],
            'deductible_percent' => ['nullable', 'numeric', 'decimal:0,8', 'min:0', 'max:100'],
            'datev_account' => ['nullable', 'string', 'max:16'],
            'datev_automatic' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $data['default_tax_code_id'] = null;
        if (! empty($data['datev_automatic'])) {
            $data['default_tax_code_id'] = AccountingTaxCode::query()
                ->where('organization_id', $organization->id)
                ->whereKey(Sqid::decodeOrNumeric(AccountingTaxCode::class, (string) $data['datev_automatic']))
                ->value('id');
            abort_if($data['default_tax_code_id'] === null, 422);
        }
        unset($data['datev_automatic']);

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
