<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualityHostingInvoiceReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use PDFToolkit\Helper\PDFTextProvider;
use RuntimeException;
use Throwable;

/**
 * Liest Quality-Hosting-Rechnungen und -Gutschriften (PDF, Feature 152,
 * MVP-762), deutsches und englisches Layout. Aufbau je Position: Kopfzeilen
 * „Endkunde: CNL00007 (Name)" / „End customer: …" und „Vertrag: CNLCON00156"
 * / „Contract: …", dann „Pos Menge Beschreibung Einzelpreis Gesamt", danach
 * „Dienst:"/„Service:", „Vertrag:"/„Contract:", Laufzeit „03.09.26 - 02.09.27".
 * Gutschriften („Storno zu Rechnung", „Gutschriftsnr.", „Credit Note No.")
 * erkennt der Reader nur an den Kopfzeilen vor der ersten Position; ihre
 * Positionen wie „Umzugsbonus Endkunde Name (CNL00002)" tragen keinen
 * Vertrag — sie gelten der Firma. Positionen ohne oder mit wiederholter
 * Nummer bekommen eine laufende Nummer, damit der Allocator sie nicht als
 * Dublette verwirft (Review 2026-09-10, A7/B18).
 */
final class QualityHostingInvoiceReader {
    private const MONTHS = ['januar' => 1, 'februar' => 2, 'märz' => 3, 'maerz' => 3, 'april' => 4, 'mai' => 5, 'juni' => 6, 'juli' => 7, 'august' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'dezember' => 12, 'january' => 1, 'february' => 2, 'march' => 3, 'may' => 5, 'june' => 6, 'july' => 7, 'october' => 10, 'december' => 12];

    /** Positionszeile: Pos Menge Beschreibung Einzelpreis Gesamt. */
    private const POSITION = '/^(\d+)\s+(\d+(?:[.,]\d+)?)\s+(.+?)\s+(-?[\d.]+,\d{2,5})\s+(-?[\d.]+,\d{2})$/u';

    private const COMPANY = '/^(?:Endkunde|End customer|End Customer):\s+(.+)$/u';

    private const CONTRACT = '/(?:Vertrag|Contract):\s*(\S+)/u';

    private const CONTRACT_LINE = '/^(?:Vertrag|Contract):\s*(\S+)/u';

    /** Ende des Detailblocks einer Position bzw. einer umgebrochenen Beschreibung. */
    private const BLOCK_END = '/^(?:\d+\s+\d|Total|Dienst:|Service:|Vertrag:|Contract:|Grundgeb|Base fee|Basic fee)/u';

    /**
     * Text zuerst zeilenausgerichtet aus dem Textlayer, sonst per OCR (C17).
     * Toolkit-Fehler tragen den Serverpfad — nach außen nur der Dateiname.
     */
    public function read(string $path): ProviderInvoice {
        $name = basename($path);
        try {
            $text = (new PDFTextProvider($path))->textWithFallback(
                static fn(PDFTextProvider $p): ?string => $p->rowAlignedText(),
                static fn(PDFTextProvider $p): ?string => $p->ocrRowAlignedText('deu+eng'),
            );
        } catch (Throwable $e) {
            throw new RuntimeException((string) __('resale_import.invoice.unreadable', ['file' => $name, 'reason' => str_replace($path, $name, $e->getMessage())]), 0, $e);
        }
        if ($text === null) {
            throw new RuntimeException((string) __('resale_import.invoice.no_text', ['file' => $name]));
        }

        return $this->parse($text);
    }

    public function parse(string $text): ProviderInvoice {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []), static fn(string $l): bool => $l !== ''));
        $number = '';
        $date = null;
        $credit = $this->isCreditNote($lines);
        $customerNumber = null;
        $netTotal = null;
        $issues = [];
        $positions = [];
        /** @var array<int, true> $usedPositions */
        $usedPositions = [];
        $nextPosition = 1;
        $company = ['key' => null, 'name' => null, 'contract' => null];
        $pendingCompany = null; // mehrzeiliger Endkundenname
        $pendingContract = null; // Vertrag aus der Endkunde-Kopfzeile, falls die Position keinen nennt
        $inDetails = false;

        foreach ($lines as $index => $line) {
            if (preg_match('/^(?:Rechnungsnr\.|Gutschriftsnr\.|Invoice No\.|Credit Note No\.)\s+(\S+)(?:\s+(?:Kundennr\.|Customer No\.)\s+(\S+))?/u', $line, $m) === 1) {
                $number = $m[1];
                $customerNumber = $m[2] ?? $customerNumber;

                continue;
            }
            if (preg_match('/^(?:Rechnungsdatum|Gutschriftsdatum|Date of Invoice|Date of Credit Note|Invoice Date|Credit Note Date)\s+(.+)$/u', $line, $m) === 1) {
                $date = $this->date($m[1]);

                continue;
            }
            if (preg_match('/^Total EUR (?:ohne MwSt\.|Excl\. VAT|excl\. VAT)\s+(-?[\d.]+,\d{2})/u', $line, $m) === 1) {
                $netTotal = $credit ? -$this->amount($m[1]) : $this->amount($m[1]); // Gutschrift: wie die Positionen negativ

                continue;
            }
            if ($pendingCompany !== null) {
                // Fortsetzung eines umgebrochenen Endkundennamens bis zur schließenden Klammer.
                $pendingCompany .= ' ' . $line;
                if (str_contains($line, ')')) {
                    $company = $this->company($pendingCompany);
                    $pendingContract = $company['contract'];
                    $pendingCompany = null;
                }

                continue;
            }
            if (preg_match(self::COMPANY, $line, $m) === 1) {
                $inDetails = false;
                if (str_contains($m[1], ')')) {
                    $company = $this->company($m[1]);
                    $pendingContract = $company['contract'];
                } else {
                    $pendingCompany = $m[1];
                }

                continue;
            }
            if (! $inDetails && preg_match(self::CONTRACT_LINE, $line, $m) === 1) {
                $pendingContract = $m[1];

                continue;
            }
            if (preg_match(self::POSITION, $line, $m) !== 1) {
                continue;
            }
            $description = trim($m[3]);
            // Gutschrift-Positionen mit umgebrochenem Namen: Folgezeile(n) bis „(CNL…)" anhängen.
            $lookahead = $index + 1;
            while (! str_contains($description, '(') || ! str_contains($description, ')')) {
                if (! isset($lines[$lookahead]) || preg_match(self::BLOCK_END, $lines[$lookahead]) === 1) {
                    break;
                }
                if (preg_match('/\(CNL\d+\)/u', $lines[$lookahead]) === 1 || preg_match('/^[\p{L}&.\- ]+\)?$/u', $lines[$lookahead]) === 1) {
                    $description .= ' ' . $lines[$lookahead];
                    $lookahead++;

                    continue;
                }
                break;
            }
            $position = (int) $m[1];
            if ($position <= 0 || isset($usedPositions[$position])) {
                $position = $nextPosition;
            }
            $usedPositions[$position] = true;
            $nextPosition = max($nextPosition, $position + 1);
            $total = $this->amount($m[5]);
            $entry = new ProviderInvoiceLine(
                position: $position,
                quantity: (float) str_replace(',', '.', $m[2]),
                description: $description,
                unitPrice: $credit ? -$this->amount($m[4]) : $this->amount($m[4]),
                total: $credit ? -$total : $total,
                companyKey: $company['key'],
                companyName: $company['name'],
            );
            if (preg_match('/^(.+?)\s+(?:Endkunde|End customer|End Customer)\s+(.+?)\s*\((CNL\d+)\)\s*$/u', $description, $c) === 1) {
                $entry->description = trim($c[1]);
                $entry->companyName = trim($c[2]);
                $entry->companyKey = $c[3];
            }
            // Details nach der Position: Vertrag und Laufzeit bis zur nächsten Position.
            for ($j = $index + 1; $j < count($lines); $j++) {
                $next = $lines[$j];
                if (preg_match(self::POSITION, $next) === 1 || preg_match(self::COMPANY, $next) === 1 || str_starts_with($next, 'Total EUR')) {
                    break;
                }
                if ($entry->contract === null && preg_match(self::CONTRACT_LINE, $next, $v) === 1) {
                    $entry->contract = $v[1];
                } elseif ($entry->periodStart === null && preg_match('/^(?:(?:Laufzeit|Term|Period):\s*)?(\d{2})\.(\d{2})\.(\d{2})\s*-\s*(\d{2})\.(\d{2})\.(\d{2})$/u', $next, $p) === 1) {
                    $entry->periodStart = new CarbonImmutable(sprintf('%04d-%02d-%02d', 2000 + (int) $p[3], (int) $p[2], (int) $p[1]));
                    $entry->periodEnd = new CarbonImmutable(sprintf('%04d-%02d-%02d', 2000 + (int) $p[6], (int) $p[5], (int) $p[4]));
                }
            }
            $entry->contract ??= $pendingContract;
            $pendingContract = null;
            $inDetails = true;
            $positions[] = $entry;
        }
        if ($number === '') {
            $issues[] = (string) __('resale_import.invoice.no_number');
        }
        if ($date === null) {
            $issues[] = (string) __('resale_import.invoice.no_date');
        }
        // Summenabweichung meldet der Allocator über consistencyIssue() — hier nur Parse-Befunde.
        return new ProviderInvoice($number, $date, $credit, $customerNumber, $positions, $netTotal, $issues);
    }

    /**
     * Gutschrift nur anhand der Kopfzeilen vor der ersten Position — eine
     * Position „Storno zu Rechnung 123" macht aus einer Rechnung keine Gutschrift.
     *
     * @param  list<string>  $lines
     */
    private function isCreditNote(array $lines): bool {
        foreach ($lines as $line) {
            if (preg_match(self::POSITION, $line) === 1) {
                return false;
            }
            if (preg_match('/Gutschriftsnr\.|Storno zu Rechnung|Credit Note No\.|Credit Note Date|Date of Credit Note/u', $line) === 1) {
                return true;
            }
        }

        return false;
    }

    /** „3. September 2026", „3 September 2026" oder „September 3, 2026". */
    private function date(string $raw): ?CarbonImmutable {
        if (preg_match('/^(\d{1,2})\.?\s*(\p{L}+),?\s+(\d{4})/u', $raw, $m) === 1) {
            [$day, $monthName, $year] = [(int) $m[1], $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\p{L}+)\s+(\d{1,2}),?\s+(\d{4})/u', $raw, $m) === 1) {
            [$day, $monthName, $year] = [(int) $m[2], $m[1], (int) $m[3]];
        } else {
            return null;
        }
        $month = self::MONTHS[mb_strtolower($monthName)] ?? null;

        return $month === null || ! checkdate($month, $day, $year) ? null : new CarbonImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    /**
     * @return array{key: string|null, name: string|null, contract: string|null}
     */
    private function company(string $raw): array {
        $contract = preg_match(self::CONTRACT, $raw, $c) === 1 ? $c[1] : null;
        if (preg_match('/(CNL\d+)\s*\((.+?)\)\s*(?:(?:Vertrag|Contract):.*)?$/u', $raw, $m) === 1) {
            return ['key' => $m[1], 'name' => trim(preg_replace('/\s+/', ' ', $m[2]) ?? $m[2]), 'contract' => $contract];
        }

        return ['key' => null, 'name' => trim(preg_replace('/\s*(?:Vertrag|Contract):.*$/u', '', $raw) ?? $raw), 'contract' => $contract];
    }

    private function amount(string $value): float {
        return (float) (NumberHelper::normalizeDecimalStringOrNull($value) ?? '0');
    }
}
