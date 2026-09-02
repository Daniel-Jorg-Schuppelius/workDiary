<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataQualityBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Reports;

use App\Enums\Finance\{AccountingEntryStatus, RecurringRunStatus};
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingFilingObligation, AccountingRecurringRun};
use App\Models\Organization;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Buchungsqualität: was der Auswertung im Weg steht (Feature 125, MVP-676).
 */
class DataQualityBuilder extends AbstractAccountingReportBuilder {
    /**
     * @return array{drafts: int, unbalanced: int, blocked_runs: int, open_expectations: int, accounts_without_rule: int, ten_day_cases: int, open_clearing: int, overdue_filings: int, findings: list<string>}
     */
    public function build(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $drafts = AccountingEntry::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [AccountingEntryStatus::Draft->value, AccountingEntryStatus::Ready->value])
            ->whereBetween('booked_on', DateRange::days($from, $to))
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
            ->where('due_on', '<', DateRange::dayAfter($to))
            ->count();

        $tenDayCases = $this->tenDayRuleCases($organization, $from, $to);
        $openClearing = $this->unresolvedClearingAccounts($organization, $to);

        // Überschrittene Meldefristen (MVP-686) — sie kosten Verspätungszuschlag.
        $overdueFilings = AccountingFilingObligation::query()
            ->where('organization_id', $organization->id)
            ->open()
            ->where('due_on', '<', DateRange::day($to))
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
            $balance = NumberHelper::subtractPrecise($sums[$id]['debit'] ?? '0.00', $sums[$id]['credit'] ?? '0.00', 2);
            if (! NumberHelper::isZeroPrecise($balance)) {
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
}
