<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainAccountingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Domain;

use App\Enums\Domain\DomainCapabilityArea;
use App\Models\Domain\{DomainAccountingEntry, DomainProjection, DomainProviderConnection, DomainResellerAccount};
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Support\Carbon;

/**
 * Read-only Reseller-Accounting (Feature 083, MVP-392). `QueryAccountingList`
 * wird ausschließlich lesend verwendet; WorkDiary erzeugt daraus KEINE
 * steuerliche Rechnung. Schreibende Accounting-Befehle sind bewusst nicht
 * implementiert. Dedup über `raw_hash`.
 */
class DomainAccountingService {
    public function __construct(private readonly DomainProviderResolver $resolver) {}

    /** Spiegelt das Buchungsjournal (Haupt-/Subuser) als Projektion. */
    public function sync(DomainProviderConnection $connection): int {
        $adapter = $this->resolver->for($connection);
        $response = $adapter->execute('QueryAccountingList', [], DomainCapabilityArea::Accounting);

        $count = 0;
        foreach ($response->rows() as $row) {
            $rawHash = hash('sha256', json_encode($row) ?: '');
            $user = $row['user'] ?? $row['subuser'] ?? '';
            $reseller = $user !== '' ? DomainResellerAccount::query()
                ->where('connection_id', $connection->id)
                ->where('external_user', $user)
                ->first() : null;
            // Domain org-weit auflösen (eine Zeile je Domainname, unabhängig
            // von der meldenden Verbindung).
            $domain = isset($row['domain']) ? DomainProjection::query()
                ->where('organization_id', $connection->organization_id)
                ->where('domain_hash', DomainProjection::hashFor($row['domain']))
                ->first() : null;

            $mappedCustomerId = $domain?->customer_id;
            if ($mappedCustomerId === null) {
                $mappedCustomerId = $reseller?->customer_id;
            }

            DomainAccountingEntry::query()->updateOrCreate(
                [
                    'organization_id' => $connection->organization_id,
                    'connection_id' => $connection->id,
                    'raw_hash' => $rawHash,
                ],
                [
                    'external_user' => $user,
                    'accounting_id' => $row['accountingid'] ?? $row['id'] ?? null,
                    'reseller_account_id' => $reseller?->id,
                    'domain_projection_id' => $domain?->id,
                    'customer_id' => $mappedCustomerId,
                    'entry_date' => isset($row['date']) ? Carbon::parse($row['date'], 'UTC') : null,
                    'type' => $row['type'] ?? null,
                    'description' => $row['description'] ?? null,
                    'reference' => $row['reference'] ?? null,
                    'quantity' => $this->num($row['quantity'] ?? null),
                    'net_amount' => $this->num($row['amount'] ?? $row['netamount'] ?? null),
                    'vat_rate' => $this->num($row['vatrate'] ?? null),
                    'tax_amount' => $this->num($row['vat'] ?? $row['taxamount'] ?? null),
                    'currency' => isset($row['currency']) ? CurrencyCode::tryFrom(strtoupper($row['currency']))?->value : null,
                    'synced_at' => Carbon::now(),
                ],
            );
            $count++;
        }

        return $count;
    }

    private function num(?string $value): ?float {
        return $value !== null && is_numeric($value) ? (float) $value : null;
    }
}
