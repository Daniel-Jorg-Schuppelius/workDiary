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
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\Parsers\CSVDocumentParser;
use CommonToolkit\ValueObjects\Money;
use RuntimeException;
use Throwable;

/**
 * Liest den „Purchases"-Export des Telekom Cloud Marketplace (AppDirect-Format).
 *
 * Der Export kommt mit UTF-8-BOM, einer Kopfzeile mit Leerzeichen-Vorlauf
 * („ Owner Company Phone") und Gebühren wie „1.958,07 €" mit geschütztem
 * Leerzeichen — alles wird hier normalisiert, damit die Fachlogik saubere
 * Werte bekommt. Zeilen ohne verwertbare Gebühr oder Daten werden nicht
 * verworfen, sondern als Befund gemeldet.
 */
final class MarketplacePurchasesReader {
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

    private const DATE_FORMATS = ['d.m.y', 'd.m.Y', 'Y-m-d', 'd/m/Y', 'm/d/y'];

    public function read(string $file): PurchasesImport {
        if (! is_readable($file)) {
            throw new RuntimeException("CSV-Datei nicht lesbar: {$file}");
        }

        $delimiter = CSVDocumentParser::detectDelimiter($file);
        $document = CSVDocumentParser::fromFile($file, $delimiter, '"', true);
        $header = $document->getHeader();
        if ($header === null) {
            throw new RuntimeException("CSV ohne Kopfzeile: {$file}");
        }

        $index = [];
        foreach ($header->getColumnNames() as $position => $name) {
            $index[self::normalizeHeader((string) $name)] = (int) $position;
        }

        $missing = array_values(array_diff(self::REQUIRED, array_keys($index)));
        if ($missing !== []) {
            throw new RuntimeException('Pflichtspalten fehlen: ' . implode(', ', $missing));
        }

        $entitlements = [];
        $issues = [];

        foreach (array_values($document->getRows()) as $offset => $row) {
            $line = $offset + 2;
            $value = static function (string $column) use ($row, $index): string {
                $position = $index[$column] ?? null;
                if ($position === null) {
                    return '';
                }

                return trim((string) ($row->getField($position)?->getValue() ?? ''));
            };

            $companyName = $value('owner company name');
            $entitlementId = $value('company entitlement uuid');
            if ($companyName === '' || $entitlementId === '') {
                $issues[] = sprintf('Zeile %d: Firma oder Entitlement fehlt - übersprungen.', $line);

                continue;
            }

            $frequencyLabel = $value('active order frequency');
            $frequency = BillingFrequency::fromLabel($frequencyLabel);
            if ($frequency === null) {
                $issues[] = sprintf('Zeile %d (%s): unbekannter Rhythmus "%s" - übersprungen.', $line, $companyName, $frequencyLabel);

                continue;
            }

            $feeRaw = $value('active order total fee');
            $fee = $this->parseMoney($feeRaw, $value('currency'));
            if ($fee === null) {
                $issues[] = sprintf('Zeile %d (%s): Gebühr "%s" nicht lesbar - übersprungen.', $line, $companyName, $feeRaw);

                continue;
            }

            $startRaw = $value('creation date');
            $endRaw = $value('active order contract end date');
            $startsOn = $this->parseDate($startRaw);
            $endsOn = $this->parseDate($endRaw);
            if ($startsOn === null || $endsOn === null) {
                $issues[] = sprintf('Zeile %d (%s): Datum nicht lesbar ("%s" / "%s") - übersprungen.', $line, $companyName, $startRaw, $endRaw);

                continue;
            }
            if ($endsOn->lessThanOrEqualTo($startsOn)) {
                $issues[] = sprintf('Zeile %d (%s): Vertragsende liegt nicht nach dem Beginn - übersprungen.', $line, $companyName);

                continue;
            }

            $companyId = $value('owner company id');
            $company = new MarketplaceCompany(
                key: $companyId !== '' ? $companyId : MarketplaceCompany::normalizeName($companyName),
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
                sourceLine: $line,
                source: MarketplaceEntitlement::SOURCE_TELEKOM,
            );
        }

        return new PurchasesImport($entitlements, $issues);
    }

    private static function normalizeHeader(string $name): string {
        $name = preg_replace('/^\xEF\xBB\xBF/', '', $name) ?? $name;

        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? $name));
    }

    private function parseMoney(string $raw, string $currency): ?Money {
        $cleaned = str_replace(["\u{00A0}", "\u{202F}"], ' ', $raw);
        $decimal = NumberHelper::normalizeDecimalStringOrNull($cleaned);
        if ($decimal === null) {
            return null;
        }

        $code = CurrencyCode::tryFrom(strtoupper(trim($currency))) ?? CurrencyCode::Euro;

        try {
            return Money::of($decimal, $code);
        } catch (Throwable) {
            return null;
        }
    }

    private function parseDate(string $raw): ?CarbonImmutable {
        if ($raw === '') {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat('!' . $format, $raw);
            } catch (Throwable) {
                continue;
            }
            if ($parsed instanceof CarbonImmutable && $parsed->format($format) === $raw) {
                return $parsed->startOfDay();
            }
        }

        return null;
    }
}
