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
use PDFToolkit\Registries\PDFReaderRegistry;

/**
 * Liest Quality-Hosting-Rechnungen und -Gutschriften (PDF, Feature 152,
 * MVP-762). Aufbau je Position: Kopfzeilen „Endkunde: CNL00007 (Name)" und
 * „Vertrag: CNLCON00156", dann „Pos Menge Beschreibung Einzelpreis Gesamt",
 * danach „Dienst:", „Vertrag:", Laufzeit „03.09.26 - 02.09.27". Gutschriften
 * („Storno zu Rechnung", „Gutschriftsnr.") tragen Positionen wie „Umzugsbonus
 * Endkunde Name (CNL00002)" ohne Vertrag — sie gelten der Firma.
 */
final class QualityHostingInvoiceReader {
    private const MONTHS = ['januar' => 1, 'februar' => 2, 'märz' => 3, 'maerz' => 3, 'april' => 4, 'mai' => 5, 'juni' => 6, 'juli' => 7, 'august' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'dezember' => 12, 'january' => 1, 'february' => 2, 'march' => 3, 'may' => 5, 'june' => 6, 'july' => 7, 'october' => 10, 'december' => 12];

    public function read(string $path): ProviderInvoice {
        try {
            $text = (string) (new PDFTextProvider($path))->rowAlignedText();
        } catch (\Throwable) {
            $text = (string) PDFReaderRegistry::getInstance()->extractText($path, ['language' => 'deu+eng'])->getTextOrDefault();
        }

        return $this->parse($text);
    }

    public function parse(string $text): ProviderInvoice {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []), static fn(string $l): bool => $l !== ''));
        $number = '';
        $date = null;
        $credit = str_contains($text, 'Gutschriftsnr.') || str_contains($text, 'Storno zu Rechnung') || str_contains($text, 'Credit Note No.');
        $customerNumber = null;
        $netTotal = null;
        $issues = [];
        $positions = [];
        $company = ['key' => null, 'name' => null];
        $pendingCompany = null; // mehrzeiliger Endkundenname

        foreach ($lines as $index => $line) {
            if (preg_match('/^(?:Rechnungsnr\.|Gutschriftsnr\.|Invoice No\.|Credit Note No\.)\s+(\S+)(?:\s+(?:Kundennr\.|Customer No\.)\s+(\S+))?/u', $line, $m) === 1) {
                $number = $m[1];
                $customerNumber = $m[2] ?? $customerNumber;

                continue;
            }
            if (preg_match('/^(?:Rechnungsdatum|Gutschriftsdatum|Date of Invoice|Date of Credit Note)\s+(\d{1,2})\.\s*(\p{L}+)\s+(\d{4})/u', $line, $m) === 1) {
                $month = self::MONTHS[mb_strtolower($m[2])] ?? null;
                $date = $month === null ? null : new CarbonImmutable(sprintf('%04d-%02d-%02d', (int) $m[3], $month, (int) $m[1]));

                continue;
            }
            if (preg_match('/^Total EUR (?:ohne MwSt\.|Excl\. VAT)\s+(-?[\d.]+,\d{2})/u', $line, $m) === 1) {
                $netTotal = $this->amount($m[1]);

                continue;
            }
            if ($pendingCompany !== null) {
                // Fortsetzung eines umgebrochenen Endkundennamens bis zur schließenden Klammer.
                $pendingCompany .= ' ' . $line;
                if (str_contains($line, ')')) {
                    $company = $this->company($pendingCompany);
                    $pendingCompany = null;
                }

                continue;
            }
            if (preg_match('/^Endkunde:\s+(.+)$/u', $line, $m) === 1) {
                if (str_contains($m[1], ')')) {
                    $company = $this->company($m[1]);
                } else {
                    $pendingCompany = $m[1];
                }

                continue;
            }
            if (preg_match('/^(\d+)\s+(\d+(?:[.,]\d+)?)\s+(.+?)\s+(-?[\d.]+,\d{2,5})\s+(-?[\d.]+,\d{2})$/u', $line, $m) === 1) {
                $description = trim($m[3]);
                // Gutschrift-Positionen mit umgebrochenem Namen: Folgezeile(n) bis „(CNL…)" anhängen.
                $lookahead = $index + 1;
                while (! str_contains($description, '(') || ! str_contains($description, ')')) {
                    if (! isset($lines[$lookahead]) || preg_match('/^(?:\d+\s+\d|Total|Dienst:|Vertrag:|Grundgeb)/u', $lines[$lookahead]) === 1) {
                        break;
                    }
                    if (preg_match('/\(CNL\d+\)/u', $lines[$lookahead]) === 1 || preg_match('/^[\p{L}&.\- ]+\)?$/u', $lines[$lookahead]) === 1) {
                        $description .= ' ' . $lines[$lookahead];
                        $lookahead++;

                        continue;
                    }
                    break;
                }
                $total = $this->amount($m[5]);
                $entry = new ProviderInvoiceLine(
                    position: (int) $m[1],
                    quantity: (float) str_replace(',', '.', $m[2]),
                    description: $description,
                    unitPrice: $credit ? -$this->amount($m[4]) : $this->amount($m[4]),
                    total: $credit ? -$total : $total,
                    companyKey: $company['key'],
                    companyName: $company['name'],
                );
                if (preg_match('/^(.+?)\s+Endkunde\s+(.+?)\s*\((CNL\d+)\)\s*$/u', $description, $c) === 1) {
                    $entry->description = trim($c[1]);
                    $entry->companyName = trim($c[2]);
                    $entry->companyKey = $c[3];
                }
                // Details nach der Position: Vertrag und Laufzeit bis zur nächsten Position.
                for ($j = $index + 1; $j < count($lines); $j++) {
                    $next = $lines[$j];
                    if (preg_match('/^\d+\s+\d+(?:[.,]\d+)?\s+.+\s+-?[\d.]+,\d{2,5}\s+-?[\d.]+,\d{2}$/u', $next) === 1 || str_starts_with($next, 'Endkunde:') || str_starts_with($next, 'Total EUR')) {
                        break;
                    }
                    if ($entry->contract === null && preg_match('/^Vertrag:\s+(\S+)/u', $next, $v) === 1) {
                        $entry->contract = $v[1];
                    } elseif ($entry->periodStart === null && preg_match('/^(\d{2})\.(\d{2})\.(\d{2})\s*-\s*(\d{2})\.(\d{2})\.(\d{2})$/u', $next, $p) === 1) {
                        $entry->periodStart = new CarbonImmutable(sprintf('%04d-%02d-%02d', 2000 + (int) $p[3], (int) $p[2], (int) $p[1]));
                        $entry->periodEnd = new CarbonImmutable(sprintf('%04d-%02d-%02d', 2000 + (int) $p[6], (int) $p[5], (int) $p[4]));
                    }
                }
                $positions[] = $entry;
            }
        }
        if ($number === '') {
            $issues[] = 'Keine Rechnungs-/Gutschriftsnummer gefunden.';
        }
        if ($date === null) {
            $issues[] = 'Kein Belegdatum gefunden.';
        }

        return new ProviderInvoice($number, $date, $credit, $customerNumber, $positions, $netTotal, $issues);
    }

    /**
     * @return array{key: string|null, name: string|null}
     */
    private function company(string $raw): array {
        if (preg_match('/(CNL\d+)\s*\((.+?)\)\s*(?:Vertrag:.*)?$/u', $raw, $m) === 1) {
            return ['key' => $m[1], 'name' => trim(preg_replace('/\s+/', ' ', $m[2]) ?? $m[2])];
        }

        return ['key' => null, 'name' => trim($raw)];
    }

    private function amount(string $value): float {
        return (float) (NumberHelper::normalizeDecimalStringOrNull($value) ?? '0');
    }
}
