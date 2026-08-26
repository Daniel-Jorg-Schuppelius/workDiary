<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingBudgetService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\AccountType;
use App\Models\Accounting\{AccountingAccount, AccountingBudget, AccountingFiscalYear};
use App\Models\{CostCenter, Organization, User};
use App\Services\Accounting\Reports\MonthlyActualsBuilder;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Konto-Budgets (Feature 142, MVP-709) — einzige Schreibstelle für
 * `accounting_budgets`.
 *
 * Ein Budget ist ein Planwert je Konto, Geschäftsjahr und (optional)
 * Kostenstelle: entweder **ein Jahreswert** (Monat 0, wird für Monats-
 * vergleiche gleichmäßig verteilt — Rest-Cents deterministisch über
 * `NumberHelper::allocateEvenly`) oder **zwölf Monatswerte**. Beides
 * zugleich gibt es nicht; {@see self::save()} ersetzt den Satz.
 *
 * Vorzeichen: in der natürlichen Richtung der Kontoart (Ertrag positiv,
 * Aufwand positiv). Ohne Kostenstellen-Filter zählt die Summe aller Budgets
 * des Kontos — Kostenstellen-Budgets sind Teile des Ganzen, keine Kopie.
 */
class AccountingBudgetService {
    public const MODE_YEAR = 'year';

    public const MODE_MONTHS = 'months';

    public function __construct(
        private readonly FiscalCalendar $calendar,
        private readonly MonthlyActualsBuilder $actuals,
        private readonly JournalService $journal,
    ) {}

    /**
     * Planwerte je Konto für einen Zeitraum — monatsgenau: Ein angebrochener
     * Monat zählt voll, ein Budget ist keine Tagesgröße.
     *
     * @return array<int, numeric-string>
     */
    public function plannedByAccount(Organization $organization, CarbonImmutable $from, CarbonImmutable $to, ?int $costCenterId = null): array {
        $startMonth = $this->calendar->startMonth($organization);
        $months = $this->monthsBetween($from, $to);
        if ($months === []) {
            return [];
        }

        $years = array_values(array_unique(array_map(fn (CarbonImmutable $m): int => $this->calendar->fiscalYearOf($m, $startMonth), $months)));
        $budgets = $this->budgets($organization, $years, $costCenterId);

        $planned = [];
        foreach ($budgets as $budget) {
            $accountId = (int) $budget->accounting_account_id;
            $amount = $budget->amount->getAmount();
            $shares = $budget->month === 0 ? NumberHelper::allocateEvenly($amount, 12, 2) : null;

            foreach ($months as $month) {
                if ($this->calendar->fiscalYearOf($month, $startMonth) !== (int) $budget->fiscal_year) {
                    continue;
                }
                $value = $shares !== null
                    ? $shares[$this->calendar->positionOf($month->month, $startMonth)]
                    : ($budget->month === $month->month ? $amount : null);
                if ($value === null) {
                    continue;
                }
                $planned[$accountId] = NumberHelper::addPrecise($planned[$accountId] ?? '0.00', $value, 2);
            }
        }

        return $planned;
    }

    /**
     * Pflegeansicht: Konto × Jahreswert/Monate eines Geschäftsjahres.
     *
     * @return array{start_month: int, months: list<CarbonImmutable>, rows: list<array<string, mixed>>, total: numeric-string}
     */
    public function matrix(Organization $organization, int $fiscalYear, ?int $costCenterId = null): array {
        $startMonth = $this->calendar->startMonth($organization);
        $months = $this->calendar->monthsOf($fiscalYear, $startMonth);
        $budgets = $this->budgets($organization, [$fiscalYear], $costCenterId)->groupBy('accounting_account_id');

        $rows = [];
        $total = '0.00';
        foreach ($this->budgetableAccounts($organization) as $account) {
            /** @var Collection<int, AccountingBudget> $own */
            $own = $budgets->get($account->id, collect());
            $year = $own->first(fn (AccountingBudget $b): bool => $b->month === 0);
            $monthValues = [];
            $sum = '0.00';
            foreach ($months as $month) {
                $rowsOfMonth = $own->filter(fn (AccountingBudget $b): bool => $b->month === $month->month);
                $value = $rowsOfMonth->isEmpty() ? null : NumberHelper::sumPrecise($rowsOfMonth->map(fn (AccountingBudget $b): string => $b->amount->getAmount())->all(), 2);
                $monthValues[$month->month] = $value;
                $sum = NumberHelper::addPrecise($sum, $value ?? '0.00', 2);
            }

            // Ohne Kostenstellen-Filter können Jahres- und Monatswerte
            // verschiedener Kostenstellen nebeneinander stehen — die Summe
            // ist dann die Wahrheit, der Modus nur ein Hinweis.
            $yearAmount = $year?->amount->getAmount();
            $rowTotal = NumberHelper::addPrecise($sum, $yearAmount ?? '0.00', 2);
            $mode = $year !== null && NumberHelper::isZeroPrecise($sum) ? self::MODE_YEAR : ($own->isEmpty() ? null : self::MODE_MONTHS);

            $rows[] = [
                'account' => $account,
                'mode' => $mode,
                'year' => $yearAmount,
                'months' => $monthValues,
                'total' => $rowTotal,
                'note' => $own->first()?->note,
            ];
            $total = NumberHelper::addPrecise($total, $account->type === AccountType::Income ? $rowTotal : NumberHelper::negatePrecise($rowTotal), 2);
        }

        return ['start_month' => $startMonth, 'months' => $months, 'rows' => $rows, 'total' => $total];
    }

    /**
     * Budget eines Kontos setzen — als Jahreswert oder als zwölf Monatswerte.
     * Ersetzt den bisherigen Satz für Konto × Jahr × Kostenstelle.
     *
     * @param  array{mode: string, year_amount?: string|null, months?: array<int|string, string|null>, note?: string|null}  $data
     */
    public function save(Organization $organization, AccountingAccount $account, int $fiscalYear, ?int $costCenterId, array $data, User $actor): void {
        $this->assertOwn($organization, $account, $costCenterId);
        $currency = $this->journal->baseCurrency($organization);

        $rows = [];
        if ($data['mode'] === self::MODE_YEAR) {
            $rows[0] = $this->normalize($data['year_amount'] ?? null) ?? '0.00';
        } else {
            foreach ($data['months'] ?? [] as $month => $value) {
                $normalized = $this->normalize($value);
                if ($normalized === null) {
                    continue;
                }
                $month = (int) $month;
                if ($month < 1 || $month > 12) {
                    continue;
                }
                $rows[$month] = $normalized;
            }
        }

        $note = isset($data['note']) && trim((string) $data['note']) !== '' ? mb_substr(trim((string) $data['note']), 0, 191) : null;

        DB::transaction(function () use ($organization, $account, $fiscalYear, $costCenterId, $rows, $note, $currency, $actor): void {
            $this->scope($organization, $fiscalYear, $costCenterId)
                ->where('accounting_account_id', $account->id)
                ->delete();

            foreach ($rows as $month => $amount) {
                AccountingBudget::query()->create([
                    'organization_id' => $organization->id,
                    'fiscal_year' => $fiscalYear,
                    'accounting_account_id' => $account->id,
                    'cost_center_id' => $costCenterId,
                    'month' => $month,
                    'amount' => $amount,
                    'currency' => $currency,
                    'note' => $note,
                    'created_by' => $actor->id,
                ]);
            }
        });
    }

    /**
     * „Vorjahr-Ist als Budget": Monatsbeträge des vorherigen Geschäftsjahres
     * werden zu Monatswerten des Zieljahres. Konten ohne Bewegung bleiben
     * ohne Budget; vorhandene Budgets des Zieljahres werden ersetzt.
     *
     * @return int Anzahl übernommener Konten
     */
    public function copyPreviousYearActuals(Organization $organization, int $fiscalYear, ?int $costCenterId, User $actor): int {
        if ($costCenterId !== null) {
            $this->assertOwn($organization, null, $costCenterId);
        }
        $startMonth = $this->calendar->startMonth($organization);
        $previousMonths = $this->calendar->monthsOf($fiscalYear - 1, $startMonth);
        $actuals = $this->actuals->build($organization, $previousMonths, $costCenterId);
        $currency = $this->journal->baseCurrency($organization);

        $copied = 0;
        DB::transaction(function () use ($organization, $fiscalYear, $costCenterId, $previousMonths, $actuals, $currency, $actor, &$copied): void {
            foreach ($this->budgetableAccounts($organization) as $account) {
                $rows = [];
                foreach ($previousMonths as $month) {
                    $sums = $actuals[$month->format('Y-m')][$account->id] ?? null;
                    if ($sums === null) {
                        continue;
                    }
                    $amount = $account->type === AccountType::Income
                        ? NumberHelper::subtractPrecise($sums['credit'], $sums['debit'], 2)
                        : NumberHelper::subtractPrecise($sums['debit'], $sums['credit'], 2);
                    if (! NumberHelper::isZeroPrecise($amount)) {
                        $rows[$month->month] = $amount;
                    }
                }
                if ($rows === []) {
                    continue;
                }

                $this->scope($organization, $fiscalYear, $costCenterId)
                    ->where('accounting_account_id', $account->id)
                    ->delete();
                foreach ($rows as $month => $amount) {
                    AccountingBudget::query()->create([
                        'organization_id' => $organization->id,
                        'fiscal_year' => $fiscalYear,
                        'accounting_account_id' => $account->id,
                        'cost_center_id' => $costCenterId,
                        'month' => $month,
                        'amount' => $amount,
                        'currency' => $currency,
                        'note' => (string) __('accounting.budget.note.copied_from', ['year' => $fiscalYear - 1]),
                        'created_by' => $actor->id,
                    ]);
                }
                $copied++;
            }
        });

        return $copied;
    }

    /**
     * Wählbare Geschäftsjahre: die angelegten plus das laufende — Budgets
     * werden vor dem Jahr gepflegt, nicht erst mit der ersten Buchung.
     *
     * @return list<int>
     */
    public function fiscalYears(Organization $organization, CarbonImmutable $now): array {
        $startMonth = $this->calendar->startMonth($organization);
        $years = AccountingFiscalYear::query()
            ->where('organization_id', $organization->id)
            ->get(['starts_on'])
            ->map(fn (AccountingFiscalYear $y): int => $this->calendar->fiscalYearOf(CarbonImmutable::parse($y->starts_on), $startMonth))
            ->all();
        $current = $this->calendar->fiscalYearOf($now, $startMonth);
        $years[] = $current;
        $years[] = $current + 1;
        $years = array_values(array_unique($years));
        sort($years);

        return $years;
    }

    /**
     * Konten, die ein Budget tragen können: Erfolgskonten der Organisation.
     *
     * @return Collection<int, AccountingAccount>
     */
    public function budgetableAccounts(Organization $organization): Collection {
        return AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->whereIn('type', [AccountType::Income->value, AccountType::Expense->value])
            ->orderBy('number')
            ->get();
    }

    /**
     * @param  list<int>  $fiscalYears
     * @return Collection<int, AccountingBudget>
     */
    private function budgets(Organization $organization, array $fiscalYears, ?int $costCenterId): Collection {
        return AccountingBudget::query()
            ->where('organization_id', $organization->id)
            ->whereIn('fiscal_year', $fiscalYears)
            ->when($costCenterId !== null, fn ($q) => $q->where('cost_center_id', $costCenterId))
            ->get();
    }

    /** @return \Illuminate\Database\Eloquent\Builder<AccountingBudget> */
    private function scope(Organization $organization, int $fiscalYear, ?int $costCenterId): \Illuminate\Database\Eloquent\Builder {
        return AccountingBudget::query()
            ->where('organization_id', $organization->id)
            ->where('fiscal_year', $fiscalYear)
            ->where('cost_center_key', $costCenterId ?? 0);
    }

    /** @return list<CarbonImmutable> */
    private function monthsBetween(CarbonImmutable $from, CarbonImmutable $to): array {
        $months = [];
        $cursor = $from->startOfMonth();
        $end = $to->startOfMonth();
        while ($cursor->lessThanOrEqualTo($end)) {
            $months[] = $cursor;
            $cursor = $cursor->addMonthNoOverflow();
        }

        return $months;
    }

    /** @return numeric-string|null */
    private function normalize(mixed $value): ?string {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return Decimal::of(NumberHelper::normalizeDecimalString(trim((string) $value)), 2)->getValue();
    }

    private function assertOwn(Organization $organization, ?AccountingAccount $account, ?int $costCenterId): void {
        if ($account !== null && (int) $account->organization_id !== (int) $organization->id) {
            throw ValidationException::withMessages(['account' => (string) __('accounting.ledger.error.unknown_account')]);
        }

        if ($costCenterId !== null && ! CostCenter::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereKey($costCenterId)->exists()) {
            throw ValidationException::withMessages(['cost_center' => (string) __('accounting.ledger.error.unknown_cost_center')]);
        }
    }
}
