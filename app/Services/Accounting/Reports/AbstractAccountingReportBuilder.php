<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractAccountingReportBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Reports;

use App\Enums\Finance\{AccountType, AccountingEntryStatus};
use App\Models\Accounting\{AccountingAccount, AccountingEntryLine, AccountingOpenItemSettlement};
use App\Models\Organization;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\RoundingMode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\{Decimal, Money};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gemeinsame Basis der Finanzberichte (Feature 125, MVP-676).
 *
 * Alle Berichte lesen **ausschließlich** festgeschriebene Buchungen
 * (`posted`/`reversed`) — ein Entwurf ist eine Absicht, keine Zahl. Damit
 * liefern Liste, Kennzahl und Export bei gleichen Filtern zwangsläufig
 * dieselbe Grundgesamtheit: Es gibt nur eine Quelle.
 */
abstract class AbstractAccountingReportBuilder {
    /** Zustände, die in eine Auswertung eingehen. */
    protected const POSTED = [AccountingEntryStatus::Posted->value, AccountingEntryStatus::Reversed->value];

    /** Vergleichsmodi der Ergebnisberichte (Feature 142, MVP-709). */
    public const COMPARE_NONE = 'none';

    public const COMPARE_PREVIOUS_YEAR = 'previous_year';

    public const COMPARE_PREVIOUS_MONTH = 'previous_month';

    public const COMPARE_MONTHS = 'months';

    public const COMPARE_BUDGET = 'budget';

    public const COMPARE_MODES = [
        self::COMPARE_NONE,
        self::COMPARE_PREVIOUS_YEAR,
        self::COMPARE_PREVIOUS_MONTH,
        self::COMPARE_MONTHS,
        self::COMPARE_BUDGET,
    ];

    /**
     * Soll-/Habensummen je Konto im Zeitraum — die einzige Aggregationsstelle
     * der Berichte. Mit `$costCenterId` zählen nur Zeilen dieser Kostenstelle
     * (Feature 142) — Zeilen ohne Kostenstelle bleiben dann außen vor.
     *
     * @return array<int, array{debit: numeric-string, credit: numeric-string}>
     */
    protected function sumsByAccount(Organization $organization, ?CarbonImmutable $from, CarbonImmutable $to, ?int $accountId = null, ?int $costCenterId = null): array {
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
                    ->where('accounting_entries.booked_on', '<', DateRange::dayAfter($to));

                if ($from !== null) {
                    $sub->where('accounting_entries.booked_on', '>=', DateRange::day($from));
                }
            })
            ->groupBy('accounting_account_id');

        if ($accountId !== null) {
            $query->where('accounting_account_id', $accountId);
        }

        if ($costCenterId !== null) {
            $query->where('accounting_entry_lines.cost_center_id', $costCenterId);
        }

        // SQL-Aggregate kommen je Treiber als String oder float zurück; Decimal
        // kanonisiert sie ohne Float-Rechnung (Vollscan 2026-08-23, C1).
        $result = [];
        foreach ($query->get() as $row) {
            $result[(int) $row->accounting_account_id] = [
                'debit' => Decimal::of((string) $row->getAttribute('debit_sum'), 2)->getValue(),
                'credit' => Decimal::of((string) $row->getAttribute('credit_sum'), 2)->getValue(),
            ];
        }

        return $result;
    }

    /**
     * Vergleichszeitraum zu einem Modus: Vorjahr gleicher Zeitraum oder der
     * Vormonat (Monatsgrenzen bleiben Monatsgrenzen — der 31. Januar wird
     * zum 31. Dezember, der 31. März zum 28. Februar). Monatsraster und
     * Budget haben keinen zweiten Zeitraum.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    protected function comparisonRange(CarbonImmutable $from, CarbonImmutable $to, string $mode): ?array {
        return match ($mode) {
            self::COMPARE_PREVIOUS_YEAR => [$from->subYear(), $to->subYear()],
            self::COMPARE_PREVIOUS_MONTH => [
                $from->subMonthNoOverflow(),
                $to->isLastOfMonth() ? $to->subMonthNoOverflow()->endOfMonth()->startOfDay() : $to->subMonthNoOverflow(),
            ],
            default => null,
        };
    }

    /**
     * Abweichung absolut und in Prozent des Vergleichswerts; ohne
     * Vergleichswert gibt es keinen Prozentsatz (statt Division durch null).
     *
     * @param  numeric-string  $actual
     * @param  numeric-string  $compare
     * @return array{delta: numeric-string, delta_pct: numeric-string|null}
     */
    protected function deltaOf(string $actual, string $compare): array {
        $delta = NumberHelper::subtractPrecise($actual, $compare, 2);
        $pct = NumberHelper::isZeroPrecise($compare)
            ? null
            : NumberHelper::dividePrecise(NumberHelper::multiplyPrecise($delta, '100', 4), NumberHelper::absPrecise($compare), 1, RoundingMode::HalfUp);

        return ['delta' => $delta, 'delta_pct' => $pct];
    }

    /**
     * Betrag in der natürlichen Richtung der Kontoart: Erträge Haben − Soll,
     * Aufwendungen (und alles andere) Soll − Haben.
     *
     * @param  array{debit: numeric-string, credit: numeric-string}|null  $sums
     * @return numeric-string
     */
    protected function naturalAmount(AccountingAccount $account, ?array $sums): string {
        $debit = $sums['debit'] ?? '0.00';
        $credit = $sums['credit'] ?? '0.00';

        return $account->type === AccountType::Income
            ? NumberHelper::subtractPrecise($credit, $debit, 2)
            : NumberHelper::subtractPrecise($debit, $credit, 2);
    }

    /**
     * @return Collection<int, AccountingOpenItemSettlement>
     */
    protected function settlementsInPeriod(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): Collection {
        return AccountingOpenItemSettlement::query()
            ->where('organization_id', $organization->id)
            ->whereBetween('booked_on', DateRange::days($from, $to))
            ->with(['openItem.entry.lines.account'])
            ->get();
    }

    /**
     * Anteil eines Betrags im Verhältnis Ausgleich zu Ursprungsbetrag: erst
     * multipliziert, dann geteilt, einmal kaufmännisch gerundet — ein vorab
     * gerundeter Quotient würde bei Teilzahlungen Cent verschieben
     * (Vollscan 2026-08-23, C1).
     *
     * @param  numeric-string  $amount
     * @return numeric-string
     */
    protected function proRata(string $amount, Money $part, Money $whole): string {
        return NumberHelper::dividePrecise(
            NumberHelper::multiplyPrecise($amount, $part->getAmount(), 4),
            $whole->getAmount(),
            2,
            RoundingMode::HalfUp,
        );
    }
}
