<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconciliationReportSerializer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Enums\Reselling\ReconciliationStatus;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;

/**
 * Wandelt das Abgleichsergebnis in ein flaches Array — die einzige Form, in der
 * Konsole (CSV), Datenbank (JSON am Lauf) und Oberfläche den Bericht sehen.
 * Geldbeträge tragen Rohwert und formatierten Text, damit die Ansicht nicht
 * rechnen und die Datei nicht raten muss.
 */
final class ReconciliationReportSerializer {
    /**
     * @param  list<PriceCheckRow>  $priceRows
     * @param  list<string>  $resolverErrors
     * @return array<string, mixed>
     */
    public function toArray(PurchasesImport $import, ReconciliationReport $report, array $priceRows = [], array $resolverErrors = [], ?PriceList $priceList = null): array {
        $counts = $report->countsByStatus();
        $findings = [];
        $mappings = [];
        $extras = [];
        $lines = [];
        $errors = $resolverErrors;
        $problems = 0;

        foreach ($report->companies as $company) {
            $companyProblems = 0;
            foreach ($company->findings as $finding) {
                $findings[] = $this->finding($company, $finding);
                if ($finding->status->isProblem()) {
                    $companyProblems++;
                }
            }
            $problems += $companyProblems;

            $mapping = $company->mapping;
            $mappings[] = [
                'company' => $mapping->company->name,
                'key' => $mapping->company->key,
                'partner_number' => $mapping->company->partnerCustomerNumber,
                'customer' => $mapping->customer?->name,
                'customer_sqid' => $mapping->customer?->sqid,
                'billed_via' => $mapping->billedVia,
                'contact_ids' => $mapping->contactIds,
                'source' => $mapping->source,
                'detail' => $mapping->detail,
                'source_label' => $mapping->sourceLabel(),
                'candidates' => $mapping->candidates,
                'resolved' => $mapping->isResolved(),
                'periods' => count($company->findings),
                'problems' => $companyProblems,
            ];

            foreach ($company->extras as $extra) {
                $extras[] = [
                    'company' => $mapping->company->name,
                    'voucher' => $extra->line->voucherNumber !== '' ? $extra->line->voucherNumber : $extra->line->voucherId,
                    'date' => $extra->line->voucherDate->toDateString(),
                    'name' => $extra->line->name,
                    'description' => $extra->line->description,
                    'remaining' => $extra->remainingQuantity,
                    'unit_net' => $this->money($extra->line->unitNet),
                ];
            }
            foreach ($company->errors as $error) {
                $errors[] = $mapping->company->name . ': ' . $error;
            }
            foreach ($company->lines as $seen) {
                $line = $seen['line'];
                $shared = (bool) ($seen['shared'] ?? false);
                $lines[] = [
                    // Zeilen eines Partnerkontakts ohne Nennung der Firma laufen unter dem Partner.
                    'company' => $shared ? (($mapping->billedVia ?: $line->recipient) ?: $mapping->company->name) . ' (Partner)' : $mapping->company->name,
                    'company_key' => $shared ? '' : $mapping->company->key,
                    'shared' => $shared,
                    'contact_id' => $line->contactId,
                    'voucher' => $line->voucherNumber !== '' ? $line->voucherNumber : $line->voucherId,
                    'date' => $line->voucherDate->toDateString(),
                    'type' => $line->voucherType,
                    'name' => $line->name,
                    'description' => mb_substr($line->description, 0, 160),
                    'quantity' => $line->quantity,
                    'remaining' => $seen['remaining'],
                    'used' => $line->headerOnly ? 0.0 : $line->quantity - $seen['remaining'],
                    'unit_net' => $this->money($line->unitNet),
                    'header_only' => $line->headerOnly,
                    'microsoft' => (new ProductNameMatcher)->looksLikeMicrosoftProduct($line->text()),
                    'recipient' => $line->recipient,
                    'voucher_text' => mb_substr($line->voucherText, 0, 200),
                ];
            }
        }

        $successions = [];
        foreach ($import->links as $link) {
            $successions[] = [
                'company' => $link->predecessor->company->name,
                'product' => $link->predecessor->edition,
                'from' => $link->predecessor->startsOn->toDateString(),
                'to' => $link->predecessor->endsOn?->toDateString(),
                'successor' => $link->successor->entitlementId,
                'successor_from' => $link->successor->startsOn->toDateString(),
            ];
        }

        return [
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'options' => [
                'reference' => $report->options->reference->toDateString(),
                'window_before' => $report->options->windowBefore,
                'window_after' => $report->options->windowAfter,
                'strict_products' => $report->options->strictProducts,
            ],
            'summary' => [
                'entitlements' => count($import->entitlements),
                'companies' => count($import->companies()),
                'links' => count($import->links),
                'sources' => $import->countBySource(),
                'periods' => count($findings),
                'problems' => $problems,
                'counts' => $counts,
                'open_fee' => $this->money($report->openFee()),
                'unmapped_fee' => $this->money($report->unmappedFee()),
                'unmapped_companies' => count($report->unmappedCompanies()),
                'price_flags' => count(array_filter($priceRows, static fn(PriceCheckRow $row): bool => $row->flags !== [] && $row->flags !== [PriceCheckRow::FLAG_NO_SALES])),
            ],
            'issues' => $import->issues,
            'errors' => array_values(array_unique($errors)),
            'mappings' => $mappings,
            'findings' => $findings,
            'extras' => $extras,
            'lines' => $lines,
            'successions' => $successions,
            'price_check' => array_map(fn(PriceCheckRow $row): array => $this->priceRow($row), $priceRows),
            'price_list' => [
                'entries' => $priceList === null ? 0 : count($priceList->entries),
                'valid_from' => $priceList?->validFrom?->toDateString(),
                'issues' => $priceList === null ? [] : $priceList->issues,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(CompanyReconciliation $company, PeriodFinding $finding): array {
        $entitlement = $finding->period->entitlement;

        return [
            'company' => $company->company()->name,
            'company_key' => $company->company()->key,
            'source' => $entitlement->source,
            'source_label' => $entitlement->sourceLabel(),
            'edition' => $entitlement->edition,
            'application' => $entitlement->application,
            'entitlement' => $entitlement->entitlementId,
            'order' => $entitlement->orderId,
            'period_index' => $finding->period->index,
            'from' => $finding->period->startsOn->toDateString(),
            'to' => $finding->period->endsOn->toDateString(),
            'label' => $finding->period->label(),
            'quantity' => $finding->period->quantity,
            'unit_fee' => $this->money($finding->period->unitFee),
            'fee' => $this->money($finding->period->fee()),
            'status' => $finding->status->value,
            'status_label' => $finding->status->label(),
            'problem' => $finding->status->isProblem(),
            'vouchers' => $finding->voucherNumbers(),
            'lowest_unit_net' => $finding->lowestUnitNet === null ? null : $this->money($finding->lowestUnitNet),
            'open_fee' => $this->money($finding->openFee()),
            'note' => $finding->note,
            'succession' => $entitlement->successionNote,
            'customer' => $company->mapping->customer?->name,
            'billed_via' => $company->mapping->billedVia,
            'contact_ids' => $company->mapping->contactIds,
            'mapping_source' => $company->mapping->source === ContactMapping::SOURCE_NONE ? '' : $company->mapping->sourceLabel(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function priceRow(PriceCheckRow $row): array {
        return [
            'product' => $row->product,
            'term_months' => $row->termMonths,
            'interval' => $row->interval->value,
            'interval_label' => $row->interval->label(),
            'running_quantity' => $row->runningQuantity,
            'contract_min' => $row->contractUnitMin === null ? null : $this->money($row->contractUnitMin),
            'contract_max' => $row->contractUnitMax === null ? null : $this->money($row->contractUnitMax),
            'list_price' => $row->listPrice === null ? null : $this->money($row->listPrice),
            'uvp' => $row->uvp === null ? null : $this->money($row->uvp),
            'sales_min' => $row->salesMin === null ? null : $this->money($row->salesMin),
            'sales_median' => $row->salesMedian === null ? null : $this->money($row->salesMedian),
            'sales_max' => $row->salesMax === null ? null : $this->money($row->salesMax),
            'sales_samples' => $row->salesSamples,
            'margin_percent' => $row->marginPercent,
            'flags' => $row->flags,
            'article_price' => $row->articlePrice === null ? null : $this->money($row->articlePrice),
            'article_name' => $row->articleName,
        ];
    }

    /**
     * @return array{amount: string, currency: string, formatted: string}
     */
    private function money(Money $money): array {
        return [
            'amount' => $money->getAmount(),
            'currency' => $money->getCurrency()->value,
            'formatted' => $money->format(),
        ];
    }

    /**
     * Status-Reihenfolge für Filter und Zählung in der Oberfläche.
     *
     * @return list<string>
     */
    public static function statusOrder(): array {
        return array_map(static fn(ReconciliationStatus $status): string => $status->value, ReconciliationStatus::cases());
    }
}
