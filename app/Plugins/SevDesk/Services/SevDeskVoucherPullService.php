<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskVoucherPullService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Services;

use App\Models\Finance\AccountingVoucher;
use App\Models\Supplier;
use App\Plugins\SevDesk\Api\SevDeskClientFactory;
use App\Plugins\SevDesk\{SevDeskConfig, SevDeskPlugin};
use Illuminate\Support\Carbon;

/**
 * Beleg-Rückabruf aus sevDesk (Feature 122, MVP-611).
 *
 * Belege, die direkt in der Buchhaltung entstehen — Kassenbon, per Mail an
 * den Steuerberater gegangene Lieferantenrechnung — tauchen in workDiary
 * sonst nie auf, und der Belegfluss hat ein Loch, das niemand sieht.
 *
 * Gespiegelt wird, nicht übernommen: Der Beleg gehört sevDesk. workDiary
 * schreibt nichts zurück und löscht nichts.
 */
class SevDeskVoucherPullService {
    /** Seitengröße des Abrufs; sevDesk liefert jüngste zuerst. */
    private const PAGE_SIZE = 50;

    public function __construct(private readonly SevDeskClientFactory $clients) {}

    /** @return array{read: int, created: int, updated: int} */
    public function pull(int $organizationId, int $pages = 2): array {
        $counters = ['read' => 0, 'created' => 0, 'updated' => 0];

        $config = SevDeskConfig::resolve($organizationId);
        if (empty($config['api_key'])) {
            return $counters;
        }

        $client = $this->clients->for($organizationId);

        for ($page = 0; $page < max(1, $pages); $page++) {
            $rows = $client->vouchers($page * self::PAGE_SIZE, self::PAGE_SIZE);
            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $counters['read']++;
                $this->mirror($organizationId, $row, $counters);
            }

            if (count($rows) < self::PAGE_SIZE) {
                break;
            }
        }

        return $counters;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{read: int, created: int, updated: int}  $counters
     */
    private function mirror(int $organizationId, array $row, array &$counters): void {
        $externalId = trim((string) ($row['id'] ?? ''));
        if ($externalId === '') {
            return;
        }

        $supplierName = trim((string) ($row['supplierName'] ?? ($row['supplier']['name'] ?? '')));
        $supplier = $supplierName !== ''
            ? Supplier::query()->where('organization_id', $organizationId)->where('name', $supplierName)->first()
            : null;

        $voucher = AccountingVoucher::query()->firstOrNew([
            'organization_id' => $organizationId,
            'plugin_id' => SevDeskPlugin::ID,
            'external_id' => $externalId,
        ]);
        $existed = $voucher->exists;

        $voucher->fill([
            'contact_external_id' => trim((string) ($row['supplier']['id'] ?? '')) ?: null,
            'supplier_id' => $supplier?->id,
            'voucher_type' => trim((string) ($row['creditDebit'] ?? '')) ?: null,
            'voucher_status' => trim((string) ($row['status'] ?? '')) ?: null,
            'voucher_number' => trim((string) ($row['voucherNumber'] ?? ($row['description'] ?? ''))) ?: null,
            'voucher_date' => $this->date($row['voucherDate'] ?? null),
            'due_date' => $this->date($row['dueDate'] ?? null),
            'paid_date' => $this->date($row['payDate'] ?? null),
            'total_amount' => $this->amount($row['sumGross'] ?? null),
            'net_amount' => $this->amount($row['sumNet'] ?? null),
            // sevDesk führt keinen Offenposten am Beleg; er ergibt sich aus
            // dem Zahldatum. Eine erfundene Zahl wäre schlechter als keine.
            'open_amount' => null,
            'currency' => trim((string) ($row['currency'] ?? 'EUR')) ?: 'EUR',
            'archived' => false,
            'payload' => $row,
            'synced_at' => Carbon::now(),
        ]);
        $voucher->save();

        $counters[$existed ? 'updated' : 'created']++;
    }

    private function date(mixed $value): ?string {
        $value = trim((string) (is_scalar($value) ? $value : ''));

        return $value !== '' ? Carbon::parse($value)->toDateString() : null;
    }

    private function amount(mixed $value): ?string {
        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : null;
    }
}
