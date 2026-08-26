<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BwaBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Reports;

use App\Enums\Finance\{AccountType, BwaGroup};
use App\Models\Accounting\AccountingAccount;
use App\Models\Organization;
use App\Services\Accounting\{AccountingBudgetService, FiscalCalendar};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Betriebswirtschaftliche Auswertung (Feature 142, MVP-709).
 *
 * Gliederung: Umsatzerlöse + Bestandsveränderung = Gesamtleistung;
 * − Materialaufwand = Rohertrag; + sonstige betriebliche Erlöse =
 * betrieblicher Rohertrag; − Kosten = Betriebsergebnis; ± neutrales Ergebnis
 * = Ergebnis vor Steuern; − Steuern vom Einkommen = vorläufiges Ergebnis.
 *
 * Konten ohne Zuordnung ({@see BwaAccountMapper}) fließen in **keine**
 * Gruppe, stehen aber sichtbar unter „nicht zugeordnet" und in der Schluss-
 * zeile „Ergebnis inkl. nicht zugeordneter Konten" — die stimmt mit der
 * Ergebnisrechnung überein, die BWA verschluckt nichts.
 *
 * Alle Zahlen sind Strings mit zwei Nachkommastellen (bc), nie float.
 *
 * @phpstan-type BwaValues array<string, numeric-string>
 * @phpstan-type BwaRow array{kind: string, key: string, label: string, values: BwaValues, delta: numeric-string|null, delta_pct: numeric-string|null, account: AccountingAccount|null, group: BwaGroup|null, depth: int, emphasis: bool}
 */
class BwaBuilder extends AbstractAccountingReportBuilder {
    public const COL_ACTUAL = 'actual';

    public const COL_COMPARE = 'compare';

    public const COL_TOTAL = 'total';

    /** Zwischensummen in Berichtsreihenfolge: nach welchem Abschnitt sie stehen. */
    private const SUBTOTALS_AFTER = [
        'output' => ['total_output'],
        'material' => ['gross_profit'],
        'other_income' => ['operating_gross_profit'],
        'costs' => ['total_costs', 'operating_result'],
        'neutral' => ['result_before_tax'],
        'taxes' => ['result'],
    ];

    public function __construct(
        private readonly BwaAccountMapper $mapper,
        private readonly AccountingBudgetService $budgets,
        private readonly FiscalCalendar $calendar,
    ) {}

    /**
     * @return array{scheme: string|null, compare: string, compare_range: array{0: CarbonImmutable, 1: CarbonImmutable}|null, columns: list<array{key: string, label: string}>, rows: list<BwaRow>, groups: array<string, BwaValues>, subtotals: array<string, BwaValues>, unmapped_count: int}
     */
    public function build(Organization $organization, CarbonImmutable $from, CarbonImmutable $to, string $compare = self::COMPARE_NONE, ?int $costCenterId = null): array {
        $compare = in_array($compare, self::COMPARE_MODES, true) ? $compare : self::COMPARE_NONE;

        $accounts = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->where(fn ($q) => $q->whereIn('type', [AccountType::Income->value, AccountType::Expense->value])->orWhereNotNull('bwa_group'))
            ->orderBy('number')
            ->get();
        $scheme = $this->mapper->detectScheme($accounts);

        // Spalten und ihre Rohwerte je Konto.
        $columns = [];
        $rawByColumn = [];
        $compareRange = $this->comparisonRange($from, $to, $compare);

        if ($compare === self::COMPARE_MONTHS) {
            $startMonth = $this->calendar->startMonth($organization);
            $months = $this->calendar->monthsOf($this->calendar->fiscalYearOf($from, $startMonth), $startMonth);
            foreach ($months as $month) {
                $key = $month->format('Y-m');
                $columns[] = ['key' => $key, 'label' => $month->translatedFormat('M Y')];
                $rawByColumn[$key] = $this->sumsByAccount($organization, $month, $month->endOfMonth()->startOfDay(), null, $costCenterId);
            }
            $columns[] = ['key' => self::COL_TOTAL, 'label' => (string) __('accounting.bwa.column.total')];
        } else {
            $columns[] = ['key' => self::COL_ACTUAL, 'label' => (string) __('accounting.bwa.column.actual')];
            $rawByColumn[self::COL_ACTUAL] = $this->sumsByAccount($organization, $from, $to, null, $costCenterId);

            if ($compareRange !== null) {
                $columns[] = ['key' => self::COL_COMPARE, 'label' => (string) __('accounting.bwa.compare.' . $compare)];
                $rawByColumn[self::COL_COMPARE] = $this->sumsByAccount($organization, $compareRange[0], $compareRange[1], null, $costCenterId);
            } elseif ($compare === self::COMPARE_BUDGET) {
                $columns[] = ['key' => self::COL_COMPARE, 'label' => (string) __('accounting.bwa.column.budget')];
            }
        }

        $planned = $compare === self::COMPARE_BUDGET ? $this->budgets->plannedByAccount($organization, $from, $to, $costCenterId) : [];
        $valueKeys = array_values(array_filter(array_column($columns, 'key'), static fn (string $k): bool => $k !== self::COL_TOTAL));
        $hasDelta = $compare !== self::COMPARE_MONTHS && $compare !== self::COMPARE_NONE;

        // Konto → Werte je Spalte im Vorzeichen seiner Gruppe.
        /** @var array<string, list<array{account: AccountingAccount, values: BwaValues}>> $accountsByGroup */
        $accountsByGroup = [];
        /** @var list<array{account: AccountingAccount, values: BwaValues}> $unmapped */
        $unmapped = [];

        foreach ($accounts as $account) {
            $group = $this->mapper->groupFor($account, $scheme);
            $incomeSign = $group instanceof BwaGroup ? $group->isIncome() : $account->type === AccountType::Income;
            $values = [];
            $any = false;
            foreach ($valueKeys as $key) {
                if ($key === self::COL_COMPARE && $compare === self::COMPARE_BUDGET) {
                    $plan = $planned[$account->id] ?? '0.00';
                    // Budgets tragen das Vorzeichen der Kontoart; die Gruppe
                    // kann entgegengesetzt sein (Erlösschmälerung als Aufwandskonto).
                    $value = ($account->type === AccountType::Income) === $incomeSign ? $plan : NumberHelper::negatePrecise($plan);
                } else {
                    $sums = $rawByColumn[$key][$account->id] ?? null;
                    $debit = $sums['debit'] ?? '0.00';
                    $credit = $sums['credit'] ?? '0.00';
                    $value = $incomeSign
                        ? NumberHelper::subtractPrecise($credit, $debit, 2)
                        : NumberHelper::subtractPrecise($debit, $credit, 2);
                }
                $values[$key] = $value;
                $any = $any || ! NumberHelper::isZeroPrecise($value);
            }
            if (! $any) {
                continue;
            }
            if ($compare === self::COMPARE_MONTHS) {
                $values[self::COL_TOTAL] = NumberHelper::sumPrecise(array_values($values), 2);
            }

            if ($group instanceof BwaGroup) {
                $accountsByGroup[$group->value][] = ['account' => $account, 'values' => $values];
            } else {
                $unmapped[] = ['account' => $account, 'values' => $values];
            }
        }

        $allKeys = array_column($columns, 'key');
        $zero = array_fill_keys($allKeys, '0.00');

        // Gruppensummen.
        $groupValues = array_fill_keys(array_map(static fn (BwaGroup $g): string => $g->value, BwaGroup::cases()), $zero);
        foreach (BwaGroup::cases() as $group) {
            foreach ($accountsByGroup[$group->value] ?? [] as $row) {
                $groupValues[$group->value] = $this->addValues($groupValues[$group->value], $row['values']);
            }
        }

        // Zwischensummen.
        $sub = [];
        $sub['total_output'] = $this->addValues($groupValues[BwaGroup::Revenue->value], $groupValues[BwaGroup::InventoryChange->value]);
        $sub['gross_profit'] = $this->subValues($sub['total_output'], $groupValues[BwaGroup::Material->value]);
        $sub['operating_gross_profit'] = $this->addValues($sub['gross_profit'], $groupValues[BwaGroup::OtherOperatingIncome->value]);
        $sub['total_costs'] = $zero;
        foreach (BwaGroup::cases() as $group) {
            if ($group->section() === 'costs') {
                $sub['total_costs'] = $this->addValues($sub['total_costs'], $groupValues[$group->value]);
            }
        }
        $sub['operating_result'] = $this->subValues($sub['operating_gross_profit'], $sub['total_costs']);
        $neutral = $this->addValues($groupValues[BwaGroup::InterestIncome->value], $groupValues[BwaGroup::NeutralIncome->value]);
        $neutral = $this->subValues($neutral, $groupValues[BwaGroup::InterestExpense->value]);
        $neutral = $this->subValues($neutral, $groupValues[BwaGroup::NeutralExpense->value]);
        $sub['result_before_tax'] = $this->addValues($sub['operating_result'], $neutral);
        $sub['result'] = $this->subValues($sub['result_before_tax'], $groupValues[BwaGroup::IncomeTaxes->value]);

        $unmappedNet = $zero;
        foreach ($unmapped as $row) {
            $signed = $row['account']->type === AccountType::Income ? $row['values'] : array_map(static fn (string $v): string => NumberHelper::negatePrecise($v), $row['values']);
            $unmappedNet = $this->addValues($unmappedNet, $signed);
        }
        $sub['unmapped_net'] = $unmappedNet;
        $sub['result_total'] = $this->addValues($sub['result'], $unmappedNet);

        // Anzeigezeilen in Berichtsreihenfolge.
        $rows = [];
        $section = null;
        foreach (BwaGroup::cases() as $group) {
            if ($section !== null && $section !== $group->section()) {
                foreach (self::SUBTOTALS_AFTER[$section] ?? [] as $key) {
                    $rows[] = $this->row('subtotal', $key, (string) __('accounting.bwa.subtotal.' . $key), $sub[$key], $hasDelta, emphasis: true);
                }
            }
            $section = $group->section();

            $rows[] = $this->row('group', $group->value, $group->label(), $groupValues[$group->value], $hasDelta, group: $group);
            foreach ($accountsByGroup[$group->value] ?? [] as $entry) {
                $rows[] = $this->row('account', 'account:' . $entry['account']->id, $entry['account']->displayLabel(), $entry['values'], $hasDelta, account: $entry['account'], group: $group, depth: 1);
            }
        }
        foreach (self::SUBTOTALS_AFTER[$section] ?? [] as $key) {
            $rows[] = $this->row('subtotal', $key, (string) __('accounting.bwa.subtotal.' . $key), $sub[$key], $hasDelta, emphasis: true);
        }

        if ($unmapped !== []) {
            $rows[] = $this->row('unmapped', 'unmapped', (string) __('accounting.bwa.unmapped.title'), $unmappedNet, $hasDelta);
            foreach ($unmapped as $entry) {
                $rows[] = $this->row('account', 'unmapped:' . $entry['account']->id, $entry['account']->displayLabel(), $entry['values'], $hasDelta, account: $entry['account'], depth: 1);
            }
            $rows[] = $this->row('subtotal', 'result_total', (string) __('accounting.bwa.subtotal.result_total'), $sub['result_total'], $hasDelta, emphasis: true);
        }

        return [
            'scheme' => $scheme,
            'compare' => $compare,
            'compare_range' => $compareRange,
            'columns' => $columns,
            'rows' => $rows,
            'groups' => $groupValues,
            'subtotals' => $sub,
            'unmapped_count' => count($unmapped),
        ];
    }

    /**
     * @param  BwaValues  $values
     * @return BwaRow
     */
    private function row(string $kind, string $key, string $label, array $values, bool $hasDelta, ?AccountingAccount $account = null, ?BwaGroup $group = null, int $depth = 0, bool $emphasis = false): array {
        $delta = $hasDelta ? $this->deltaOf($values[self::COL_ACTUAL] ?? '0.00', $values[self::COL_COMPARE] ?? '0.00') : null;

        return [
            'kind' => $kind,
            'key' => $key,
            'label' => $label,
            'values' => $values,
            'delta' => $delta['delta'] ?? null,
            'delta_pct' => $delta['delta_pct'] ?? null,
            'account' => $account,
            'group' => $group,
            'depth' => $depth,
            'emphasis' => $emphasis,
        ];
    }

    /**
     * @param  BwaValues  $a
     * @param  BwaValues  $b
     * @return BwaValues
     */
    private function addValues(array $a, array $b): array {
        foreach ($b as $key => $value) {
            $a[$key] = NumberHelper::addPrecise($a[$key] ?? '0.00', $value, 2);
        }

        return $a;
    }

    /**
     * @param  BwaValues  $a
     * @param  BwaValues  $b
     * @return BwaValues
     */
    private function subValues(array $a, array $b): array {
        foreach ($b as $key => $value) {
            $a[$key] = NumberHelper::subtractPrecise($a[$key] ?? '0.00', $value, 2);
        }

        return $a;
    }
}
