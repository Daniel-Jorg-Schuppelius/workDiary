<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyLedgerImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Etsy\Services;

use App\Models\{EtsyConnection, EtsyLedgerEntry, Organization};
use App\Plugins\Etsy\Api\EtsyClientFactory;
use App\Plugins\Etsy\EtsyConfig;
use Carbon\CarbonImmutable;

/**
 * Etsy-Ledger-Import (Feature 101, MVP-498): Fenster-Sweep über die
 * PFLICHT-Parameter `min_created`/`max_created` (W0 §6), Upsert je
 * (Org, ledger_entry_id), Checkpoint `ledger_max_created` in der Connection.
 * Einträge mit `reference_type=payment` werden per Batch-Abruf
 * (`GET .../payments?payment_ids=…`) mit ihrer `receipt_id` verknüpft.
 * Ein leeres Fenster ist ein Ergebnis — der Checkpoint wandert trotzdem.
 * Läuft im selben `etsy:sync`-Lauf wie der Bestellimport; ohne aktive
 * Verbindung ist der Import ein stiller No-op (der Bestellimport meldet
 * den Zustand bereits).
 */
class EtsyLedgerImportService {
    /** Payment-Batchgröße des Nachzugs (payment_ids ist Pflichtparameter). */
    private const PAYMENT_BATCH = 25;

    public function __construct(private readonly EtsyClientFactory $clients) {}

    /** @return array{imported: int, updated: int, linked: int} */
    public function import(Organization $organization): array {
        $counters = ['imported' => 0, 'updated' => 0, 'linked' => 0];

        $connection = EtsyConnection::query()
            ->where('organization_id', $organization->id)
            ->first();
        if (! $connection instanceof EtsyConnection || ! $connection->isActive()) {
            return $counters;
        }

        $client = $this->clients->for($connection);
        $config = EtsyConfig::resolve((int) $organization->id);
        $limit = max(1, min(100, (int) config('plugins.etsy.page_size', 100)));
        $budget = max(1, (int) $config['sync_page_budget']);
        $now = CarbonImmutable::now()->getTimestamp();
        $since = $this->windowStart($connection, $config['import_from'] ?? null);

        $paymentIds = [];
        $offset = 0;
        for ($page = 0; $page < $budget; $page++) {
            $result = $client->ledgerEntries((int) $connection->shop_id, $since, $now, $limit, $offset);
            foreach ($result['results'] as $entry) {
                $this->ingest($organization, $entry, $counters, $paymentIds);
            }
            if (count($result['results']) < $limit) {
                break;
            }
            $offset += $limit;
        }

        // Payment-Verknüpfung: receipt_id je Payment nachziehen.
        $counters['linked'] = $this->linkPayments($organization, $client, (int) $connection->shop_id, $paymentIds);

        // Leeres Fenster ist ein Ergebnis — Checkpoint trotzdem fortschreiben.
        $connection->rememberCheckpoint('ledger_max_created', $now);

        return $counters;
    }

    // ── Intern ──────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $entry
     * @param array{imported: int, updated: int, linked: int} $counters
     * @param list<int> $paymentIds
     */
    private function ingest(Organization $organization, array $entry, array &$counters, array &$paymentIds): void {
        $entryId = (int) ($entry['entry_id'] ?? 0);
        if ($entryId <= 0) {
            return;
        }

        $referenceType = self::stringOrNull($entry['reference_type'] ?? null);
        $referenceId = self::stringOrNull($entry['reference_id'] ?? null);

        $row = EtsyLedgerEntry::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'ledger_entry_id' => $entryId],
            [
                'ledger_type' => self::stringOrNull($entry['ledger_type'] ?? null),
                'amount' => is_numeric($entry['amount'] ?? null) ? (int) $entry['amount'] : 0,
                'balance' => is_numeric($entry['balance'] ?? null) ? (int) $entry['balance'] : 0,
                'currency' => self::stringOrNull($entry['currency'] ?? null),
                'description' => mb_substr((string) ($entry['description'] ?? ''), 0, 255) ?: null,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'posted_at' => is_numeric($entry['created_timestamp'] ?? $entry['create_date'] ?? null)
                    ? CarbonImmutable::createFromTimestampUTC((int) ($entry['created_timestamp'] ?? $entry['create_date']))
                    : null,
            ],
        );

        $row->wasRecentlyCreated ? $counters['imported']++ : $counters['updated']++;

        if ($referenceType === 'payment' && $referenceId !== null && ctype_digit($referenceId) && $row->receipt_id === null) {
            $paymentIds[] = (int) $referenceId;
        }
    }

    /**
     * Payment→Receipt-Verknüpfung über den Batch-Abruf (payment_ids ist
     * Pflichtparameter; W0 §6 — das Payment-Schema trägt `receipt_id`).
     *
     * @param list<int> $paymentIds
     */
    private function linkPayments(Organization $organization, \App\Plugins\Etsy\Api\EtsyClient $client, int $shopId, array $paymentIds): int {
        $linked = 0;
        foreach (array_chunk(array_values(array_unique($paymentIds)), self::PAYMENT_BATCH) as $chunk) {
            foreach ($client->payments($shopId, $chunk)['results'] as $payment) {
                $paymentId = (int) ($payment['payment_id'] ?? 0);
                $receiptId = (int) ($payment['receipt_id'] ?? 0);
                if ($paymentId <= 0 || $receiptId <= 0) {
                    continue;
                }
                $linked += EtsyLedgerEntry::query()
                    ->where('organization_id', $organization->id)
                    ->where('reference_type', 'payment')
                    ->where('reference_id', (string) $paymentId)
                    ->whereNull('receipt_id')
                    ->update(['receipt_id' => $receiptId]);
            }
        }

        return $linked;
    }

    /** Fensterbeginn: Checkpoint → `import_from` → Erstlauf-Fenster (mit Überlappung). */
    private function windowStart(EtsyConnection $connection, ?string $importFrom): int {
        $overlap = max(0, (int) config('plugins.etsy.overlap_minutes', 5)) * 60;

        $checkpoint = $connection->checkpoint('ledger_max_created');
        if ($checkpoint > 0) {
            return max(0, $checkpoint - $overlap);
        }

        if ($importFrom !== null && trim($importFrom) !== '') {
            try {
                return CarbonImmutable::parse(trim($importFrom))->startOfDay()->getTimestamp();
            } catch (\Throwable) {
                // Unlesbares Datum → Erstlauf-Fenster.
            }
        }

        $window = max(1, (int) config('plugins.etsy.initial_window_days', 30));

        return CarbonImmutable::now()->subDays($window)->getTimestamp();
    }

    private static function stringOrNull(mixed $value): ?string {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
