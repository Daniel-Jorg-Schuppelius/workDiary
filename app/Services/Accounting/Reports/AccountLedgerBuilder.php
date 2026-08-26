<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountLedgerBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Reports;

use App\Models\Accounting\{AccountingAccount, AccountingEntryLine};
use App\Models\Organization;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Kontenblatt: Vortrag, Bewegungen und Endsaldo eines Kontos (Feature 125,
 * MVP-676).
 */
class AccountLedgerBuilder extends AbstractAccountingReportBuilder {
    /**
     * Sortierung und Endsaldo entstehen in der Datenbank. Ein Sachkonto kann
     * über fünf Geschäftsjahre zehntausende Zeilen tragen — sie alle zu
     * hydrieren, nur um sie zu addieren, kostet ein Vielfaches der Abfrage
     * (gemessen in MVP-683). Ohne `$perPage` wird weiterhin alles geliefert;
     * der Export braucht die vollständige Menge.
     *
     * @return array{opening: string, lines: Collection<int, AccountingEntryLine>|LengthAwarePaginator<int, AccountingEntryLine>, closing: string}
     */
    public function build(Organization $organization, AccountingAccount $account, CarbonImmutable $from, CarbonImmutable $to, ?int $perPage = null, ?int $costCenterId = null): array {
        $before = $this->sumsByAccount($organization, null, $from->subDay(), $account->id, $costCenterId);
        $opening = NumberHelper::subtractPrecise($before[$account->id]['debit'] ?? '0.00', $before[$account->id]['credit'] ?? '0.00', 2);

        $period = $this->sumsByAccount($organization, $from, $to, $account->id, $costCenterId);
        $closing = NumberHelper::addPrecise(
            $opening,
            NumberHelper::subtractPrecise($period[$account->id]['debit'] ?? '0.00', $period[$account->id]['credit'] ?? '0.00', 2),
            2,
        );

        $query = AccountingEntryLine::query()
            ->where('accounting_entry_lines.organization_id', $organization->id)
            ->where('accounting_entry_lines.accounting_account_id', $account->id)
            ->join('accounting_entries', 'accounting_entries.id', '=', 'accounting_entry_lines.accounting_entry_id')
            ->whereIn('accounting_entries.status', self::POSTED)
            ->whereBetween('accounting_entries.booked_on', DateRange::days($from, $to))
            ->when($costCenterId !== null, fn ($q) => $q->where('accounting_entry_lines.cost_center_id', $costCenterId))
            ->orderBy('accounting_entries.booked_on')
            ->orderBy('accounting_entries.journal_no')
            ->select('accounting_entry_lines.*')
            ->with('entry');

        $lines = $perPage === null ? $query->get() : $query->paginate($perPage)->withQueryString();

        return ['opening' => $opening, 'lines' => $lines, 'closing' => $closing];
    }
}
