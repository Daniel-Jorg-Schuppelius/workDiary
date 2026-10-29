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
use App\Services\Domain\Concerns\ReadsProviderFields;
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
 * SUBRESELLER (verursachender Subuser); der Live-Gateway schreibt dieselben Namen
 * ohne Unterstriche. Befund 2026-10-29: bis dahin las der Sync Schlüssel, die die
 * API nie liefert — 1134 Zeilen ohne Inhalt.
 */
class DomainAccountingService {
    use ReadsProviderFields;

    /** Erste Abfrage ab diesem Datum (Monatsrechnungen des Portals seit 2015). */
    public const FIRST_DATE = '2015-01-01';

    /** Folgeabgleich: so viele Tage vor der jüngsten Buchung erneut lesen (Nachbuchungen). */
    public const OVERLAP_DAYS = 31;

    public const PAGE_SIZE = 1000;

    /**
     * Logische Felder → Kandidatenschlüssel (Wissensdatenbank mit Unterstrich, Live-Gateway ohne;
     * {@see ReadsProviderFields}). Über diese Felder läuft auch der Dedup-Hash — die Summenfelder
     * der Antwort (TOTAL, SUM …) ändern sich je Abruf.
     *
     * @var array<string, list<string>>
     */
    private const FIELDS = [
        'date' => ['accountingdate', 'date'],
        'type' => ['accountingtype', 'type'],
        'description' => ['accountingdescription', 'description'],
        'object' => ['accountingobject', 'object', 'domain'],
        'amount' => ['accountingamount', 'amount', 'quantity'],
        'price' => ['accountingprice', 'price', 'netamount'],
        'vat' => ['accountingvat', 'vatrate', 'vat'],
        'vat_price' => ['accountingvatprice', 'vatprice', 'taxamount'],
        'currency' => ['accountingcurrency', 'currency'],
        'period' => ['invoiceperiod', 'period'],
        'subreseller' => ['subreseller', 'subuser', 'user'],
        'pricing' => ['accountingpricing', 'pricing'],
    ];

    /**
     * Zusätzliche Felder des Live-Gateways (domain:contract 2026-10-29: „accounting invoice id“,
     * „accounting reference“) — bewusst nicht im Dedup-Hash, damit bestehende Zeilen ihre Kennung
     * behalten und nur ergänzt werden.
     *
     * @var array<string, list<string>>
     */
    private const EXTRA_FIELDS = [
        'invoice_number' => ['accountinginvoiceid', 'invoiceid', 'invoice'],
        'reference' => ['accountingreference', 'reference'],
    ];

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
                $fields = [];
                foreach (self::FIELDS as $name => $keys) {
                    $fields[$name] = self::providerField($row, $keys);
                }
                // Zeile 0 trägt zusätzlich die Summenfelder; ohne Buchungsdatum ist es keine Buchung.
                if ($fields['date'] === null) {
                    continue;
                }
                $extra = [];
                foreach (self::EXTRA_FIELDS as $name => $keys) {
                    $extra[$name] = self::providerField($row, $keys);
                }
                $this->store($connection, $fields, $extra);
                $stored++;
            }
            $count += $stored;
            $first += self::PAGE_SIZE;
        } while ($stored > 0 && $first < (int) ($response->first('total') ?? 0));

        // Zeilen aus der Zeit der falschen Schlüssel (weder Datum noch Text) tragen nichts.
        DomainAccountingEntry::query()->where('connection_id', $connection->id)->whereNull('entry_date')->whereNull('description')->delete();

        return $count;
    }

    /**
     * @param  array<string, string|null>  $fields  logische Felder laut {@see self::FIELDS}
     * @param  array<string, string|null>  $extra  Felder laut {@see self::EXTRA_FIELDS}
     */
    private function store(DomainProviderConnection $connection, array $fields, array $extra = []): void {
        $user = (string) ($fields['subreseller'] ?? '');
        $reseller = $user !== '' ? DomainResellerAccount::query()
            ->where('connection_id', $connection->id)
            ->where('external_user', $user)
            ->first() : null;
        $description = (string) ($fields['description'] ?? '');
        $domainName = self::looksLikeDomain((string) ($fields['object'] ?? '')) ? (string) $fields['object'] : (self::looksLikeDomain($description) ? $description : null);
        // Domain org-weit auflösen (eine Zeile je Domainname, unabhängig von der meldenden Verbindung).
        $domain = $domainName !== null ? DomainProjection::query()
            ->where('organization_id', $connection->organization_id)
            ->where('domain_hash', DomainProjection::hashFor($domainName))
            ->first() : null;
        // Der Live-Gateway nennt keinen Subreseller — das Hauptjournal enthält aber die Buchungen der
        // Subuser-Domains (Prod 2026-10-29: 388 für lds-systems). Der Subuser kommt dann über die Domain.
        if ($reseller === null && $domain?->reseller_account_id !== null) {
            $reseller = DomainResellerAccount::query()->find($domain->reseller_account_id);
            $user = $reseller->external_user ?? $user;
        }

        DomainAccountingEntry::query()->updateOrCreate(
            [
                'organization_id' => $connection->organization_id,
                'connection_id' => $connection->id,
                'raw_hash' => CryptoHelper::hash(JsonHelper::encode(array_filter($fields, static fn(?string $v): bool => $v !== null))),
            ],
            [
                'external_user' => $user,
                'reseller_account_id' => $reseller?->id,
                'domain_projection_id' => $domain?->id,
                'customer_id' => $domain !== null && $domain->customer_id !== null ? $domain->customer_id : $reseller?->customer_id,
                'entry_date' => Carbon::parse((string) $fields['date'], 'UTC'),
                'type' => $fields['type'],
                'description' => $description !== '' ? $description : $domainName,
                'invoice_period' => $fields['period'],
                'invoice_number' => $extra['invoice_number'] ?? null,
                'reference' => $extra['reference'] ?? null,
                'quantity' => NumberHelper::normalizeDecimalStringOrNull($fields['amount'] ?? ''),
                'net_amount' => NumberHelper::normalizeDecimalStringOrNull($fields['price'] ?? ''),
                'vat_rate' => NumberHelper::normalizeDecimalStringOrNull($fields['vat'] ?? ''),
                'tax_amount' => NumberHelper::normalizeDecimalStringOrNull($fields['vat_price'] ?? ''),
                'currency' => $fields['currency'] !== null ? CurrencyCode::tryFrom(strtoupper($fields['currency']))?->value : null,
                'synced_at' => Carbon::now(),
            ],
        );
    }

    /** Domainname (Label.TLD) — Gebühren, Einzahlungen und Zonen haben Freitext. */
    public static function looksLikeDomain(string $value): bool {
        return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/i', $value) === 1;
    }
}
