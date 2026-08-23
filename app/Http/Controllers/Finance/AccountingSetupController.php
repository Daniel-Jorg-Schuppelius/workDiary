<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingSetupController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\{AccountingSovereignty, ProfitDetermination, TaxationMethod, VatFilingInterval};
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Accounting\{AccountingAccount, AccountingFiscalYear, AccountingSovereigntyPeriod, AccountingVatFilingPeriod};
use App\Models\Organization;
use App\Services\Accounting\{AccountingProfileService, AccountingReportService, AccountingSovereigntyResolver, FiscalYearService, TaxationMethodResolver, VatFilingProfileResolver};
use App\Services\Accounting\Filing\VatSpecialPrepaymentService;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Einrichtung der lokalen Buchhaltung (Feature 125, MVP-671).
 *
 * Bewusst eine geführte Seite statt eines Schalters in den Einstellungen:
 * Buchungshoheit, Startdatum, Geschäftsjahr und Preflight gehören zusammen,
 * und wer sie einzeln umlegen kann, legt sie irgendwann widersprüchlich um.
 */
class AccountingSetupController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly AccountingProfileService $profiles,
        private readonly AccountingSovereigntyResolver $sovereignty,
        private readonly FiscalYearService $fiscalYears,
        private readonly TaxationMethodResolver $taxation,
        private readonly VatFilingProfileResolver $filing,
        private readonly AccountingReportService $reports,
        private readonly VatSpecialPrepaymentService $prepayments,
    ) {}

    public function index(): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerView->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        $profile = $this->profiles->profileFor($organization);

        return view('finance.accounting.setup', [
            'profile' => $profile,
            'preflight' => $profile->exists ? $this->profiles->preflight($organization) : null,
            'canConfigure' => Gate::allows(Permission::AccountingLedgerConfigure->value),
            'currentSovereignty' => $this->sovereignty->at($organization),
            'fiscalYears' => AccountingFiscalYear::query()
                ->where('organization_id', $organization->id)
                ->withCount('periods')
                ->orderByDesc('starts_on')
                ->get(),
            'sections' => AccountingSovereigntyPeriod::query()
                ->where('organization_id', $organization->id)
                ->with('actor')
                ->orderByDesc('valid_from')
                ->get(),
            'profitOptions' => ProfitDetermination::cases(),
            'sovereigntyOptions' => AccountingSovereignty::cases(),
            'taxationMethod' => $this->taxation->at($organization),
            'taxationPeriods' => \App\Models\Accounting\AccountingTaxationPeriod::query()
                ->where('organization_id', $organization->id)
                ->orderByDesc('valid_from')
                ->get(),
            // Meldeprofil (MVP-684): Zeitraum, Verlängerung und der
            // Ableitungsvorschlag aus der Vorjahressteuer.
            'filingInterval' => $this->filing->at($organization),
            'filingPeriods' => AccountingVatFilingPeriod::query()
                ->where('organization_id', $organization->id)
                ->orderByDesc('valid_from')
                ->get(),
            'filingExtension' => $this->filing->extensionFor($organization, (int) now()->year),
            'filingSuggestion' => $this->filingSuggestion($organization),
        ]);
    }

    /** Dialog: Geschäftsjahr anlegen. */
    public function fiscalYearForm(): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        return view('finance.accounting._fiscal_year_dialog', [
            'suggestedStart' => $this->suggestedFiscalYearStart($organization)->toDateString(),
        ]);
    }

    /** Dialog: Buchungshoheit ab Stichtag wechseln. */
    public function sovereigntyForm(): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        return view('finance.accounting._sovereignty_dialog', [
            'profile' => $this->profiles->profileFor($organization),
            'sovereigntyOptions' => AccountingSovereignty::cases(),
            'suggestedFrom' => CarbonImmutable::now()->startOfMonth()->toDateString(),
        ]);
    }

    /** Dialog: Versteuerungsart wechseln (MVP-679). */
    /**
     * Ableitungsvorschlag aus der Steuer des Vorjahres.
     *
     * Die Zahllast kommt aus derselben Auswertung wie der USt-Bericht — ein
     * zweiter Rechenweg würde zwei Wahrheiten erzeugen.
     *
     * @return array<string, mixed>
     */
    private function filingSuggestion(Organization $organization): array {
        $year = (int) now()->year;
        $priorFrom = CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $year - 1, 1, 1));
        $priorTo = $priorFrom->endOfYear();

        $preview = $this->reports->vatPreview($organization, $priorFrom, $priorTo);

        return $this->filing->suggest($year, (string) $preview['payable']);
    }

    /** Dialog: Voranmeldungszeitraum wechseln (MVP-684). */
    public function filingIntervalForm(): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        return view('finance.accounting._filing_interval_dialog', [
            'intervals' => VatFilingInterval::cases(),
            'current' => $this->filing->at($organization),
            'suggestion' => $this->filingSuggestion($organization),
            'suggestedFrom' => CarbonImmutable::now()->addYear()->startOfYear()->toDateString(),
        ]);
    }

    public function switchFilingInterval(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $actor = $request->user();
        abort_if($actor === null, 403);

        $data = $request->validate([
            'interval' => ['required', 'string', 'in:' . implode(',', array_column(VatFilingInterval::cases(), 'value'))],
            'valid_from' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->filing->switchTo(
            $organization,
            VatFilingInterval::from((string) $data['interval']),
            CarbonImmutable::parse((string) $data['valid_from']),
            $actor,
            $data['reason'] ?? null,
        );

        return back()->with('status', __('accounting.filing.flash.switched'));
    }

    /** Dialog: Dauerfristverlängerung erfassen (MVP-684). */
    public function extensionForm(): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $year = (int) now()->year;

        return view('finance.accounting._vat_extension_dialog', [
            'year' => $year,
            'extension' => $this->filing->extensionFor($organization, $year),
            'interval' => $this->filing->at($organization),
        ]);
    }

    public function storeExtension(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $actor = $request->user();
        abort_if($actor === null, 403);

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'granted_on' => ['nullable', 'date'],
            'special_prepayment_amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->filing->recordExtension(
            $organization,
            (int) $data['year'],
            isset($data['granted_on']) ? CarbonImmutable::parse((string) $data['granted_on']) : null,
            isset($data['special_prepayment_amount'])
                ? number_format((float) $data['special_prepayment_amount'], 2, '.', '')
                : null,
            $actor,
            $data['note'] ?? null,
        );

        return back()->with('status', __('accounting.filing.flash.extension_saved'));
    }

    /** Dialog: Sondervorauszahlung buchen (MVP-685). */
    public function prepaymentForm(): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        $accounts = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->active()
            ->orderBy('number')
            ->get();

        return view('finance.accounting._prepayment_dialog', [
            'calculation' => $this->prepayments->calculate($organization, (int) now()->year),
            'accounts' => $accounts,
            'moneyAccounts' => $accounts->filter(fn (AccountingAccount $account): bool => $account->is_bank || $account->is_cash)->values(),
        ]);
    }

    public function storePrepayment(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $actor = $request->user();
        abort_if($actor === null, 403);

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'prepayment_account' => ['required', 'string'],
            'money_account' => ['required', 'string'],
            'booked_on' => ['required', 'date'],
        ]);

        $accounts = AccountingAccount::query()->where('organization_id', $organization->id);

        $this->prepayments->post(
            $organization,
            (int) $data['year'],
            (clone $accounts)->whereKey(Sqid::decodeOrNumeric(AccountingAccount::class, (string) $data['prepayment_account']))->firstOrFail(),
            (clone $accounts)->whereKey(Sqid::decodeOrNumeric(AccountingAccount::class, (string) $data['money_account']))->firstOrFail(),
            number_format((float) $data['amount'], 2, '.', ''),
            CarbonImmutable::parse((string) $data['booked_on']),
            $actor,
        );

        return back()->with('status', __('accounting.filing.flash.prepayment_posted'));
    }

    public function taxationForm(): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $from = CarbonImmutable::now()->addYear()->startOfYear();

        return view('finance.accounting._taxation_dialog', [
            'methods' => TaxationMethod::cases(),
            'current' => $this->taxation->at($organization),
            'suggestedFrom' => $from->toDateString(),
            // Der Wechsel ist eine fachliche Entscheidung; das Programm zeigt,
            // welche offenen Posten davon berührt sind (§ 20 S. 3 UStG).
            'changeover' => $this->taxation->changeoverReport($organization, $from),
        ]);
    }

    public function switchTaxation(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $actor = $request->user();
        abort_if($actor === null, 403);

        $data = $request->validate([
            'method' => ['required', 'string', 'in:' . implode(',', array_column(TaxationMethod::cases(), 'value'))],
            'valid_from' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->taxation->switchTo(
            $organization,
            TaxationMethod::from((string) $data['method']),
            CarbonImmutable::parse((string) $data['valid_from']),
            $actor,
            $data['reason'] ?? null,
        );

        return back()->with('status', __('accounting.taxation.flash.switched'));
    }

    /** Einrichtungsangaben speichern — ohne die Buchungshoheit zu bewegen. */
    public function update(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        $data = $request->validate([
            'profit_determination' => ['required', 'string', 'in:' . implode(',', array_column(ProfitDetermination::cases(), 'value'))],
            'base_currency' => ['required', 'string', 'size:3'],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
            'starts_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $currency = CurrencyCode::tryFrom(strtoupper((string) $data['base_currency']));
        abort_if($currency === null, 422);

        $this->profiles->configure($organization, [
            'profit_determination' => ProfitDetermination::from((string) $data['profit_determination']),
            'base_currency' => $currency,
            'fiscal_year_start_month' => (int) $data['fiscal_year_start_month'],
            'starts_on' => isset($data['starts_on']) ? CarbonImmutable::parse((string) $data['starts_on']) : null,
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('status', __('accounting.ledger.flash.saved'));
    }

    /** Geschäftsjahr samt Perioden anlegen. */
    public function storeFiscalYear(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        $data = $request->validate([
            'starts_on' => ['required', 'date'],
            'label' => ['nullable', 'string', 'max:32'],
        ]);

        $year = $this->fiscalYears->create(
            $organization,
            CarbonImmutable::parse((string) $data['starts_on']),
            $data['label'] ?? null,
        );

        return back()->with('status', __('accounting.ledger.flash.fiscal_year_created', ['year' => $year->label]));
    }

    /** Lokale Buchhaltung scharf schalten (nur nach vollständigem Preflight). */
    public function activate(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $actor = $request->user();
        abort_if($actor === null, 403);

        $this->profiles->activateLocal($organization, $actor);

        return back()->with('status', __('accounting.ledger.flash.activated'));
    }

    /**
     * Vorschlag für den Jahresbeginn: der Tag nach dem letzten Jahr, sonst der
     * konfigurierte Geschäftsjahresmonat im laufenden Kalenderjahr.
     */
    private function suggestedFiscalYearStart(\App\Models\Organization $organization): CarbonImmutable {
        $last = AccountingFiscalYear::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('ends_on')
            ->first();

        if ($last instanceof AccountingFiscalYear) {
            return CarbonImmutable::parse($last->ends_on)->addDay();
        }

        $month = $this->profiles->profileFor($organization)->fiscal_year_start_month;

        return CarbonImmutable::now()->startOfYear()->addMonths(max(0, $month - 1));
    }

    /** Führung ab Stichtag abgeben oder zurückholen. */
    public function switchSovereignty(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $actor = $request->user();
        abort_if($actor === null, 403);

        $data = $request->validate([
            'sovereignty' => ['required', 'string', 'in:' . implode(',', array_column(AccountingSovereignty::cases(), 'value'))],
            'external_provider' => ['nullable', 'string', 'max:64'],
            'valid_from' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->profiles->switchSovereignty(
            $organization,
            AccountingSovereignty::from((string) $data['sovereignty']),
            CarbonImmutable::parse((string) $data['valid_from']),
            $actor,
            $data['external_provider'] ?? null,
            $data['reason'] ?? null,
        );

        return back()->with('status', __('accounting.ledger.flash.sovereignty_switched'));
    }
}
