<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Targets;

use App\Enums\Finance\{TransferChannel, TransferTarget};
use App\Models\{ExternalReference, OrgaMaxConnection, TimeEntry};
use App\Models\Finance\BillingTransfer;
use App\Plugins\OrgaMax\Api\{OrgaMaxClient, OrgaMaxClientFactory};
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Services\Invoicing\BillableTimeAggregator;
use GuzzleHttp\Exception\ConnectException;
use RuntimeException;

/**
 * Übergibt einen bestätigten BillingTransfer als orgaMAX-AUFTRAG
 * (Feature 077, MVP-309): Der veröffentlichte API-Vertrag bietet keinen
 * direkten Rechnungs-Create — POST /order/ erzeugt den Auftrag; die
 * Umwandlung in eine Rechnung (POST /order/{id}/invoice) bleibt eine eigene,
 * ausdrücklich bestätigte Aktion (MVP-310) oder geschieht in orgaMAX.
 *
 * Idempotenz: Vor jedem Anlegen wird (1) die bestehende ExternalReference
 * des Transfers geprüft und (2) ein Reconciliation-Scan der jüngsten
 * Aufträge nach dem Quellmarker `workdiary:<payload_hash>` gefahren. Ein
 * Timeout nach dem Schreiben löst KEINE blinde Wiederholung aus — der
 * Transfer scheitert als „Ergebnis unklar" und der nächste Lauf adoptiert
 * den gefundenen Auftrag statt doppelt anzulegen.
 */
class OrgaMaxTarget implements FacturationTarget {
    use Concerns\LoadsBillingSources;
    use Concerns\ReconcilesByMarker;

    public const EXT_TYPE_ORDER = 'orgamax_order';

    public const MARKER_PREFIX = 'workdiary:';

    public function __construct(
        private readonly BillableTimeAggregator $aggregator,
        private readonly OrgaMaxClientFactory $clients,
    ) {}

    public function supports(TransferTarget $target): bool {
        return $target === TransferTarget::OrgaMax;
    }

    public function transfer(BillingTransfer $transfer): TargetResult {
        $connection = OrgaMaxConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $transfer->organization_id)
            ->first();
        if (! $connection instanceof OrgaMaxConnection || ! $connection->isActive()) {
            throw new RuntimeException((string) __('orgamax.error.not_connected'));
        }
        if (! $connection->capabilityEnabled('billing') || $connection->capabilityLeader('billing') !== 'orgamax') {
            throw new RuntimeException((string) __('orgamax.error.billing_capability_disabled'));
        }

        // (1) Bereits übergeben? (harte Idempotenz je Transfer)
        $existing = $this->existingReference($transfer, OrgaMaxPlugin::ID, self::EXT_TYPE_ORDER);
        if ($existing !== null) {
            return new TargetResult(externalReference: $existing);
        }

        $client = $this->clients->for($connection);
        $marker = self::MARKER_PREFIX . $transfer->payload_hash;

        // (2) Reconciliation: hat ein früherer, unklarer Lauf den Auftrag
        // bereits erzeugt? Marker-Scan statt blinder Wiederholung (MVP-309).
        $adopted = $this->findByMarker($client, $marker);
        if ($adopted !== null) {
            return new TargetResult(externalReference: $this->storeReference($transfer, $adopted, $marker, adopted: true));
        }

        $transfer->loadMissing(['items', 'customer']);
        $customerRef = $this->resolveCustomerReference($transfer);

        $positions = $transfer->channel === TransferChannel::Time
            ? $this->timePositions($transfer)
            : $this->materialPositions($transfer);
        if ($positions === []) {
            throw new RuntimeException((string) __('finance.error.no_sources'));
        }

        $payload = [
            'customerId' => $customerRef->external_id,
            // Quellmarker in Referenz UND interner Notiz — Grundlage der
            // Reconciliation und des Übergabenachweises.
            'reference' => $marker,
            'internalNote' => (string) __('orgamax.order.internal_note', [
                'channel' => $transfer->channel->label(),
                'from' => $transfer->period_from?->format('d.m.Y') ?? '—',
                'to' => $transfer->period_to?->format('d.m.Y') ?? '—',
            ]) . ' [' . $marker . ']',
            'positions' => $positions,
        ];

        try {
            $body = $client->createOrder($payload);
        } catch (ConnectException) {
            // Timeout/Netzabriss NACH dem Senden: Ausgang unklar — kein
            // blindes Retry; der nächste Lauf reconciled über den Marker.
            throw new RuntimeException((string) __('orgamax.error.outcome_unclear'));
        }

        $externalId = (string) ($body['id'] ?? $body['orderId'] ?? '');
        if ($externalId === '') {
            throw new RuntimeException('orgaMAX order create returned no id.');
        }

        return new TargetResult(externalReference: $this->storeReference($transfer, ['id' => $externalId] + $body, $marker, adopted: false));
    }

    // ── Reconciliation ──────────────────────────────────────────────────

    /**
     * Jüngste Aufträge nach dem Quellmarker durchsuchen (begrenztes Fenster).
     *
     * @return array<string, mixed>|null
     */
    private function findByMarker(OrgaMaxClient $client, string $marker): ?array {
        $pageSize = max(1, (int) config('plugins.orgamax.page_size', 100));
        $limit = max($pageSize, (int) config('plugins.orgamax.reconcile_scan_limit', 200));

        for ($offset = 0; $offset < $limit; $offset += $pageSize) {
            $rows = $client->orders($offset, $pageSize);
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $haystack = implode(' ', array_filter([
                    (string) ($row['reference'] ?? ''),
                    (string) ($row['internalNote'] ?? ''),
                    (string) ($row['note'] ?? ''),
                ]));
                if (str_contains($haystack, $marker) && ! empty($row['id'] ?? $row['orderId'] ?? null)) {
                    return ['id' => (string) ($row['id'] ?? $row['orderId'])] + $row;
                }
            }
            if (count($rows) < $pageSize) {
                break;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $order */
    private function storeReference(BillingTransfer $transfer, array $order, string $marker, bool $adopted): ExternalReference {
        // Nachweis über den gemeinsamen Baustein (Vollaudit 2026-07, M41).
        return $this->storeMarkerReference($transfer, OrgaMaxPlugin::ID, self::EXT_TYPE_ORDER, 'orgamax', 'order', $order, $marker, $adopted);
    }

    /** Kundenzuordnung NUR über die bestehende ExternalReference — fehlende Zuordnung ⇒ Inbox statt Schattenstammdaten. */
    private function resolveCustomerReference(BillingTransfer $transfer): ExternalReference {
        $customer = $transfer->customer;
        $reference = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $transfer->organization_id)
            ->where('plugin_id', OrgaMaxPlugin::ID)
            ->where('external_type', 'customer')
            ->where('referenceable_type', $customer->getMorphClass())
            ->where('referenceable_id', $customer->getKey())
            ->first();
        if (! $reference instanceof ExternalReference) {
            throw new RuntimeException((string) __('orgamax.error.customer_unmapped', ['customer' => $customer->name]));
        }

        return $reference;
    }

    // ── Positionen (Aggregation identisch zu Lexoffice-/Invoice-Pfad) ───

    /** @return list<array<string, mixed>> */
    private function timePositions(BillingTransfer $transfer): array {
        // Quellen über das gemeinsame Skelett (Vollaudit 2026-07, M41).
        $entries = $this->loadTimeEntries($transfer);
        $entriesById = $entries->keyBy('id');
        $positions = [];

        foreach ($this->aggregator->aggregate($entries) as $block) {
            $hours = $block->billedHours();
            if ($hours <= 0) {
                continue;
            }
            /** @var TimeEntry|null $primary */
            $primary = $entriesById->get($block->primaryEntryId);
            $rate = $block->hourlyRate()
                ?? (float) ($primary?->hourly_rate ?: $transfer->customer->hourly_rate ?: 0);

            $positions[] = [
                'description' => $block->displayName($transfer),
                'quantity' => $hours,
                'unit' => 'h',
                'unitPrice' => round($rate, 2),
            ];
        }

        return $positions;
    }

    /** @return list<array<string, mixed>> */
    private function materialPositions(BillingTransfer $transfer): array {
        // Quellen über das gemeinsame Skelett (Vollaudit 2026-07, M41).
        $usages = $this->loadMaterialUsages($transfer);

        $positions = [];
        foreach ($usages as $usage) {
            $name = trim((string) $usage->description) ?: (string) __('Material');
            $date = $usage->timesheet?->work_date?->format('d.m.Y');
            if ($date !== null) {
                $name .= ' (' . $date . ')';
            }

            $positions[] = [
                'description' => $name,
                'quantity' => round((float) $usage->quantity, 2),
                'unit' => $usage->unit !== '' ? (string) $usage->unit : (string) __('invoicing.unit_piece'),
                'unitPrice' => round((float) ($usage->unit_price ?? 0), 2),
            ];
        }

        return $positions;
    }
}
