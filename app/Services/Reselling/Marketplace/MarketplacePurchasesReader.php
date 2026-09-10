<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MarketplacePurchasesReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Enums\Reselling\BillingFrequency;
use App\Services\Reselling\Marketplace\Concerns\{NormalizesHeaders, ParsesImportValues};
use CommonToolkit\Contracts\Interfaces\CSV\FieldInterface;
use CommonToolkit\Entities\CSV\HeaderLine;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Parsers\CSVDocumentParser;
use RuntimeException;

/**
 * Liest den „Purchases"-Export des Telekom Cloud Marketplace (AppDirect-Format).
 *
 * Der Export kommt mit UTF-8-BOM, einer Kopfzeile mit Leerzeichen-Vorlauf
 * („ Owner Company Phone") und Gebühren wie „1.958,07 €" mit geschütztem
 * Leerzeichen — BOM und Encoding normalisiert das Toolkit beim Lesen, Beträge
 * und Daten werden deutsch gedeutet. Zeilen ohne verwertbare Gebühr oder
 * Daten werden nicht verworfen, sondern als Befund gemeldet; eine Zeile mit
 * abweichender Feldzahl kippt nicht die Datei (Review 2026-09-10, C).
 */
final class MarketplacePurchasesReader {
    use NormalizesHeaders;
    use ParsesImportValues;

    private const REQUIRED = [
        'owner company name',
        'company entitlement uuid',
        'edition name',
        'active order id',
        'active order frequency',
        'active order total fee',
        'active order contract end date',
        'creation date',
    ];

    public function read(string $file): PurchasesImport {
        $name = basename($file);
        if (! is_readable($file)) {
            throw new RuntimeException((string) __('resale_import.file.unreadable', ['file' => $name]));
        }

        $delimiter = CSVDocumentParser::detectDelimiter($file);
        /** @var array<string, int> $index */
        $index = [];
        $headerCount = 0;
        $entitlements = [];
        $issues = [];

        foreach (CSVDocumentParser::streamAll($file, $delimiter, '"', true) as $line => $parsed) {
            if ($parsed instanceof HeaderLine) {
                $index = self::headerIndex($parsed->getColumnNames());
                $headerCount = count($parsed->getColumnNames());
                $missing = array_values(array_diff(self::REQUIRED, array_keys($index)));
                if ($missing !== []) {
                    throw new RuntimeException((string) __('resale_import.file.missing_columns', ['columns' => implode(', ', $missing)]));
                }

                continue;
            }
            if ($index === []) {
                continue;
            }
            $fields = array_values($parsed->getFields());
            if (count($fields) > $headerCount && trim(implode('', array_map(static fn(FieldInterface $f): string => $f->getValue(), array_slice($fields, $headerCount)))) !== '') {
                $issues[] = (string) __('resale_import.row.too_many_fields', ['line' => $line, 'expected' => $headerCount, 'found' => count($fields)]);

                continue;
            }
            $value = static function (string $column) use ($fields, $index): string {
                $position = $index[$column] ?? null;

                return $position === null || ! isset($fields[$position]) ? '' : trim($fields[$position]->getValue());
            };

            $companyName = $value('owner company name');
            $entitlementId = $value('company entitlement uuid');
            $issue = static fn(string $key, array $params = []): string => (string) __('resale_import.row.' . $key, $params + ['line' => $line, 'company' => $companyName]);
            if ($companyName === '' || $entitlementId === '') {
                $issues[] = $issue('missing_company_or_entitlement');

                continue;
            }

            $frequencyLabel = $value('active order frequency');
            $frequency = BillingFrequency::fromLabel($frequencyLabel);
            if ($frequency === null) {
                $issues[] = $issue('unknown_frequency', ['value' => $frequencyLabel]);

                continue;
            }

            $feeRaw = $value('active order total fee');
            $fee = self::importMoney($feeRaw, CurrencyCode::tryFrom(strtoupper($value('currency'))) ?? CurrencyCode::Euro, 2);
            if ($fee === null) {
                $issues[] = $issue('fee_unreadable', ['value' => $feeRaw]);

                continue;
            }

            $startRaw = $value('creation date');
            $endRaw = $value('active order contract end date');
            $startsOn = self::importDate($startRaw);
            $endsOn = self::importDate($endRaw);
            if ($startsOn === null || $endsOn === null) {
                $issues[] = $issue('dates_unreadable', ['start' => $startRaw, 'end' => $endRaw]);

                continue;
            }
            if ($endsOn->lessThanOrEqualTo($startsOn)) {
                $issues[] = $issue('contract_end_before_start');

                continue;
            }

            $companyId = $value('owner company id');
            $company = new MarketplaceCompany(
                key: $companyId !== '' ? $companyId : MarketplaceCompany::matchKey($companyName),
                name: $companyName,
                email: $value('owner email') !== '' ? $value('owner email') : null,
                phone: $value('owner company phone') !== '' ? $value('owner company phone') : null,
            );

            $entitlements[] = new MarketplaceEntitlement(
                company: $company,
                entitlementId: $entitlementId,
                orderId: $value('active order id'),
                application: $value('application name'),
                edition: $value('edition name'),
                fee: $fee,
                frequency: $frequency,
                startsOn: $startsOn,
                endsOn: $endsOn,
                status: $value('status'),
                assignedUsers: (int) $value('assigned users'),
                sourceLine: (int) $line,
                source: MarketplaceEntitlement::SOURCE_TELEKOM,
            );
        }
        if ($index === []) {
            throw new RuntimeException((string) __('resale_import.file.no_header', ['file' => $name]));
        }

        return new PurchasesImport($entitlements, $issues);
    }
}
