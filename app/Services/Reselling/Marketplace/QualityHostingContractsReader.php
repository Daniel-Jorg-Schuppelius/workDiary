<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualityHostingContractsReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Enums\Reselling\BillingFrequency;
use App\Services\Reselling\Marketplace\Concerns\{NormalizesHeaders, ParsesImportValues};
use Carbon\CarbonImmutable;
use CommonToolkit\Entities\XLSX\Cell;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Parsers\XLSXDocumentParser;
use RuntimeException;
use Throwable;

/**
 * Liest den Vertragsexport des Quality-Hosting-Partnerportals (XLSX, eine
 * Zeile je Vertrag). Anders als der Telekom-Export nennt er Menge und
 * Stückpreis ausdrücklich; Verträge sind „Aktiv, verlängert sich am …", also
 * ohne Ende. Datumszellen kommen als Excel-Seriennummer, Datum oder Text.
 * Die Summenzeile am Ende (ohne Vertragsnummer) wird übersprungen.
 */
final class QualityHostingContractsReader {
    use NormalizesHeaders;
    use ParsesImportValues;

    private const REQUIRED = [
        'kundennummer',
        'kunde',
        'produktname',
        'gekaufte lizenzen',
        'gesamtpreis (vertragslaufzeit)',
        'abrechnungsintervall',
        'vertragsnummer',
        'vertragsstart',
        'vertragsstatus',
    ];

    private const END_PATTERN = '/(gek(?:ü|ue)ndigt|beendet|endet|l(?:ä|ae)uft aus|bis zum)[^0-9]*(\d{1,2}\.\d{1,2}\.\d{4})/iu';

    public function read(string $file): PurchasesImport {
        $name = basename($file);
        if (! is_readable($file)) {
            throw new RuntimeException((string) __('resale_import.file.unreadable', ['file' => $name]));
        }

        try {
            $document = XLSXDocumentParser::fromFile($file, true);
        } catch (Throwable $e) {
            throw new RuntimeException((string) __('resale_import.file.xlsx_unreadable', ['file' => $name, 'reason' => str_replace($file, $name, $e->getMessage())]), 0, $e);
        }
        $sheet = $document->getFirstSheet();
        if ($sheet === null) {
            throw new RuntimeException((string) __('resale_import.file.no_sheet', ['file' => $name]));
        }

        $index = self::headerIndex(self::sheetHeaderNames($sheet));
        $missing = array_values(array_diff(self::REQUIRED, array_keys($index)));
        if ($missing !== []) {
            throw new RuntimeException((string) __('resale_import.file.missing_columns', ['columns' => implode(', ', $missing)]));
        }

        $entitlements = [];
        $issues = [];

        foreach ($sheet->getRows() as $row) {
            $line = $row->getRowIndex();
            $cells = array_values($row->getCells());
            $cell = static function (string $column) use ($cells, $index): ?Cell {
                $position = $index[$column] ?? null;

                return $position === null ? null : ($cells[$position] ?? null);
            };
            $text = static fn(string $column): string => trim($cell($column)?->toCanonicalString() ?? '');

            $contract = $text('vertragsnummer');
            $companyName = $text('kunde');
            $issue = static fn(string $key, array $params = []): string => (string) __('resale_import.row.' . $key, $params + ['line' => $line, 'company' => $companyName !== '' ? $companyName : $text('produktname')]);
            if ($contract === '') {
                if ($companyName !== '' || $text('produktname') !== '') {
                    $issues[] = $issue('no_contract');
                }

                continue;
            }

            $frequencyLabel = $text('abrechnungsintervall');
            $frequency = BillingFrequency::fromLabel($frequencyLabel);
            if ($frequency === null) {
                $issues[] = $issue('unknown_frequency', ['value' => $frequencyLabel]);

                continue;
            }

            $fee = self::importMoney($cell('gesamtpreis (vertragslaufzeit)')?->getValue(), CurrencyCode::Euro, 2);
            if ($fee === null) {
                $issues[] = $issue('total_unreadable', ['value' => $text('gesamtpreis (vertragslaufzeit)')]);

                continue;
            }

            $startsOn = self::importDate($cell('vertragsstart')?->getValue());
            if ($startsOn === null) {
                $issues[] = $issue('contract_start_unreadable', ['value' => $text('vertragsstart')]);

                continue;
            }

            $quantityRaw = $text('gekaufte lizenzen');
            $quantity = $quantityRaw === '' ? 1 : self::importInteger($cell('gekaufte lizenzen')?->getValue());
            if ($quantity === null || $quantity <= 0) {
                $issues[] = $issue('quantity_invalid', ['value' => $quantityRaw]);

                continue;
            }
            $status = $text('vertragsstatus');
            $endsOn = $this->endFromStatus($status);
            if ($endsOn !== null && $endsOn->lessThanOrEqualTo($startsOn)) {
                $issues[] = $issue('status_end_before_start');

                continue;
            }
            if ($endsOn === null && $status !== '' && ! str_starts_with(mb_strtolower($status), 'aktiv')) {
                $issues[] = $issue('status_unknown', ['value' => $status]);
            }

            $customerNumber = $text('kundennummer');
            $partnerNumber = $text('partner-kundennummer');
            $company = new MarketplaceCompany(
                key: $customerNumber !== '' ? $customerNumber : MarketplaceCompany::matchKey($companyName),
                name: $companyName,
                email: null,
                phone: null,
                partnerCustomerNumber: $partnerNumber !== '' ? $partnerNumber : null,
            );

            $term = self::importInteger($cell('vertragslaufzeit')?->getValue());
            $entitlements[] = new MarketplaceEntitlement(
                company: $company,
                entitlementId: $contract,
                orderId: $contract,
                application: $text('produktname'),
                edition: $text('produktname'),
                fee: $fee,
                frequency: $frequency,
                startsOn: $startsOn,
                endsOn: $endsOn,
                status: $status,
                assignedUsers: $quantity,
                sourceLine: $line,
                source: MarketplaceEntitlement::SOURCE_QUALITYHOSTING,
                quantity: $quantity,
                unitFee: self::importMoney($cell('preis pro lizenz (vertragslaufzeit)')?->getValue(), CurrencyCode::Euro, 4),
                termMonths: $term !== null && $term > 0 ? $term : null,
            );
        }

        return new PurchasesImport($entitlements, $issues);
    }

    private function endFromStatus(string $status): ?CarbonImmutable {
        if ($status === '' || preg_match(self::END_PATTERN, $status, $match) !== 1) {
            return null;
        }

        return self::importDate($match[2]);
    }
}
