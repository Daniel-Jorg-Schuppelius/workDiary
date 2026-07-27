<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Targets;

use App\Enums\Finance\{TransferChannel, TransferTarget};
use App\Models\{Customer, ExternalReference, TimeEntry};
use App\Models\Finance\BillingTransfer;
use App\Plugins\SevDesk\Api\{SevDeskClient, SevDeskClientFactory};
use App\Plugins\SevDesk\{SevDeskConfig, SevDeskPlugin};
use App\Services\Invoicing\BillableTimeAggregator;
use GuzzleHttp\Exception\ConnectException;
use RuntimeException;

/**
 * Übergibt einen bestätigten BillingTransfer als sevDesk-RECHNUNGSENTWURF
 * (MVP-125, Bauturbo A4): POST /Invoice/Factory/saveInvoice mit Status 50
 * (Entwurf) — sevDesk behält die Rechnungshoheit (Nummer, Festschreibung);
 * `enshrine` wird nie aufgerufen.
 *
 * - Buchhaltungs-Version je Mandant ({@see SevDeskClient::bookkeepingVersion()},
 *   gecacht): 2.0 verlangt `taxRule` am Beleg, 1.0 `taxType`/`taxRate`.
 * - Kontakt-Projektion in drei Stufen: bestehende ExternalReference →
 *   sevDesk-Suche über die Kundennummer → Kontakt anlegen (Projektion,
 *   Referenz wird gespeichert).
 * - Idempotenz nach orgaMAX-Muster: (1) ExternalReference je Transfer,
 *   (2) Reconciliation-Scan der jüngsten Rechnungen nach dem Quellmarker
 *   `workdiary:<payload_hash>` (customerInternalNote). Ein Timeout nach dem
 *   Schreiben löst KEINE blinde Wiederholung aus — der nächste Lauf adoptiert
 *   die gefundene Rechnung statt doppelt anzulegen.
 */
class SevDeskTarget implements FacturationTarget {
    use Concerns\LoadsBillingSources;
    use Concerns\ReconcilesByMarker;

    public const EXT_TYPE_INVOICE = 'sevdesk_invoice';

    public const EXT_TYPE_CONTACT = 'contact';

    public const MARKER_PREFIX = 'workdiary:';

    public function __construct(
        private readonly BillableTimeAggregator $aggregator,
        private readonly SevDeskClientFactory $clients,
    ) {}

    public function supports(TransferTarget $target): bool {
        return $target === TransferTarget::SevDesk;
    }

    public function transfer(BillingTransfer $transfer): TargetResult {
        $config = SevDeskConfig::resolve($transfer->organization_id);
        if (empty($config['api_key'])) {
            throw new RuntimeException((string) __('finance.error.sevdesk_not_configured'));
        }

        // (1) Bereits übergeben? (harte Idempotenz je Transfer)
        $existing = $this->existingReference($transfer, SevDeskPlugin::ID, self::EXT_TYPE_INVOICE);
        if ($existing !== null) {
            return new TargetResult(externalReference: $existing);
        }

        $client = $this->clients->for((int) $transfer->organization_id);
        $marker = self::MARKER_PREFIX . $transfer->payload_hash;

        // (2) Reconciliation: hat ein früherer, unklarer Lauf die Rechnung
        // bereits erzeugt? Marker-Scan statt blinder Wiederholung.
        $adopted = $this->findByMarker($client, $marker);
        if ($adopted !== null) {
            return new TargetResult(externalReference: $this->storeReference($transfer, $adopted, $marker, adopted: true));
        }

        $transfer->loadMissing(['items', 'customer']);
        $contactReference = $this->resolveContactReference($transfer, $client);

        $vatRate = (float) $config['default_vat_rate'];
        $positions = $transfer->channel === TransferChannel::Time
            ? $this->timePositions($transfer, $vatRate, (int) $config['unity_hour_id'])
            : $this->materialPositions($transfer, $vatRate, (int) $config['unity_piece_id']);
        if ($positions === []) {
            throw new RuntimeException((string) __('finance.error.no_sources'));
        }

        // contactPerson ist Pflichtfeld der sevDesk-Rechnung (SevUser).
        $sevUser = $client->firstSevUser();
        $sevUserId = (string) ($sevUser['id'] ?? '');
        if ($sevUserId === '') {
            throw new RuntimeException('sevDesk /SevUser returned no user for contactPerson.');
        }

        $intro = (string) __('finance.sevdesk.introduction', [
            'channel' => $transfer->channel->label(),
            'from' => $transfer->period_from?->format('d.m.Y') ?? '—',
            'to' => $transfer->period_to?->format('d.m.Y') ?? '—',
        ]);

        $invoice = [
            'objectName' => 'Invoice',
            'mapAll' => true,
            'invoiceType' => 'RE',
            // Entwurf — sevDesk führt (Nummer/Festschreibung); nie `enshrine`.
            'status' => 50,
            'invoiceDate' => now()->format('Y-m-d'),
            'currency' => $transfer->customer->currency->value,
            'discount' => 0,
            'contact' => ['id' => (int) $contactReference->external_id, 'objectName' => 'Contact'],
            'contactPerson' => ['id' => (int) $sevUserId, 'objectName' => 'SevUser'],
            'header' => $intro,
            // Quellmarker in der internen Notiz — Grundlage der Reconciliation
            // und des Übergabenachweises (erscheint nicht auf dem Beleg).
            'customerInternalNote' => $intro . ' [' . $marker . ']',
        ];

        // Buchhaltungs-Version je Mandant (gecacht): 2.0 ⇒ taxRule, 1.0 ⇒ taxType.
        if ($client->bookkeepingVersion() === '2.0') {
            $invoice['taxRule'] = ['id' => (int) $config['tax_rule_id'], 'objectName' => 'TaxRule'];
        } else {
            $invoice['taxType'] = 'default';
            $invoice['taxRate'] = $vatRate;
            $invoice['taxText'] = (string) __('finance.sevdesk.tax_text', ['rate' => rtrim(rtrim(number_format($vatRate, 2, '.', ''), '0'), '.')]);
        }

        try {
            $body = $client->saveInvoice([
                'invoice' => $invoice,
                'invoicePosSave' => $positions,
                'invoicePosDelete' => null,
            ]);
        } catch (ConnectException) {
            // Timeout/Netzabriss NACH dem Senden: Ausgang unklar — kein
            // blindes Retry; der nächste Lauf reconciled über den Marker.
            throw new RuntimeException((string) __('finance.error.sevdesk_outcome_unclear'));
        }

        $created = is_array($body['invoice'] ?? null) ? $body['invoice'] : $body;
        $externalId = (string) ($created['id'] ?? '');
        if ($externalId === '') {
            throw new RuntimeException('sevDesk saveInvoice returned no id.');
        }

        return new TargetResult(externalReference: $this->storeReference($transfer, ['id' => $externalId] + $created, $marker, adopted: false));
    }

    // ── Reconciliation ──────────────────────────────────────────────────

    /**
     * Jüngste Rechnungen nach dem Quellmarker durchsuchen (begrenztes Fenster).
     *
     * @return array<string, mixed>|null
     */
    private function findByMarker(SevDeskClient $client, string $marker): ?array {
        $pageSize = max(1, (int) config('plugins.sevdesk.page_size', 100));
        $limit = max($pageSize, (int) config('plugins.sevdesk.reconcile_scan_limit', 200));

        for ($offset = 0; $offset < $limit; $offset += $pageSize) {
            $rows = $client->invoices($offset, $pageSize);
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $haystack = implode(' ', array_filter([
                    (string) ($row['customerInternalNote'] ?? ''),
                    (string) ($row['header'] ?? ''),
                    (string) ($row['headText'] ?? ''),
                ]));
                if (str_contains($haystack, $marker) && ! empty($row['id'] ?? null)) {
                    return ['id' => (string) $row['id']] + $row;
                }
            }
            if (count($rows) < $pageSize) {
                break;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $invoice */
    private function storeReference(BillingTransfer $transfer, array $invoice, string $marker, bool $adopted): ExternalReference {
        // Nachweis über den gemeinsamen Baustein (Vollaudit 2026-07, M41).
        return $this->storeMarkerReference($transfer, SevDeskPlugin::ID, self::EXT_TYPE_INVOICE, 'sevdesk', 'invoice', $invoice, $marker, $adopted);
    }

    // ── Kontakt-Projektion ──────────────────────────────────────────────

    /**
     * Kontakt-Auflösung in drei Stufen (Muster LexofficeTarget): bestehende
     * ExternalReference → sevDesk-Suche über die Kundennummer → Kontakt
     * anlegen (Projektion). Die Referenz wird in jedem Fall gespeichert,
     * damit Folge-Übergaben idempotent auflösen.
     */
    private function resolveContactReference(BillingTransfer $transfer, SevDeskClient $client): ExternalReference {
        /** @var Customer $customer */
        $customer = $transfer->customer;

        $existing = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $transfer->organization_id)
            ->where('plugin_id', SevDeskPlugin::ID)
            ->where('external_type', self::EXT_TYPE_CONTACT)
            ->where('referenceable_type', $customer->getMorphClass())
            ->where('referenceable_id', $customer->getKey())
            ->first();
        if ($existing instanceof ExternalReference) {
            return $existing;
        }

        $number = trim((string) $customer->number);

        // Matching über die Kundennummer (dokumentierter /Contact-Filter).
        $matched = null;
        if ($number !== '') {
            foreach ($client->contactsByCustomerNumber($number) as $row) {
                if (is_array($row) && (string) ($row['customerNumber'] ?? '') === $number && ! empty($row['id'] ?? null)) {
                    $matched = $row;
                    break;
                }
            }
        }

        if ($matched === null) {
            $payload = array_filter([
                'objectName' => 'Contact',
                'mapAll' => true,
                'name' => (string) $customer->name,
                'customerNumber' => $number !== '' ? $number : null,
                'category' => [
                    'id' => (int) config('plugins.sevdesk.contact_category_id', 3),
                    'objectName' => 'Category',
                ],
            ], static fn($value): bool => $value !== null);

            try {
                $matched = $client->createContact($payload);
            } catch (ConnectException) {
                // Ausgang unklar — nächster Lauf findet den Kontakt über die
                // Kundennummer wieder, statt ihn doppelt anzulegen.
                throw new RuntimeException((string) __('finance.error.sevdesk_outcome_unclear'));
            }
        }

        $contactId = (string) ($matched['id'] ?? '');
        if ($contactId === '') {
            throw new RuntimeException('sevDesk contact projection returned no id.');
        }

        return ExternalReference::updateOrCreate(
            [
                'plugin_id' => SevDeskPlugin::ID,
                'external_type' => self::EXT_TYPE_CONTACT,
                'referenceable_type' => $customer->getMorphClass(),
                'referenceable_id' => $customer->getKey(),
            ],
            [
                'organization_id' => $transfer->organization_id,
                'external_id' => $contactId,
                'synced_at' => now(),
            ],
        );
    }

    // ── Positionen (Aggregation identisch zu Lexoffice-/orgaMAX-Pfad) ───

    /** @return list<array<string, mixed>> */
    private function timePositions(BillingTransfer $transfer, float $vatRate, int $unityHourId): array {
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
            $fallbackRate = $primary !== null && $primary->hourly_rate !== null
                ? $primary->hourly_rate
                : $transfer->customer->hourly_rate;
            $rate = $block->hourlyRate() ?? $fallbackRate?->toFloat() ?? 0.0;

            $positions[] = [
                'objectName' => 'InvoicePos',
                'mapAll' => true,
                'name' => $block->displayName($transfer),
                'quantity' => $hours,
                'price' => round($rate, 2),
                'taxRate' => $vatRate,
                'unity' => ['id' => $unityHourId, 'objectName' => 'Unity'],
            ];
        }

        return $positions;
    }

    /** @return list<array<string, mixed>> */
    private function materialPositions(BillingTransfer $transfer, float $vatRate, int $unityPieceId): array {
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
                'objectName' => 'InvoicePos',
                'mapAll' => true,
                'name' => $name,
                'quantity' => round(($usage->quantity?->getValue()->toFloat() ?? 0.0), 2),
                'price' => round($usage->unit_price?->toFloat() ?? 0.0, 2),
                'taxRate' => $usage->tax_rate !== null ? round((float) $usage->tax_rate->getNumericValue(), 2) : $vatRate,
                // sevDesk verlangt eine Katalog-Unity; freie Einheitstexte gibt
                // der Vertrag nicht her — Standard: Stück (Pilot verifiziert).
                'unity' => ['id' => $unityPieceId, 'objectName' => 'Unity'],
            ];
        }

        return $positions;
    }
}
