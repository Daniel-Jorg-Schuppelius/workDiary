<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionSettlementService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\Sales\{CommissionSettlementStatus, CommissionStatus};
use App\Models\{Organization, User};
use App\Models\Sales\{CommissionSettlementRun, InvoiceCommission};
use App\Support\CsvExport;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Abrechnungslauf je Periode (Feature 146, MVP-729).
 *
 * Der Entwurf ist eine **reine Projektion**: er haelt keine Zeilen fest,
 * sondern zeigt bei jedem Aufruf, was in der Periode offen ist. Erst das
 * Schliessen schreibt fest — es haengt die Zeilen an den Lauf, setzt sie auf
 * `settled` und friert die Summen ein. Danach ist der Lauf unveraenderlich
 * ({@see CommissionSettlementRun::booted()}); Korrekturen kommen als
 * Rueckrechnung in den Lauf der Folgeperiode.
 *
 * Ein Lauf rechnet **eine** Waehrung ab. Provisionen werden nie umgerechnet;
 * Belege in anderer Waehrung bekommen ihren eigenen Lauf.
 */
class CommissionSettlementService {
    /**
     * Offene Provisionszeilen einer Periode (Vorschau).
     *
     * @return Collection<int, InvoiceCommission>
     */
    public function openCommissions(int $organizationId, Carbon $start, Carbon $end, CurrencyCode $currency): Collection {
        return InvoiceCommission::query()
            ->where('organization_id', $organizationId)
            ->where('currency', $currency->value)
            ->open()
            ->whereBetween('earned_on', [$start->toDateString(), $end->toDateString()])
            ->with(['user:id,name', 'invoice:id,number,customer_id', 'invoice.customer:id,name,company', 'rule:id,name'])
            ->orderBy('user_id')
            ->orderBy('earned_on')
            ->orderBy('id')
            ->get();
    }

    /**
     * Zeilen des Laufs: beim Entwurf die Vorschau, beim geschlossenen Lauf die
     * festgeschriebenen Zeilen.
     *
     * @return Collection<int, InvoiceCommission>
     */
    public function rowsOf(CommissionSettlementRun $run): Collection {
        if ($run->isClosed()) {
            return $run->commissions()
                ->with(['user:id,name', 'invoice:id,number,customer_id', 'invoice.customer:id,name,company', 'rule:id,name'])
                ->orderBy('user_id')->orderBy('earned_on')->orderBy('id')
                ->get();
        }

        return $this->openCommissions((int) $run->organization_id, $run->period_start, $run->period_end, $run->currency);
    }

    /**
     * Summen einer Zeilenmenge. Bewusst ueber {@see Money::sum} statt SQL-SUM:
     * auf SQLite laeuft eine Summe ueber `decimal`-Spalten durch float.
     *
     * @param  Collection<int, InvoiceCommission>  $rows
     * @return array{base: Money, commission: Money, count: int}
     */
    public function totals(Collection $rows, CurrencyCode $currency): array {
        $bases = $rows->map(fn (InvoiceCommission $row): Money => $row->base_amount ?? Money::zero($currency))->all();
        $commissions = $rows->map(fn (InvoiceCommission $row): Money => $row->commission_amount ?? Money::zero($currency))->all();

        return [
            'base' => $bases === [] ? Money::zero($currency) : Money::sum($bases, $currency),
            'commission' => $commissions === [] ? Money::zero($currency) : Money::sum($commissions, $currency),
            'count' => $rows->count(),
        ];
    }

    /**
     * Summen je Vertriebsperson — die Sicht, die in den Lohn-Export geht.
     *
     * @param  Collection<int, InvoiceCommission>  $rows
     * @return list<array{user_id: int, user: string, count: int, base: Money, commission: Money}>
     */
    public function perUser(Collection $rows, CurrencyCode $currency): array {
        $groups = [];
        foreach ($rows as $row) {
            $groups[(int) $row->user_id][] = $row;
        }

        $result = [];
        foreach ($groups as $userId => $userRows) {
            $bases = array_map(static fn (InvoiceCommission $r): Money => $r->base_amount ?? Money::zero($currency), $userRows);
            $amounts = array_map(static fn (InvoiceCommission $r): Money => $r->commission_amount ?? Money::zero($currency), $userRows);
            $result[] = [
                'user_id' => $userId,
                'user' => (string) ($userRows[0]->user->name ?? '#' . $userId),
                'count' => count($userRows),
                'base' => Money::sum($bases, $currency),
                'commission' => Money::sum($amounts, $currency),
            ];
        }

        usort($result, static fn (array $a, array $b): int => strcmp($a['user'], $b['user']));

        return $result;
    }

    /** Lauf anlegen (Entwurf). Ueberlappende Perioden sind ausgeschlossen. */
    public function createRun(Organization $organization, Carbon $start, Carbon $end, CurrencyCode $currency, ?string $period = null, ?User $actor = null): CommissionSettlementRun {
        if ($end->lessThan($start)) {
            throw new RuntimeException((string) __('commission.error.period_reversed'));
        }

        $overlap = CommissionSettlementRun::query()
            ->where('organization_id', $organization->id)
            ->where('currency', $currency->value)
            ->where('period_start', '<=', $end->toDateString())
            ->where('period_end', '>=', $start->toDateString())
            ->exists();

        if ($overlap) {
            throw new RuntimeException((string) __('commission.error.period_overlap'));
        }

        return CommissionSettlementRun::create([
            'organization_id' => $organization->id,
            'period' => $period !== null && trim($period) !== '' ? trim($period) : $start->format('Y-m'),
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'status' => CommissionSettlementStatus::Draft,
            'currency' => $currency,
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * Lauf schliessen: Zeilen anhaengen, festschreiben, Summen einfrieren.
     * Ab hier korrigiert nur noch eine Rueckrechnung.
     */
    public function close(CommissionSettlementRun $run, ?User $actor = null): CommissionSettlementRun {
        if ($run->isClosed()) {
            throw new RuntimeException((string) __('commission.error.already_closed'));
        }

        return DB::transaction(function () use ($run, $actor): CommissionSettlementRun {
            $rows = $this->openCommissions((int) $run->organization_id, $run->period_start, $run->period_end, $run->currency);
            $totals = $this->totals($rows, $run->currency);

            foreach ($rows as $row) {
                $row->settlement_run_id = $run->id;
                $row->status = CommissionStatus::Settled;
                $row->save();
            }

            $run->fill([
                'status' => CommissionSettlementStatus::Closed,
                'total_base' => $totals['base'],
                'total_commission' => $totals['commission'],
                'entry_count' => $totals['count'],
                'closed_at' => now(),
                'closed_by' => $actor?->id,
            ]);
            $run->save();

            return $run->refresh();
        });
    }

    /**
     * CSV fuer die Lohnabrechnung: eine Zeile je Provisionszeile, damit die
     * Lohnstelle nachvollziehen kann, woher der Betrag kommt. Betraege als
     * Dezimalstring aus {@see Money} — nie als float.
     */
    public function exportCsv(CommissionSettlementRun $run): string {
        $rows = [];
        foreach ($this->rowsOf($run) as $row) {
            $rows[] = [
                $run->period,
                (string) ($row->user->name ?? ''),
                (string) ($row->invoice->number ?? ''),
                (string) ($row->invoice->customer->name ?? ''),
                $row->earned_on->toDateString(),
                $row->currency->value,
                ($row->base_amount ?? Money::zero($run->currency))->getAmount(),
                $row->rate_percent->getNumericValue(),
                ($row->commission_amount ?? Money::zero($run->currency))->getAmount(),
                $row->isReversal() ? (string) __('commission.export.reversal') : (string) __('commission.export.regular'),
                (string) ($row->note ?? ''),
            ];
        }

        return CsvExport::toString([
            (string) __('commission.export.period'),
            (string) __('commission.export.user'),
            (string) __('commission.export.invoice'),
            (string) __('commission.export.customer'),
            (string) __('commission.export.earned_on'),
            (string) __('commission.export.currency'),
            (string) __('commission.export.base'),
            (string) __('commission.export.rate'),
            (string) __('commission.export.commission'),
            (string) __('commission.export.kind'),
            (string) __('commission.export.note'),
        ], $rows);
    }
}
