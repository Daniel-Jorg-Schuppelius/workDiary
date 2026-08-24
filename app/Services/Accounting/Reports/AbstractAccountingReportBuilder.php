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

use App\Enums\Finance\AccountingEntryStatus;
use App\Models\Accounting\{AccountingEntryLine, AccountingOpenItemSettlement};
use App\Models\Organization;
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

    /**
     * Soll-/Habensummen je Konto im Zeitraum — die einzige Aggregationsstelle
     * der Berichte.
     *
     * @return array<int, array{debit: numeric-string, credit: numeric-string}>
     */
    protected function sumsByAccount(Organization $organization, ?CarbonImmutable $from, CarbonImmutable $to, ?int $accountId = null): array {
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
     * @return Collection<int, AccountingOpenItemSettlement>
     */
    protected function settlementsInPeriod(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): Collection {
        return AccountingOpenItemSettlement::query()
            ->where('organization_id', $organization->id)
            ->whereDate('booked_on', '>=', $from->toDateString())
            ->whereDate('booked_on', '<=', $to->toDateString())
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
