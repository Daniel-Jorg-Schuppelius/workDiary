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
use Carbon\CarbonImmutable;
use CommonToolkit\Entities\XLSX\Cell;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\{DateHelper, NumberHelper};
use CommonToolkit\Parsers\XLSXDocumentParser;
use CommonToolkit\ValueObjects\Money;
use DateTimeInterface;
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
        if (! is_readable($file)) {
            throw new RuntimeException("XLSX-Datei nicht lesbar: {$file}");
        }

        try {
            $document = XLSXDocumentParser::fromFile($file, true);
        } catch (Throwable $e) {
            throw new RuntimeException("XLSX-Datei nicht lesbar: {$file} ({$e->getMessage()})", 0, $e);
        }
        $sheet = $document->getFirstSheet();
        if ($sheet === null) {
            throw new RuntimeException("XLSX ohne Tabellenblatt: {$file}");
        }

        $index = [];
        foreach (array_values($sheet->getHeaderNames()) as $position => $name) {
            $index[self::normalizeHeader((string) $name)] = (int) $position;
        }
        $missing = array_values(array_diff(self::REQUIRED, array_keys($index)));
        if ($missing !== []) {
            throw new RuntimeException('Pflichtspalten fehlen: ' . implode(', ', $missing));
        }

        $entitlements = [];
        $issues = [];

        foreach (array_values($sheet->getRows()) as $offset => $row) {
            $line = $offset + 2;
            $cells = array_values($row->getCells());
            $cell = static function (string $column) use ($cells, $index): ?Cell {
                $position = $index[$column] ?? null;

                return $position === null ? null : ($cells[$position] ?? null);
            };
            $text = static fn(string $column): string => trim((string) ($cell($column)?->toCanonicalString() ?? ''));

            $contract = $text('vertragsnummer');
            $companyName = $text('kunde');
            if ($contract === '') {
                if ($companyName !== '' || $text('produktname') !== '') {
                    $issues[] = sprintf('Zeile %d (%s): ohne Vertragsnummer - übersprungen.', $line, $companyName !== '' ? $companyName : $text('produktname'));
                }

                continue;
            }

            $frequencyLabel = $text('abrechnungsintervall');
            $frequency = BillingFrequency::fromLabel($frequencyLabel);
            if ($frequency === null) {
                $issues[] = sprintf('Zeile %d (%s): unbekannter Rhythmus "%s" - übersprungen.', $line, $companyName, $frequencyLabel);

                continue;
            }

            $fee = $this->money($cell('gesamtpreis (vertragslaufzeit)'));
            if ($fee === null) {
                $issues[] = sprintf('Zeile %d (%s): Gesamtpreis "%s" nicht lesbar - übersprungen.', $line, $companyName, $text('gesamtpreis (vertragslaufzeit)'));

                continue;
            }

            $startsOn = $this->date($cell('vertragsstart'));
            if ($startsOn === null) {
                $issues[] = sprintf('Zeile %d (%s): Vertragsstart "%s" nicht lesbar - übersprungen.', $line, $companyName, $text('vertragsstart'));

                continue;
            }

            $quantity = (int) round((float) ($cell('gekaufte lizenzen')?->getValue() ?? 1));
            $status = $text('vertragsstatus');
            $endsOn = $this->endFromStatus($status);
            if ($endsOn !== null && $endsOn->lessThanOrEqualTo($startsOn)) {
                $issues[] = sprintf('Zeile %d (%s): Vertragsende aus Status liegt nicht nach dem Beginn - übersprungen.', $line, $companyName);

                continue;
            }
            if ($endsOn === null && $status !== '' && ! str_starts_with(mb_strtolower($status), 'aktiv')) {
                $issues[] = sprintf('Zeile %d (%s): Vertragsstatus "%s" unbekannt - als laufend behandelt.', $line, $companyName, $status);
            }

            $customerNumber = $text('kundennummer');
            $partnerNumber = $text('partner-kundennummer');
            $company = new MarketplaceCompany(
                key: $customerNumber !== '' ? $customerNumber : MarketplaceCompany::normalizeName($companyName),
                name: $companyName,
                email: null,
                phone: null,
                partnerCustomerNumber: $partnerNumber !== '' ? $partnerNumber : null,
            );

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
                quantity: max(1, $quantity),
                unitFee: $this->money($cell('preis pro lizenz (vertragslaufzeit)')),
                termMonths: (int) round((float) ($cell('vertragslaufzeit')?->getValue() ?? 0)) ?: null,
            );
        }

        return new PurchasesImport($entitlements, $issues);
    }

    private static function normalizeHeader(string $name): string {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? $name));
    }

    private function money(?Cell $cell): ?Money {
        if ($cell === null || $cell->isEmpty()) {
            return null;
        }

        $value = $cell->getValue();
        if (is_int($value) || is_float($value)) {
            return Money::ofFloat(round((float) $value, 2), CurrencyCode::Euro);
        }

        $decimal = NumberHelper::normalizeDecimalStringOrNull(str_replace(["\u{00A0}", "\u{202F}"], ' ', (string) $cell->toCanonicalString()));
        if ($decimal === null) {
            return null;
        }

        try {
            return Money::of($decimal, CurrencyCode::Euro);
        } catch (Throwable) {
            return null;
        }
    }

    private function date(?Cell $cell): ?CarbonImmutable {
        if ($cell === null || $cell->isEmpty()) {
            return null;
        }

        $value = $cell->getValue();
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }
        if (is_int($value) || is_float($value)) {
            $parsed = DateHelper::fromExcelSerial($value);

            return $parsed === null ? null : CarbonImmutable::instance($parsed)->startOfDay();
        }

        return $this->parseDateText(trim((string) $cell->toCanonicalString()));
    }

    private function parseDateText(string $raw): ?CarbonImmutable {
        if ($raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            $parsed = DateHelper::fromExcelSerial((float) $raw);

            return $parsed === null ? null : CarbonImmutable::instance($parsed)->startOfDay();
        }
        foreach (['d.m.Y', 'Y-m-d', 'd.m.y', 'Y-m-d H:i:s'] as $format) {
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

    private function endFromStatus(string $status): ?CarbonImmutable {
        if ($status === '' || preg_match(self::END_PATTERN, $status, $match) !== 1) {
            return null;
        }

        return $this->parseDateText($match[2]);
    }
}
