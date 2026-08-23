<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\Finance\OpenItemDirection;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Models\Accounting\AccountingAccount;
use App\Services\Accounting\{AccountingReportService, OpenItemService, TaxationMethodResolver, VatFilingProfileResolver};
use App\Services\Accounting\Filing\{FilingDeadlineCalculator, RecapitulativeStatementService, VatFilingPeriodService, VatReturnService};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Finanzberichte der lokalen Buchhaltung (Feature 125, MVP-676).
 *
 * Alle Berichte teilen denselben Zeitraum (globaler Header) und dieselbe
 * Datenquelle ({@see AccountingReportService}) — Liste, Kennzahl und Export
 * können deshalb nicht auseinanderlaufen.
 */
class AccountingReportController extends Controller {
    use RendersReportPdf;
    use ResolvesCurrentOrganization;
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function __construct(
        private readonly AccountingReportService $reports,
        private readonly OpenItemService $openItems,
        private readonly VatFilingPeriodService $periods,
        private readonly VatReturnService $returns,
        private readonly VatFilingProfileResolver $filing,
        private readonly TaxationMethodResolver $taxation,
        private readonly RecapitulativeStatementService $recapitulatives,
        private readonly FilingDeadlineCalculator $deadlines,
    ) {}

    public function index(): View {
        $this->authorizeView();
        [$from, $to] = $this->range();
        $organization = $this->currentOrganizationOrAbort();

        return view('reports.accounting.index', [
            'from' => $from,
            'to' => $to,
            'quality' => $this->reports->dataQuality($organization, $from, $to),
            'liquidity' => $this->reports->liquidity($organization, $to),
        ]);
    }

    /** Summen- und Saldenliste. */
    public function trialBalance(Request $request): View|SymfonyResponse {
        $this->authorizeView();
        [$from, $to] = $this->range();
        $organization = $this->currentOrganizationOrAbort();
        $data = $this->reports->trialBalance($organization, $from, $to);

        if ($this->wantsExport($request)) {
            return $this->export($request, 'accounting-trial-balance', 'accounting.trial_balance', $from, $to, array_merge(
                [[
                    (string) __('accounting.ledger.column.number'),
                    (string) __('accounting.ledger.column.name'),
                    (string) __('accounting.reports.column.opening'),
                    (string) __('accounting.ledger.column.debit'),
                    (string) __('accounting.ledger.column.credit'),
                    (string) __('accounting.reports.column.balance'),
                ]],
                array_map(static fn (array $row): array => [
                    (string) $row['account']->number,
                    (string) $row['account']->name,
                    (string) $row['opening'],
                    (string) $row['debit'],
                    (string) $row['credit'],
                    (string) $row['balance'],
                ], $data['rows']),
            ));
        }

        return view('reports.accounting.trial-balance', $data + ['from' => $from, 'to' => $to]);
    }

    /** Kontenblatt eines einzelnen Kontos. */
    public function accountLedger(Request $request): View|SymfonyResponse {
        $this->authorizeView();
        [$from, $to] = $this->range();
        $organization = $this->currentOrganizationOrAbort();

        $accounts = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->orderBy('number')
            ->get();

        $selected = null;
        $requested = (string) $request->query('account', '');
        if ($requested !== '') {
            $selected = $accounts->firstWhere('id', (int) Sqid::decodeOrNumeric(AccountingAccount::class, $requested));
        }
        $selected ??= $accounts->first();

        if ($selected !== null && $this->wantsExport($request)) {
            // Der Export braucht alle Zeilen — eine Seite wäre ein anderer
            // Bericht als der auf dem Bildschirm.
            $lines = $this->reports->accountLedger($organization, $selected, $from, $to)['lines'];
            return $this->export($request, 'accounting-account-ledger', 'accounting.account_ledger', $from, $to, array_merge(
                [[
                    (string) __('accounting.ledger.column.booked_on'),
                    (string) __('accounting.ledger.column.journal_no'),
                    (string) __('accounting.ledger.column.memo'),
                    (string) __('accounting.ledger.column.debit'),
                    (string) __('accounting.ledger.column.credit'),
                ]],
                array_values(array_map(static fn ($line): array => [
                    (string) ($line->entry?->booked_on->toDateString() ?? ''),
                    (string) ($line->entry instanceof \App\Models\Accounting\AccountingEntry ? $line->entry->journal_no : ''),
                    (string) ($line->entry instanceof \App\Models\Accounting\AccountingEntry ? $line->entry->memo : ''),
                    (string) ($line->debit?->getAmount() ?? '0.00'),
                    (string) ($line->credit?->getAmount() ?? '0.00'),
                ], $lines instanceof Collection ? $lines->all() : [])),
            ));
        }

        // Die Ansicht blättert: Ein Sachkonto kann zehntausende Zeilen tragen.
        $data = $selected !== null
            ? $this->reports->accountLedger($organization, $selected, $from, $to, 100)
            : ['opening' => '0.00', 'lines' => collect(), 'closing' => '0.00'];

        return view('reports.accounting.account-ledger', $data + [
            'accounts' => $accounts,
            'selected' => $selected,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * Umsatzsteuer-Vorschau je Voranmeldungszeitraum (MVP-676/685).
     *
     * Der Zeitraum kommt aus dem Meldeprofil, nicht aus dem globalen Header:
     * Eine Auswertung über Periodengrenzen hinweg sähe aus wie eine
     * Voranmeldung, wäre aber keine.
     */
    public function vat(Request $request): View|SymfonyResponse {
        $this->authorizeView();
        [$from, $to] = $this->range();
        $organization = $this->currentOrganizationOrAbort();

        $period = $this->periods->parse((string) $request->query('period', ''))
            ?? $this->periods->periodAt($organization, $to)
            ?? $this->periods->periodAt($organization, CarbonImmutable::now());

        if ($period === null) {
            // Kleinunternehmer: kein Voranmeldungszeitraum, keine Vorschau.
            return view('reports.accounting.vat', [
                'rows' => [], 'output' => '0.00', 'input' => '0.00', 'payable' => '0.00',
                'method' => $this->taxation->at($organization),
                'period' => null, 'periods' => [], 'interval' => $this->filing->at($organization),
                'has_extension' => false, 'special_prepayment' => '0.00', 'remaining' => '0.00',
                'from' => $from, 'to' => $to,
            ]);
        }

        $data = $this->returns->preview($organization, $period);

        if ($this->wantsExport($request)) {
            $rows = [[
                (string) __('accounting.ledger.column.account'),
                (string) __('accounting.reports.column.direction'),
                (string) __('accounting.ledger.column.amount'),
            ]];
            foreach ($data['rows'] as $row) {
                $rows[] = [
                    (string) $row['account']->displayLabel(),
                    (string) __('enums.finance.tax-code-direction.' . $row['direction']),
                    (string) $row['amount'],
                ];
            }
            if ((float) $data['special_prepayment'] !== 0.0) {
                $rows[] = [(string) __('accounting.filing.field.special_prepayment'), '', '-' . $data['special_prepayment']];
            }
            // Kennziffern mit exportieren — sie sind der Abgleich mit der Erklärung.
            foreach ($data['fields'] as $field) {
                $rows[] = [
                    (string) __('accounting.filing.fields.column.field') . ' ' . $field['field'],
                    (string) $field['base'],
                    (string) $field['tax'],
                ];
            }

            return $this->export($request, 'accounting-vat', 'accounting.vat', $period->from, $period->to, $rows);
        }

        return view('reports.accounting.vat', $data + [
            'periods' => $this->periods->periodsFor($organization, $period->year),
            'from' => $period->from,
            'to' => $period->to,
        ]);
    }

    /**
     * Zusammenfassende Meldung (MVP-687) — je Empfänger-USt-IdNr.
     *
     * Die Frist steht daneben und verlängert sich **nicht** mit der
     * Dauerfristverlängerung (§ 18a Abs. 1 UStG).
     */
    public function recapitulative(Request $request): View|SymfonyResponse {
        $this->authorizeView();
        [$from, $to] = $this->range();
        $organization = $this->currentOrganizationOrAbort();

        $period = $this->periods->parse((string) $request->query('period', ''))
            ?? $this->periods->periodAt($organization, $to)
            ?? $this->periods->periodAt($organization, CarbonImmutable::now());

        $rangeFrom = $period !== null ? $period->from : $from;
        $rangeTo = $period !== null ? $period->to : $to;
        $data = $this->recapitulatives->report($organization, $rangeFrom, $rangeTo);

        if ($this->wantsExport($request)) {
            $rows = [[
                (string) __('accounting.recapitulative.column.vat_id'),
                (string) __('accounting.ledger.column.name'),
                (string) __('accounting.ledger.column.amount'),
            ]];
            foreach ($data['rows'] as $row) {
                $rows[] = [(string) $row['vat_id'], (string) $row['name'], (string) $row['amount']];
            }

            return $this->export($request, 'accounting-recapitulative', 'accounting.recapitulative', $rangeFrom, $rangeTo, $rows);
        }

        return view('reports.accounting.recapitulative', $data + [
            'period' => $period,
            'periods' => $period !== null ? $this->periods->periodsFor($organization, $period->year) : [],
            'due_on' => $period !== null ? $this->deadlines->recapitulative($period) : null,
            'from' => $rangeFrom,
            'to' => $rangeTo,
        ]);
    }

    public function euer(Request $request): View|SymfonyResponse {
        $this->authorizeView();
        [$from, $to] = $this->range();
        $data = $this->reports->euerPreview($this->currentOrganizationOrAbort(), $from, $to);

        if ($this->wantsExport($request)) {
            $rows = [[
                (string) __('accounting.reports.column.euer_category'),
                (string) __('accounting.reports.column.gross'),
                (string) __('accounting.reports.column.deductible'),
                (string) __('accounting.reports.column.not_deductible'),
            ]];
            foreach ($data['rows'] as $row) {
                $rows[] = [
                    (string) $row['category']->label(),
                    (string) $row['gross'],
                    (string) $row['deductible'],
                    (string) $row['not_deductible'],
                ];
            }

            return $this->export($request, 'accounting-euer', 'accounting.euer', $from, $to, $rows);
        }

        return view('reports.accounting.euer', $data + ['from' => $from, 'to' => $to]);
    }

    public function profitAndLoss(Request $request): View|SymfonyResponse {
        $this->authorizeView();
        [$from, $to] = $this->range();
        $data = $this->reports->profitAndLoss($this->currentOrganizationOrAbort(), $from, $to);

        if ($this->wantsExport($request)) {
            $rows = [[
                (string) __('accounting.ledger.column.account'),
                (string) __('accounting.reports.column.section'),
                (string) __('accounting.ledger.column.amount'),
            ]];
            foreach ($data['income'] as $row) {
                $rows[] = [(string) $row['account']->displayLabel(), (string) __('accounting.reports.section.income'), (string) $row['amount']];
            }
            foreach ($data['expense'] as $row) {
                $rows[] = [(string) $row['account']->displayLabel(), (string) __('accounting.reports.section.expense'), (string) $row['amount']];
            }

            return $this->export($request, 'accounting-pnl', 'accounting.pnl', $from, $to, $rows);
        }

        return view('reports.accounting.profit-and-loss', $data + ['from' => $from, 'to' => $to]);
    }

    public function liquidity(Request $request): View|SymfonyResponse {
        $this->authorizeView();
        [$from, $to] = $this->range();
        $organization = $this->currentOrganizationOrAbort();
        $data = $this->reports->liquidity($organization, $to);

        if ($this->wantsExport($request)) {
            $rows = [[
                (string) __('accounting.ledger.column.account'),
                (string) __('accounting.reports.column.balance'),
            ]];
            foreach ($data['accounts'] as $row) {
                $rows[] = [(string) $row['account']->displayLabel(), (string) $row['balance']];
            }
            $rows[] = [(string) __('accounting.reports.kpi.cash'), (string) $data['cash_total']];
            $rows[] = [(string) __('accounting.reports.kpi.receivable'), (string) $data['receivable']];
            $rows[] = [(string) __('accounting.reports.kpi.payable'), (string) $data['payable']];
            $rows[] = [(string) __('accounting.reports.kpi.forecast'), (string) $data['forecast']];

            return $this->export($request, 'accounting-liquidity', 'accounting.liquidity', $from, $to, $rows);
        }

        return view('reports.accounting.liquidity', $data + [
            'from' => $from,
            'to' => $to,
            'receivableAging' => $this->openItems->aging($organization, OpenItemDirection::Receivable, withItems: false)['buckets'],
            'payableAging' => $this->openItems->aging($organization, OpenItemDirection::Payable, withItems: false)['buckets'],
        ]);
    }

    public function quality(Request $request): View|SymfonyResponse {
        $this->authorizeView();
        [$from, $to] = $this->range();
        $data = $this->reports->dataQuality($this->currentOrganizationOrAbort(), $from, $to);

        if ($this->wantsExport($request)) {
            $rows = [[(string) __('accounting.reports.kpi.findings')]];
            foreach ($data['findings'] as $finding) {
                $rows[] = [(string) $finding];
            }

            return $this->export($request, 'accounting-quality', 'accounting.quality', $from, $to, $rows);
        }

        return view('reports.accounting.quality', $data + ['from' => $from, 'to' => $to]);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function range(): array {
        [$from, $to] = $this->globalDateRangeBounds();

        return [CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->startOfDay()];
    }

    private function authorizeView(): void {
        abort_unless(Gate::allows(Permission::AccountingLedgerView->value), 403);
    }

    private function wantsExport(Request $request): bool {
        return in_array((string) $request->query('export', ''), ['csv', 'xlsx', 'pdf'], true);
    }

    /**
     * Ein Ausgabepfad für CSV, XLSX und PDF (MVP-676/682).
     *
     * Alle drei Formate bekommen dieselben Zeilen und denselben Kopf — sonst
     * ließe sich mit demselben Filter dreimal etwas anderes behaupten.
     *
     * @param  list<list<string>>  $rows
     */
    private function export(Request $request, string $filename, string $code, CarbonImmutable $from, CarbonImmutable $to, array $rows): SymfonyResponse {
        $name = $filename . '-' . $from->toDateString() . '_' . $to->toDateString();
        $context = $this->reports->exportContext($this->currentOrganizationOrAbort(), $from, $to);

        if ((string) $request->query('export') === 'pdf') {
            return $this->pdfDownload('reports.pdf.accounting', [
                'title' => $this->reportTitle($code),
                'context' => $context,
                'rows' => $rows,
                'notice' => $this->reportNotice($code),
            ], $name . '.pdf', count($rows[0] ?? []) > 4 ? 'landscape' : 'portrait', $request, $code, $context);
        }

        return $this->csvWithMetadata($rows, $name, $code, $context, $request);
    }

    /** Titel des Berichts aus dem Exportcode — dieselbe Beschriftung wie in der Kachel. */
    private function reportTitle(string $code): string {
        return (string) __('accounting.reports.card.' . str_replace('accounting.', '', $code) . '.title');
    }

    /**
     * Vorbehalt, der auf das Blatt gehört.
     *
     * Ein ausgedruckter USt- oder EÜR-Bericht ohne diesen Satz sieht aus wie
     * eine abgegebene Erklärung — genau das ist er nicht.
     */
    private function reportNotice(string $code): ?string {
        return match ($code) {
            'accounting.vat' => (string) __('accounting.reports.vat_preview_hint'),
            'accounting.euer' => (string) __('accounting.reports.euer_preview_hint'),
            'accounting.pnl' => (string) __('accounting.reports.pnl_hint'),
            default => null,
        };
    }
}
