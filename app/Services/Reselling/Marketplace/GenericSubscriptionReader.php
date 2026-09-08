<?php
/*
 * Created on   : Tue Sep 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GenericSubscriptionReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Enums\Reselling\{BillingFrequency, SubscriptionProvider};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\Parsers\{CSVDocumentParser, XLSXDocumentParser};
use CommonToolkit\ValueObjects\Money;
use RuntimeException;
use Throwable;

/**
 * Generische Abo-Liste (Feature 152): CSV oder XLSX mit frei benannten
 * Spalten — deutsche und englische Überschriften werden erkannt. Pflicht
 * sind Firma, Produkt und Beginn; Kennung, Menge, Ende, Rhythmus, Laufzeit,
 * Einkaufs-/Verkaufspreis, Bestellnummer und Anbieter sind optional. Ohne
 * Kennung entsteht sie aus Firma, Produkt und Beginn — stabil, solange die
 * Zeile gleich bleibt. Der Anbieter kommt aus der Spalte oder dem Dialog.
 */
final class GenericSubscriptionReader {
    /** @var array<string, list<string>> Zielspalte → erkannte Überschriften (normalisiert) */
    private const COLUMNS = [
        'id' => ['kennung', 'id', 'vertrag', 'vertragsnummer', 'vertragsnr', 'contract', 'contract id', 'entitlement', 'subscription', 'subscription id', 'abo', 'abo-id', 'abo id'],
        'company' => ['firma', 'kunde', 'unternehmen', 'company', 'customer', 'endkunde', 'halter'],
        'product' => ['produkt', 'product', 'edition', 'artikel', 'lizenz', 'license', 'licence', 'leistung', 'bezeichnung'],
        'quantity' => ['menge', 'anzahl', 'lizenzen', 'quantity', 'qty', 'seats', 'stück', 'stueck', 'nutzer', 'users'],
        'start' => ['beginn', 'start', 'von', 'ab', 'startdatum', 'starts on', 'starts_on', 'start date', 'vertragsbeginn', 'laufzeitbeginn'],
        'end' => ['ende', 'bis', 'enddatum', 'ends on', 'ends_on', 'end date', 'vertragsende', 'laufzeitende', 'kündigung', 'kuendigung'],
        'frequency' => ['intervall', 'rhythmus', 'abrechnung', 'frequency', 'interval', 'billing', 'zahlweise', 'turnus'],
        'term' => ['laufzeit', 'laufzeit (monate)', 'laufzeit monate', 'term', 'term months', 'term_months', 'mindestlaufzeit'],
        'purchase' => ['einkauf', 'einkaufspreis', 'ek', 'ek-preis', 'purchase', 'purchase price', 'cost', 'kosten', 'einkauf je stück', 'einkauf/stück'],
        'sale' => ['verkauf', 'verkaufspreis', 'vk', 'vk-preis', 'sale', 'sale price', 'price', 'preis', 'verkauf je stück', 'verkauf/stück'],
        'fee' => ['gebühr', 'gebuehr', 'gesamt', 'gesamtpreis', 'total', 'total fee', 'summe', 'betrag'],
        'order' => ['bestellung', 'bestellnummer', 'bestellnr', 'order', 'order id', 'auftrag', 'auftragsnummer'],
        'provider' => ['anbieter', 'provider', 'lieferant', 'vendor', 'quelle', 'source'],
        'status' => ['status', 'zustand'],
        'currency' => ['währung', 'waehrung', 'currency'],
    ];

    private const REQUIRED = ['company', 'product', 'start'];

    private const DATE_FORMATS = ['d.m.Y', 'd.m.y', 'Y-m-d', 'd/m/Y', 'm/d/Y', 'Y-m-d H:i:s', 'd.m.Y H:i'];

    public function read(string $file, SubscriptionProvider $provider = SubscriptionProvider::Other): PurchasesImport {
        if (! is_readable($file)) {
            throw new RuntimeException("Datei nicht lesbar: {$file}");
        }
        [$headers, $rows] = str_ends_with(mb_strtolower($file), '.xlsx') || str_ends_with(mb_strtolower($file), '.xlsm')
            ? $this->readXlsx($file)
            : $this->readCsv($file);

        $index = $this->columnIndex($headers);
        $missing = [];
        foreach (self::REQUIRED as $column) {
            if (! isset($index[$column])) {
                $missing[] = $column;
            }
        }
        if ($missing !== []) {
            throw new RuntimeException('Pflichtspalten fehlen: ' . implode(', ', array_map(static fn(string $c): string => match ($c) {
                'company' => 'Firma',
                'product' => 'Produkt',
                default => 'Beginn',
            }, $missing)));
        }

        $entitlements = [];
        $issues = [];
        foreach ($rows as $offset => $row) {
            $line = $offset + 2;
            $value = static function (string $column) use ($row, $index): string {
                $position = $index[$column] ?? null;

                return $position === null ? '' : trim((string) ($row[$position] ?? ''));
            };
            $companyName = $value('company');
            $product = $value('product');
            if ($companyName === '' && $product === '' && $value('start') === '') {
                continue; // Leerzeile
            }
            if ($companyName === '' || $product === '') {
                $issues[] = sprintf('Zeile %d: Firma oder Produkt fehlt - übersprungen.', $line);

                continue;
            }
            $startsOn = $this->parseDate($value('start'));
            if ($startsOn === null) {
                $issues[] = sprintf('Zeile %d (%s): Beginn "%s" nicht lesbar - übersprungen.', $line, $companyName, $value('start'));

                continue;
            }
            $endRaw = $value('end');
            $endsOn = $endRaw === '' ? null : $this->parseDate($endRaw);
            if ($endRaw !== '' && $endsOn === null) {
                $issues[] = sprintf('Zeile %d (%s): Ende "%s" nicht lesbar - übersprungen.', $line, $companyName, $endRaw);

                continue;
            }
            if ($endsOn !== null && $endsOn->lessThanOrEqualTo($startsOn)) {
                $issues[] = sprintf('Zeile %d (%s): Ende liegt nicht nach dem Beginn - übersprungen.', $line, $companyName);

                continue;
            }
            $frequencyRaw = $value('frequency');
            $frequency = $frequencyRaw === '' ? BillingFrequency::Yearly : BillingFrequency::fromLabel($frequencyRaw);
            if ($frequency === null) {
                $issues[] = sprintf('Zeile %d (%s): unbekannter Rhythmus "%s" - übersprungen.', $line, $companyName, $frequencyRaw);

                continue;
            }
            $quantity = max(1, (int) round((float) NumberHelper::normalizeDecimalStringOrNull($value('quantity')) ?: 1));
            $currency = $value('currency');
            $unitFee = $this->parseMoney($value('purchase'), $currency);
            $fee = $this->parseMoney($value('fee'), $currency);
            if ($unitFee === null && $fee !== null) {
                $unitFee = $fee->dividedBy($quantity)->withScale(4);
            }
            $unitFee ??= Money::of('0', CurrencyCode::tryFrom(strtoupper($currency)) ?? CurrencyCode::Euro);
            $rowProvider = $this->provider($value('provider')) ?? $provider;
            $externalId = $value('id');
            if ($externalId === '') {
                // Stabil, solange Firma, Produkt und Beginn gleich bleiben.
                $externalId = 'gen:' . substr((string) \CommonToolkit\Helper\Data\CryptoHelper::hash(MarketplaceCompany::normalizeName($companyName) . '|' . ProductNameMatcher::normalize($product) . '|' . $startsOn->toDateString()), 0, 24);
            }
            $term = (int) round((float) (NumberHelper::normalizeDecimalStringOrNull($value('term')) ?? 0));

            $entitlements[] = new MarketplaceEntitlement(
                company: new MarketplaceCompany(key: MarketplaceCompany::normalizeName($companyName), name: $companyName, email: null, phone: null),
                entitlementId: $externalId,
                orderId: $value('order'),
                application: $product,
                edition: $product,
                fee: $fee ?? $unitFee->times($quantity),
                frequency: $frequency,
                startsOn: $startsOn,
                endsOn: $endsOn,
                status: $value('status'),
                assignedUsers: 0,
                sourceLine: $line,
                source: MarketplaceEntitlement::SOURCE_GENERIC,
                quantity: $quantity,
                unitFee: $unitFee,
                termMonths: $term > 0 ? $term : null,
                provider: $rowProvider->value,
                salePrice: $this->parseMoney($value('sale'), $currency),
            );
        }

        return new PurchasesImport($entitlements, $issues);
    }

    /**
     * @return array{0: list<string>, 1: list<array<int, string>>}
     */
    private function readCsv(string $file): array {
        $delimiter = CSVDocumentParser::detectDelimiter($file);
        $document = CSVDocumentParser::fromFile($file, $delimiter, '"', true);
        $header = $document->getHeader();
        if ($header === null) {
            throw new RuntimeException("CSV ohne Kopfzeile: {$file}");
        }
        $headers = array_map('strval', array_values($header->getColumnNames()));
        $rows = [];
        foreach ($document->getRows() as $row) {
            $cells = [];
            foreach ($headers as $position => $name) {
                $cells[$position] = (string) ($row->getField($position)?->getValue() ?? '');
            }
            $rows[] = $cells;
        }

        return [$headers, $rows];
    }

    /**
     * @return array{0: list<string>, 1: list<array<int, string>>}
     */
    private function readXlsx(string $file): array {
        try {
            $document = XLSXDocumentParser::fromFile($file, true);
        } catch (Throwable $e) {
            throw new RuntimeException("XLSX-Datei nicht lesbar: {$file} ({$e->getMessage()})", 0, $e);
        }
        $sheet = $document->getFirstSheet();
        if ($sheet === null) {
            throw new RuntimeException("XLSX ohne Tabellenblatt: {$file}");
        }
        $headers = array_map('strval', array_values($sheet->getHeaderNames()));
        $rows = [];
        foreach ($sheet->getRows() as $row) {
            $cells = [];
            foreach (array_values($row->getCells()) as $position => $cell) {
                $cells[$position] = (string) ($cell->getValue() ?? '');
            }
            $rows[] = $cells;
        }

        return [$headers, $rows];
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int>
     */
    private function columnIndex(array $headers): array {
        $index = [];
        foreach ($headers as $position => $name) {
            $normalized = self::normalizeHeader($name);
            foreach (self::COLUMNS as $column => $aliases) {
                if (! isset($index[$column]) && in_array($normalized, $aliases, true)) {
                    $index[$column] = $position;
                    break;
                }
            }
        }

        return $index;
    }

    private static function normalizeHeader(string $name): string {
        $name = preg_replace('/^\xEF\xBB\xBF/', '', $name) ?? $name;
        $name = mb_strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? $name));

        return trim(preg_replace('/\s*\((?:eur|€|netto|net)\)\s*$/u', '', $name) ?? $name);
    }

    private function provider(string $raw): ?SubscriptionProvider {
        $normalized = mb_strtolower(trim($raw));
        if ($normalized === '') {
            return null;
        }
        foreach (SubscriptionProvider::cases() as $case) {
            if ($normalized === $case->value || $normalized === mb_strtolower($case->label())) {
                return $case;
            }
        }

        return match (true) {
            str_contains($normalized, 'telekom') => SubscriptionProvider::TelekomMarketplace,
            str_contains($normalized, 'quality') => SubscriptionProvider::QualityHosting,
            str_contains($normalized, 'domain') => SubscriptionProvider::DomainReselling,
            str_contains($normalized, 'manuell') || str_contains($normalized, 'manual') => SubscriptionProvider::Manual,
            default => SubscriptionProvider::Other,
        };
    }

    private function parseMoney(string $raw, string $currency): ?Money {
        if ($raw === '') {
            return null;
        }
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
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        foreach (self::DATE_FORMATS as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $raw);
                if ($parsed !== null && $parsed->format($format) === $raw) {
                    return $parsed->startOfDay();
                }
            } catch (Throwable) {
                continue;
            }
        }
        try {
            return CarbonImmutable::parse($raw)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
