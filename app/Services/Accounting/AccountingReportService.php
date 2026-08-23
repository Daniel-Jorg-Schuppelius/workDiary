<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingReportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\{AccountType, AccountingEntryStatus, EuerCategory, OpenItemDirection, PostingAccountRole, RecurringRunStatus, SettlementKind};
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingEntryLine, AccountingFilingObligation, AccountingOpenItem, AccountingOpenItemSettlement, AccountingPostingRule, AccountingProfile, AccountingRecurringRun, AccountingTaxCode};
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finanzberichte der lokalen Buchhaltung (Feature 125, MVP-676).
 *
 * Alle Berichte lesen **ausschließlich** festgeschriebene Buchungen
 * (`posted`/`reversed`) — ein Entwurf ist eine Absicht, keine Zahl. Damit
 * liefern Liste, Kennzahl und Export bei gleichen Filtern zwangsläufig
 * dieselbe Grundgesamtheit: Es gibt nur eine Quelle.
 *
 * Umsatzsteuer- und EÜR-Auswertung sind **prüfbare Vorschauen**, keine
 * Erklärungen — sie kennzeichnen Methode, Zeitraum und ungeklärte Fälle.
 */
class AccountingReportService {
    /** Zustände, die in eine Auswertung eingehen. */
    private const POSTED = [AccountingEntryStatus::Posted->value, AccountingEntryStatus::Reversed->value];

    public function __construct(private readonly TaxationMethodResolver $taxation) {}

    /**
     * Summen- und Saldenliste: Vortrag, Periodenbewegung und Saldo je Konto.
     *
     * @return array{rows: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function trialBalance(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $opening = $this->sumsByAccount($organization, null, $from->subDay());
        $period = $this->sumsByAccount($organization, $from, $to);

        $accountIds = array_unique([...array_keys($opening), ...array_keys($period)]);
        $accounts = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $accountIds)
            ->orderBy('number')
            ->get()
            ->keyBy('id');

        $rows = [];
        $totals = ['opening' => '0.00', 'debit' => '0.00', 'credit' => '0.00', 'balance' => '0.00'];

        foreach ($accounts as $id => $account) {
            $openDebit = $opening[$id]['debit'] ?? '0.00';
            $openCredit = $opening[$id]['credit'] ?? '0.00';
            $debit = $period[$id]['debit'] ?? '0.00';
            $credit = $period[$id]['credit'] ?? '0.00';

            $openingBalance = $this->sub($openDebit, $openCredit);
            $balance = $this->add($openingBalance, $this->sub($debit, $credit));

            $rows[] = [
                'account' => $account,
                'opening' => $openingBalance,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
            ];

            $totals['opening'] = $this->add($totals['opening'], $openingBalance);
            $totals['debit'] = $this->add($totals['debit'], $debit);
            $totals['credit'] = $this->add($totals['credit'], $credit);
            $totals['balance'] = $this->add($totals['balance'], $balance);
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Kontenblatt: Vortrag, Bewegungen und Endsaldo eines Kontos.
     *
     * Sortierung und Endsaldo entstehen in der Datenbank. Ein Sachkonto kann
     * über fünf Geschäftsjahre zehntausende Zeilen tragen — sie alle zu
     * hydrieren, nur um sie zu addieren, kostet ein Vielfaches der Abfrage
     * (gemessen in MVP-683). Ohne `$perPage` wird weiterhin alles geliefert;
     * der Export braucht die vollständige Menge.
     *
     * @return array{opening: string, lines: Collection<int, AccountingEntryLine>|LengthAwarePaginator<int, AccountingEntryLine>, closing: string}
     */
    public function accountLedger(Organization $organization, AccountingAccount $account, CarbonImmutable $from, CarbonImmutable $to, ?int $perPage = null): array {
        $before = $this->sumsByAccount($organization, null, $from->subDay(), $account->id);
        $opening = $this->sub($before[$account->id]['debit'] ?? '0.00', $before[$account->id]['credit'] ?? '0.00');

        $period = $this->sumsByAccount($organization, $from, $to, $account->id);
        $closing = $this->add(
            $opening,
            $this->sub($period[$account->id]['debit'] ?? '0.00', $period[$account->id]['credit'] ?? '0.00'),
        );

        $query = AccountingEntryLine::query()
            ->where('accounting_entry_lines.organization_id', $organization->id)
            ->where('accounting_entry_lines.accounting_account_id', $account->id)
            ->join('accounting_entries', 'accounting_entries.id', '=', 'accounting_entry_lines.accounting_entry_id')
            ->whereIn('accounting_entries.status', self::POSTED)
            ->whereDate('accounting_entries.booked_on', '>=', $from->toDateString())
            ->whereDate('accounting_entries.booked_on', '<=', $to->toDateString())
            ->orderBy('accounting_entries.booked_on')
            ->orderBy('accounting_entries.journal_no')
            ->select('accounting_entry_lines.*')
            ->with('entry');

        $lines = $perPage === null ? $query->get() : $query->paginate($perPage)->withQueryString();

        return ['opening' => $opening, 'lines' => $lines, 'closing' => $closing];
    }

    /**
     * Umsatzsteuer-Vorschau: Bemessungsgrundlagen und Steuer je Steuerkonto.
     *
     * @return array{rows: list<array<string, mixed>>, output: string, input: string, payable: string}
     */
    public function vatPreview(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $method = $this->taxation->at($organization, $to);
        $rows = [];
        $output = '0.00';
        $input = '0.00';

        // Steuerkonten sind die, die als solche BENANNT sind: über ein
        // Steuerkennzeichen oder eine Buchungsregel mit Steuerrolle. Aus der
        // Kontoart allein ließe sich das nicht ableiten — nicht jedes
        // Passivkonto ist Umsatzsteuer.
        $taxAccountIds = AccountingTaxCode::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('tax_account_id')
            ->pluck('tax_account_id')
            ->merge(AccountingPostingRule::query()
                ->where('organization_id', $organization->id)
                ->whereIn('role', [PostingAccountRole::TaxOutput->value, PostingAccountRole::TaxInput->value])
                ->pluck('accounting_account_id'))
            ->unique()
            ->all();

        $accounts = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $taxAccountIds)
            ->orderBy('number')
            ->get();

        $sums = $this->sumsByAccount($organization, $from, $to);

        // Ist-Versteuerung (§ 20 UStG): Die Umsatzsteuer entsteht erst mit der
        // Vereinnahmung — also zählen die Ausgleiche, nicht die Belegbuchung.
        // Die Vorsteuer bleibt davon unberührt und kommt weiter aus den
        // Buchungen.
        $collected = $method->followsPayments()
            ? $this->collectedOutputTax($organization, $from, $to)
            : [];

        foreach ($accounts as $account) {
            $debit = $sums[$account->id]['debit'] ?? '0.00';
            $credit = $sums[$account->id]['credit'] ?? '0.00';

            // Umsatzsteuer steht im Haben eines Passivkontos, Vorsteuer im Soll
            // eines Aktivkontos — die Kontoart entscheidet, nicht die Nummer.
            $isOutput = $account->type === AccountType::Liability;

            if ($isOutput && $method->followsPayments()) {
                $amount = $collected[$account->id] ?? '0.00';
                if ((float) $amount === 0.0) {
                    continue;
                }
            } else {
                if ((float) $debit === 0.0 && (float) $credit === 0.0) {
                    continue;
                }

                $amount = $isOutput ? $this->sub($credit, $debit) : $this->sub($debit, $credit);
            }

            $rows[] = [
                'account' => $account,
                'direction' => $isOutput ? 'output' : 'input',
                'amount' => $amount,
            ];

            $isOutput
                ? $output = $this->add($output, $amount)
                : $input = $this->add($input, $amount);
        }

        return [
            'rows' => $rows,
            'method' => $method,
            'output' => $output,
            'input' => $input,
            'payable' => $this->sub($output, $input),
        ];
    }

    /**
     * Vereinnahmte Umsatzsteuer je Steuerkonto (Ist-Versteuerung).
     *
     * Der Steueranteil einer Zahlung ist **anteilig**: Eine Teilzahlung trägt
     * nicht den vollen Steuerbetrag des Belegs. Gerechnet wird je Steuerzeile
     * der Ursprungsbuchung mit dem Verhältnis Ausgleich zu Ursprungsbetrag —
     * eine Rundung auf der Summe würde bei mehreren Steuersätzen abweichen.
     *
     * @return array<int, string> Kontoschlüssel → vereinnahmte Steuer
     */
    private function collectedOutputTax(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $collected = [];

        foreach ($this->settlementsInPeriod($organization, $from, $to) as $settlement) {
            $item = $settlement->openItem;
            $entry = $item?->entry;
            if (! $item instanceof AccountingOpenItem || $entry === null) {
                continue;
            }

            $original = (float) ($item->original_amount?->getAmount() ?? '0.00');
            if ($original === 0.0) {
                continue;
            }

            // Eine Rückbuchung nimmt die vereinnahmte Steuer wieder heraus.
            $sign = $settlement->kind === SettlementKind::Reversal ? -1.0 : 1.0;
            $ratio = ((float) ($settlement->amount?->getAmount() ?? '0.00')) / $original * $sign;

            foreach ($entry->lines as $line) {
                $account = $line->account;
                if ($account === null || $account->type !== AccountType::Liability) {
                    continue;
                }

                $tax = (float) ($line->credit?->getAmount() ?? '0.00') - (float) ($line->debit?->getAmount() ?? '0.00');
                if ($tax === 0.0) {
                    continue;
                }

                $share = number_format($tax * $ratio, 2, '.', '');
                $collected[$account->id] = $this->add($collected[$account->id] ?? '0.00', $share);
            }
        }

        return $collected;
    }

    /**
     * EÜR-Vorschau nach Formularzeilen (§ 4 Abs. 3 EStG).
     *
     * Grundregel ist § 11 EStG: gezählt wird, was zu- oder abgeflossen ist.
     * Zwei Quellen ergeben zusammen den Zahlungsstrom, ohne einander zu
     * überschneiden — direkte Geldbuchungen (Kassenbeleg, Lastschrift) mit
     * ihren Erfolgszeilen, und Ausgleiche offener Posten anteilig mit den
     * Kategorien des Ursprungsbelegs. Die Ausgleichsbuchung selbst trägt nur
     * Bestandskonten und fällt daher nicht doppelt ins Gewicht.
     *
     * Abschreibungen kommen nicht aus Zahlungen: § 4 Abs. 3 S. 3 EStG nimmt
     * das Anlagevermögen von § 11 aus. Sie werden aus dem Journal gelesen und
     * als manuell zu prüfen gekennzeichnet.
     *
     * @return array{rows: list<array<string, mixed>>, income: string, expense: string, result: string, not_deductible: string, unclear: list<string>}
     */
    public function euerPreview(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        /** @var array<string, array{gross: string, deductible: string}> $buckets */
        $buckets = [];
        /** @var array<int, AccountingAccount> $unclearAccounts */
        $unclearAccounts = [];

        $collect = function (AccountingAccount $account, string $signed) use (&$buckets, &$unclearAccounts): void {
            $category = $account->euer_category;
            if (! $category instanceof EuerCategory) {
                if (in_array($account->type, [AccountType::Income, AccountType::Expense], true)) {
                    $unclearAccounts[$account->id] = $account;
                }

                return;
            }

            $key = $category->value;
            $buckets[$key] ??= ['gross' => '0.00', 'deductible' => '0.00'];
            $buckets[$key]['gross'] = $this->add($buckets[$key]['gross'], $signed);
            $buckets[$key]['deductible'] = $this->add(
                $buckets[$key]['deductible'],
                $this->scale($signed, $account->deductibleFactor()),
            );
        };

        foreach ($this->cashEffectiveEntries($organization, $from, $to) as $entry) {
            foreach ($entry->lines as $line) {
                $account = $line->account;
                if (! $account instanceof AccountingAccount || $account->is_bank || $account->is_cash) {
                    continue;
                }

                $collect($account, $this->directionalAmount($account, $line->debit?->getAmount() ?? '0.00', $line->credit?->getAmount() ?? '0.00'));
            }
        }

        $unclear = [];

        foreach ($this->settlementsInPeriod($organization, $from, $to) as $settlement) {
            $item = $settlement->openItem;
            if (! $item instanceof AccountingOpenItem) {
                $unclear[] = (string) __('accounting.reports.unclear.settlement_without_item', ['id' => (string) $settlement->id]);

                continue;
            }

            $entry = $item->entry;
            $original = (float) ($item->original_amount?->getAmount() ?? '0.00');
            if ($entry === null || $original === 0.0) {
                $unclear[] = (string) __('accounting.reports.unclear.settlement_without_source', ['id' => (string) $settlement->id]);

                continue;
            }

            $sign = $settlement->kind === SettlementKind::Reversal ? -1.0 : 1.0;
            $ratio = ((float) ($settlement->amount?->getAmount() ?? '0.00')) / $original * $sign;

            foreach ($entry->lines as $line) {
                $account = $line->account;
                if (! $account instanceof AccountingAccount || $account->is_open_item || $account->is_bank || $account->is_cash) {
                    continue;
                }

                $amount = $this->directionalAmount($account, $line->debit?->getAmount() ?? '0.00', $line->credit?->getAmount() ?? '0.00');
                $collect($account, $this->scale($amount, $ratio));
            }
        }

        // Abschreibungen stammen aus dem Journal, nicht aus dem Zahlungsstrom.
        $bookedOnly = $this->sumsByAccount($organization, $from, $to);
        $depreciationAccounts = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->where('euer_category', EuerCategory::Depreciation->value)
            ->get();

        foreach ($depreciationAccounts as $account) {
            $signed = $this->directionalAmount(
                $account,
                $bookedOnly[$account->id]['debit'] ?? '0.00',
                $bookedOnly[$account->id]['credit'] ?? '0.00',
            );
            if ((float) $signed === 0.0) {
                continue;
            }

            $buckets[EuerCategory::Depreciation->value] ??= ['gross' => '0.00', 'deductible' => '0.00'];
            $buckets[EuerCategory::Depreciation->value]['gross'] = $this->add($buckets[EuerCategory::Depreciation->value]['gross'], $signed);
            $buckets[EuerCategory::Depreciation->value]['deductible'] = $this->add(
                $buckets[EuerCategory::Depreciation->value]['deductible'],
                $this->scale($signed, $account->deductibleFactor()),
            );
        }

        $rows = [];
        $income = '0.00';
        $expense = '0.00';
        $notDeductible = '0.00';

        foreach (EuerCategory::cases() as $category) {
            $bucket = $buckets[$category->value] ?? null;
            if ($bucket === null || ((float) $bucket['gross'] === 0.0 && (float) $bucket['deductible'] === 0.0)) {
                continue;
            }

            // Der nicht abziehbare Rest ist keine stille Differenz — er wird
            // ausgewiesen, damit die Kürzung nachvollziehbar bleibt.
            $rest = $this->sub($bucket['gross'], $bucket['deductible']);

            $rows[] = [
                'category' => $category,
                'gross' => $bucket['gross'],
                'deductible' => $bucket['deductible'],
                'not_deductible' => $rest,
                'manual' => ! $category->derivedFromPayments(),
            ];

            if ($category->isIncome()) {
                $income = $this->add($income, $bucket['deductible']);
            } elseif ($category->isExpense()) {
                $expense = $this->add($expense, $bucket['deductible']);
                $notDeductible = $this->add($notDeductible, $rest);
            } else {
                $notDeductible = $this->add($notDeductible, $bucket['gross']);
            }
        }

        foreach ($unclearAccounts as $account) {
            $unclear[] = (string) __('accounting.reports.unclear.account_without_category', ['account' => $account->displayLabel()]);
        }

        $openCount = AccountingOpenItem::query()
            ->where('organization_id', $organization->id)
            ->stillOpen()
            ->count();

        if ($openCount > 0) {
            $unclear[] = (string) __('accounting.reports.unclear.open_items', ['count' => $openCount]);
        }

        return [
            'rows' => $rows,
            'income' => $income,
            'expense' => $expense,
            'result' => $this->sub($income, $expense),
            'not_deductible' => $notDeductible,
            'unclear' => $unclear,
        ];
    }

    /**
     * Buchungen im Zeitraum, die ein Geld- oder Kassenkonto berühren.
     *
     * @return Collection<int, AccountingEntry>
     */
    private function cashEffectiveEntries(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): Collection {
        return AccountingEntry::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', self::POSTED)
            ->whereDate('booked_on', '>=', $from->toDateString())
            ->whereDate('booked_on', '<=', $to->toDateString())
            ->whereHas('lines.account', function (Builder $query): void {
                $query->where(function (Builder $inner): void {
                    $inner->where('is_bank', true)->orWhere('is_cash', true);
                });
            })
            ->with('lines.account')
            ->get();
    }

    /**
     * @return Collection<int, AccountingOpenItemSettlement>
     */
    private function settlementsInPeriod(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): Collection {
        return AccountingOpenItemSettlement::query()
            ->where('organization_id', $organization->id)
            ->whereDate('booked_on', '>=', $from->toDateString())
            ->whereDate('booked_on', '<=', $to->toDateString())
            ->with(['openItem.entry.lines.account'])
            ->get();
    }

    /**
     * Betrag in Kontorichtung: Einnahmen stehen im Haben, Ausgaben im Soll.
     */
    private function directionalAmount(AccountingAccount $account, string $debit, string $credit): string {
        $isIncome = $account->euer_category?->isIncome() ?? ($account->type === AccountType::Income);

        return $isIncome ? $this->sub($credit, $debit) : $this->sub($debit, $credit);
    }

    /** Anteilige Skalierung mit kaufmännischer Rundung auf zwei Stellen. */
    private function scale(string $amount, float $factor): string {
        return number_format((float) $amount * $factor, 2, '.', '');
    }

    /**
     * Ergebnisrechnung nach Kontengruppen — ausdrücklich keine testierte GuV.
     *
     * @return array{income: list<array<string, mixed>>, expense: list<array<string, mixed>>, income_total: string, expense_total: string, result: string}
     */
    public function profitAndLoss(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $sums = $this->sumsByAccount($organization, $from, $to);
        $accounts = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->whereIn('type', [AccountType::Income->value, AccountType::Expense->value])
            ->orderBy('number')
            ->get();

        $income = [];
        $expense = [];
        $incomeTotal = '0.00';
        $expenseTotal = '0.00';

        foreach ($accounts as $account) {
            $debit = $sums[$account->id]['debit'] ?? '0.00';
            $credit = $sums[$account->id]['credit'] ?? '0.00';
            if ((float) $debit === 0.0 && (float) $credit === 0.0) {
                continue;
            }

            if ($account->type === AccountType::Income) {
                $amount = $this->sub($credit, $debit);
                $income[] = ['account' => $account, 'amount' => $amount];
                $incomeTotal = $this->add($incomeTotal, $amount);

                continue;
            }

            $amount = $this->sub($debit, $credit);
            $expense[] = ['account' => $account, 'amount' => $amount];
            $expenseTotal = $this->add($expenseTotal, $amount);
        }

        return [
            'income' => $income,
            'expense' => $expense,
            'income_total' => $incomeTotal,
            'expense_total' => $expenseTotal,
            'result' => $this->sub($incomeTotal, $expenseTotal),
        ];
    }

    /**
     * Bank/Kasse und Liquidität: Ist-Salden und erwartete Bewegungen bleiben
     * **getrennt** — eine Summe aus beidem wäre eine Prognose, die aussieht
     * wie ein Kontostand.
     *
     * @return array{accounts: list<array<string, mixed>>, cash_total: string, receivable: string, payable: string, forecast: string}
     */
    public function liquidity(Organization $organization, CarbonImmutable $asOf): array {
        $sums = $this->sumsByAccount($organization, null, $asOf);
        $accounts = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->where(fn ($query) => $query->where('is_bank', true)->orWhere('is_cash', true))
            ->orderBy('number')
            ->get();

        $rows = [];
        $cashTotal = '0.00';
        foreach ($accounts as $account) {
            $balance = $this->sub($sums[$account->id]['debit'] ?? '0.00', $sums[$account->id]['credit'] ?? '0.00');
            $rows[] = ['account' => $account, 'balance' => $balance];
            $cashTotal = $this->add($cashTotal, $balance);
        }

        $receivable = $this->openTotal($organization, OpenItemDirection::Receivable);
        $payable = $this->openTotal($organization, OpenItemDirection::Payable);

        return [
            'accounts' => $rows,
            'cash_total' => $cashTotal,
            'receivable' => $receivable,
            'payable' => $payable,
            'forecast' => $this->sub($this->add($cashTotal, $receivable), $payable),
        ];
    }

    /**
     * Buchungsqualität: was der Auswertung im Weg steht.
     *
     * @return array{drafts: int, unbalanced: int, blocked_runs: int, open_expectations: int, accounts_without_rule: int, ten_day_cases: int, open_clearing: int, overdue_filings: int, findings: list<string>}
     */
    public function dataQuality(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $drafts = AccountingEntry::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [AccountingEntryStatus::Draft->value, AccountingEntryStatus::Ready->value])
            ->whereDate('booked_on', '>=', $from->toDateString())
            ->whereDate('booked_on', '<=', $to->toDateString())
            ->count();

        $unbalanced = 0;
        $entries = AccountingEntry::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [AccountingEntryStatus::Draft->value, AccountingEntryStatus::Ready->value])
            ->with('lines')
            ->get();
        foreach ($entries as $entry) {
            if (! $entry->isBalanced()) {
                $unbalanced++;
            }
        }

        $blockedRuns = AccountingRecurringRun::query()
            ->where('organization_id', $organization->id)
            ->where('status', RecurringRunStatus::Blocked->value)
            ->count();

        $openExpectations = AccountingRecurringRun::query()
            ->where('organization_id', $organization->id)
            ->where('status', RecurringRunStatus::Expected->value)
            ->whereDate('due_on', '<=', $to->toDateString())
            ->count();

        $tenDayCases = $this->tenDayRuleCases($organization, $from, $to);
        $openClearing = $this->unresolvedClearingAccounts($organization, $to);

        // Überschrittene Meldefristen (MVP-686) — sie kosten Verspätungszuschlag.
        $overdueFilings = AccountingFilingObligation::query()
            ->where('organization_id', $organization->id)
            ->open()
            ->whereDate('due_on', '<', $to->toDateString())
            ->count();

        $findings = [];
        if ($drafts > 0) {
            $findings[] = (string) __('accounting.reports.quality.drafts', ['count' => $drafts]);
        }
        if ($unbalanced > 0) {
            $findings[] = (string) __('accounting.reports.quality.unbalanced', ['count' => $unbalanced]);
        }
        if ($blockedRuns > 0) {
            $findings[] = (string) __('accounting.reports.quality.blocked_runs', ['count' => $blockedRuns]);
        }
        if ($openExpectations > 0) {
            $findings[] = (string) __('accounting.reports.quality.open_expectations', ['count' => $openExpectations]);
        }
        if ($tenDayCases > 0) {
            $findings[] = (string) __('accounting.reports.quality.ten_day_rule', ['count' => $tenDayCases]);
        }
        if ($openClearing > 0) {
            $findings[] = (string) __('accounting.reports.quality.open_clearing', ['count' => $openClearing]);
        }
        if ($overdueFilings > 0) {
            $findings[] = (string) __('accounting.reports.quality.overdue_filings', ['count' => $overdueFilings]);
        }

        return [
            'drafts' => $drafts,
            'unbalanced' => $unbalanced,
            'blocked_runs' => $blockedRuns,
            'open_expectations' => $openExpectations,
            'accounts_without_rule' => 0,
            'ten_day_cases' => $tenDayCases,
            'open_clearing' => $openClearing,
            'overdue_filings' => $overdueFilings,
            'findings' => $findings,
        ];
    }

    /**
     * Klärungskonten mit Restsaldo (Feature 125, MVP-681).
     *
     * Eine Klärungsbuchung ist erst erledigt, wenn das Konto wieder auf null
     * steht. Bis dahin steht sie im Bericht — sonst wäre das Klärungskonto
     * genau das Auffangbecken, das es nicht sein soll.
     */
    private function unresolvedClearingAccounts(Organization $organization, CarbonImmutable $to): int {
        $accounts = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->where('is_clearing', true)
            ->pluck('id');

        if ($accounts->isEmpty()) {
            return 0;
        }

        $sums = $this->sumsByAccount($organization, null, $to);
        $open = 0;

        foreach ($accounts as $id) {
            $balance = $this->sub($sums[$id]['debit'] ?? '0.00', $sums[$id]['credit'] ?? '0.00');
            if ((float) $balance !== 0.0) {
                $open++;
            }
        }

        return $open;
    }

    /**
     * Zahlungen im Fenster 22.12.–10.01., deren Beleg aus dem Nachbarjahr stammt.
     *
     * § 11 Abs. 1 S. 2 EStG ordnet regelmäßig wiederkehrende Zahlungen um den
     * Jahreswechsel dem wirtschaftlich zugehörigen Jahr zu. Ob eine Zahlung
     * „regelmäßig wiederkehrend" ist, entscheidet der Sachverhalt — das
     * Programm zeigt die Kandidaten und bucht **nicht** automatisch um.
     */
    private function tenDayRuleCases(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): int {
        $cases = 0;

        foreach ($this->settlementsInPeriod($organization, $from, $to) as $settlement) {
            $paidOn = $settlement->booked_on;
            $inWindow = ($paidOn->month === 12 && $paidOn->day >= 22) || ($paidOn->month === 1 && $paidOn->day <= 10);
            if (! $inWindow) {
                continue;
            }

            $entry = $settlement->openItem?->entry;
            if ($entry === null) {
                continue;
            }

            $documentOn = $entry->document_on ?? $entry->booked_on;
            if ($documentOn->year === $paidOn->year) {
                continue;
            }

            foreach ($entry->lines as $line) {
                if ($line->account?->euer_category?->subjectToTenDayRule() === true) {
                    $cases++;

                    break;
                }
            }
        }

        return $cases;
    }

    /**
     * Kopfzeile für Exporte: Methode, Zeitraum und Datenstand gehören dazu.
     *
     * @return array<string, mixed>
     */
    public function exportContext(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $profile = AccountingProfile::query()->where('organization_id', $organization->id)->first();

        $taxation = $this->taxation->at($organization, $to);

        return [
            'organization' => $organization->name,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'profile' => $profile?->profit_determination->value ?? '—',
            'profile_label' => $profile?->profit_determination->label() ?? '—',
            'currency' => $profile?->base_currency->value ?? 'EUR',
            'taxation' => $taxation->value,
            'taxation_label' => $taxation->label(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Soll-/Habensummen je Konto im Zeitraum — die einzige Aggregationsstelle
     * der Berichte.
     *
     * @return array<int, array{debit: string, credit: string}>
     */
    private function sumsByAccount(Organization $organization, ?CarbonImmutable $from, CarbonImmutable $to, ?int $accountId = null): array {
        $query = AccountingEntryLine::query()
            ->select('accounting_account_id')
            ->selectRaw('SUM(debit) as debit_sum')
            ->selectRaw('SUM(credit) as credit_sum')
            ->where('accounting_entry_lines.organization_id', $organization->id)
            ->whereExists(function ($sub) use ($from, $to): void {
                $sub->select(DB::raw(1))
                    ->from('accounting_entries')
                    ->whereColumn('accounting_entries.id', 'accounting_entry_lines.accounting_entry_id')
                    ->whereIn('accounting_entries.status', self::POSTED)
                    ->whereDate('accounting_entries.booked_on', '<=', $to->toDateString());

                if ($from !== null) {
                    $sub->whereDate('accounting_entries.booked_on', '>=', $from->toDateString());
                }
            })
            ->groupBy('accounting_account_id');

        if ($accountId !== null) {
            $query->where('accounting_account_id', $accountId);
        }

        $result = [];
        foreach ($query->get() as $row) {
            $result[(int) $row->accounting_account_id] = [
                'debit' => number_format((float) $row->getAttribute('debit_sum'), 2, '.', ''),
                'credit' => number_format((float) $row->getAttribute('credit_sum'), 2, '.', ''),
            ];
        }

        return $result;
    }

    private function openTotal(Organization $organization, OpenItemDirection $direction): string {
        $sum = AccountingOpenItem::query()
            ->where('organization_id', $organization->id)
            ->where('direction', $direction->value)
            ->stillOpen()
            ->sum('open_amount');

        return number_format((float) $sum, 2, '.', '');
    }

    private function add(string $a, string $b): string {
        return number_format((float) $a + (float) $b, 2, '.', '');
    }

    private function sub(string $a, string $b): string {
        return number_format((float) $a - (float) $b, 2, '.', '');
    }
}
