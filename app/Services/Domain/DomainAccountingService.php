<?php
/*
 * Created on   : Tue Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainAccountingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Domain;

use App\Enums\Domain\DomainCapabilityArea;
use App\Models\Domain\{DomainAccountingEntry, DomainProjection, DomainProviderConnection, DomainResellerAccount};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper, NumberHelper};
use Illuminate\Support\Carbon;

/**
 * Read-only Reseller-Accounting (Feature 083, MVP-392). `QueryAccountingList`
 * wird ausschließlich lesend verwendet; WorkDiary erzeugt daraus KEINE
 * steuerliche Rechnung. Schreibende Accounting-Befehle sind bewusst nicht
 * implementiert. Dedup über `raw_hash` der Buchungsspalten.
 *
 * Spalten laut CentralNic-Reseller-Doku: ACCOUNTING_DATE, ACCOUNTING_TYPE,
 * ACCOUNTING_DESCRIPTION (bei Domainbuchungen der Domainname), ACCOUNTING_AMOUNT
 * (Menge), ACCOUNTING_PRICE (Netto, Belastung negativ), ACCOUNTING_VAT,
 * ACCOUNTING_VAT_PRICE, ACCOUNTING_CURRENCY, INVOICE_PERIOD (Laufzeit),
 * SUBRESELLER (verursachender Subuser). Befund 2026-10-29: bis dahin las der
 * Sync Schlüssel, die die API nie liefert — 1134 Zeilen ohne Inhalt.
 */
class DomainAccountingService {
    /** Erste Abfrage ab diesem Datum (Monatsrechnungen des Portals seit 2015). */
    public const FIRST_DATE = '2015-01-01';

    /** Folgeabgleich: so viele Tage vor der jüngsten Buchung erneut lesen (Nachbuchungen). */
    public const OVERLAP_DAYS = 31;

    public const PAGE_SIZE = 1000;

    /** Buchungsspalten, über die der Dedup-Hash läuft — die Summenfelder der Antwort (TOTAL, SUM …) ändern sich je Abruf. */
    private const HASHED = ['accounting_date', 'accounting_type', 'accounting_description', 'accounting_amount', 'accounting_price', 'accounting_vat', 'accounting_vat_price', 'accounting_currency', 'invoice_period', 'subreseller', 'accounting_pricing'];

    public function __construct(private readonly DomainProviderResolver $resolver) {}

    /** Spiegelt das Buchungsjournal (Haupt-/Subuser) seitenweise als Projektion. */
    public function sync(DomainProviderConnection $connection): int {
        $adapter = $this->resolver->for($connection);
        $latest = DomainAccountingEntry::query()->where('connection_id', $connection->id)->max('entry_date');
        $minDate = $latest !== null ? Carbon::parse((string) $latest, 'UTC')->subDays(self::OVERLAP_DAYS) : Carbon::parse(self::FIRST_DATE, 'UTC');
        $count = 0;
        $first = 0;
        do {
            $response = $adapter->execute('QueryAccountingList', [
                'MINDATE' => $minDate->format('Y-m-d'), 'FIRST' => $first, 'LIMIT' => self::PAGE_SIZE, 'ORDERBY' => 'ACCOUNTINGDATE', 'ORDER' => 'ASC',
            ], DomainCapabilityArea::Accounting);
            $stored = 0;
            foreach ($response->rows() as $row) {
                // Zeile 0 trägt zusätzlich die Summenfelder; ohne Buchungsdatum ist es keine Buchung.
                if (! isset($row['accounting_date'])) {
                    continue;
                }
                $this->store($connection, $row);
                $stored++;
            }
            $count += $stored;
            $first += self::PAGE_SIZE;
        } while ($stored > 0 && $first < (int) ($response->first('total') ?? 0));

        // Zeilen aus der Zeit der falschen Schlüssel (weder Datum noch Text) tragen nichts.
        DomainAccountingEntry::query()->where('connection_id', $connection->id)->whereNull('entry_date')->whereNull('description')->delete();

        return $count;
    }

    /** @param  array<string, string>  $row */
    private function store(DomainProviderConnection $connection, array $row): void {
        $user = trim((string) ($row['subreseller'] ?? ''));
        $reseller = $user !== '' ? DomainResellerAccount::query()
            ->where('connection_id', $connection->id)
            ->where('external_user', $user)
            ->first() : null;
        $description = trim((string) ($row['accounting_description'] ?? ''));
        // Domain org-weit auflösen (eine Zeile je Domainname, unabhängig von der meldenden Verbindung).
        $domain = self::looksLikeDomain($description) ? DomainProjection::query()
            ->where('organization_id', $connection->organization_id)
            ->where('domain_hash', DomainProjection::hashFor($description))
            ->first() : null;
        $hashed = [];
        foreach (self::HASHED as $key) {
            if (isset($row[$key])) {
                $hashed[$key] = $row[$key];
            }
        }

        DomainAccountingEntry::query()->updateOrCreate(
            [
                'organization_id' => $connection->organization_id,
                'connection_id' => $connection->id,
                'raw_hash' => CryptoHelper::hash(JsonHelper::encode($hashed)),
            ],
            [
                'external_user' => $user,
                'reseller_account_id' => $reseller?->id,
                'domain_projection_id' => $domain?->id,
                'customer_id' => $domain !== null && $domain->customer_id !== null ? $domain->customer_id : $reseller?->customer_id,
                'entry_date' => Carbon::parse($row['accounting_date'], 'UTC'),
                'type' => $row['accounting_type'] ?? null,
                'description' => $description !== '' ? $description : null,
                'invoice_period' => $row['invoice_period'] ?? null,
                'quantity' => NumberHelper::normalizeDecimalStringOrNull($row['accounting_amount'] ?? ''),
                'net_amount' => NumberHelper::normalizeDecimalStringOrNull($row['accounting_price'] ?? ''),
                'vat_rate' => NumberHelper::normalizeDecimalStringOrNull($row['accounting_vat'] ?? ''),
                'tax_amount' => NumberHelper::normalizeDecimalStringOrNull($row['accounting_vat_price'] ?? ''),
                'currency' => isset($row['accounting_currency']) ? CurrencyCode::tryFrom(strtoupper($row['accounting_currency']))?->value : null,
                'synced_at' => Carbon::now(),
            ],
        );
    }

    /** Domainname (Label.TLD) — Gebühren, Einzahlungen und Zonen haben Freitext. */
    public static function looksLikeDomain(string $value): bool {
        return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/i', $value) === 1;
    }
}
