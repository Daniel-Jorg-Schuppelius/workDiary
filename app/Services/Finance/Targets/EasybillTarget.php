<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Targets;

use App\Enums\Finance\{TransferChannel, TransferTarget};
use App\Models\{Customer, ExternalReference};
use App\Models\Finance\BillingTransfer;
use App\Plugins\Easybill\Api\{EasybillClient, EasybillClientFactory};
use App\Plugins\Easybill\{EasybillConfig, EasybillPlugin};
use App\Services\Finance\BillingPositionBuilder;
use GuzzleHttp\Exception\ConnectException;
use RuntimeException;

/**
 * Übergibt einen bestätigten BillingTransfer als easybill-RECHNUNGSENTWURF
 * (MVP-431): POST /documents mit type INVOICE — easybill behält die
 * Rechnungshoheit (Nummer, Fertigstellung); `/documents/{id}/done` wird nie
 * aufgerufen.
 *
 * - Preise der easybill-Positionen sind CENTS (150 = 1,50 €) — Umrechnung
 *   hier, Steuersatz je Position über `vat_percent`.
 * - Kunden-Projektion in drei Stufen (Muster Lexoffice/sevDesk): bestehende
 *   ExternalReference → easybill-Filter über die Kundennummer → Kunde anlegen.
 * - Idempotenz nach orgaMAX-/sevDesk-Muster: (1) ExternalReference je
 *   Transfer, (2) Reconciliation-Scan über das easybill-Feld `external_id`
 *   (= Quellmarker `workdiary:<payload_hash>`) im document_date-Fenster der
 *   letzten Tage — GET /documents kennt keinen external_id-Filter. Ein
 *   Timeout nach dem Senden löst KEINE blinde Wiederholung aus.
 */
class EasybillTarget implements FacturationTarget {
    use Concerns\LoadsBillingSources;
    use Concerns\ReconcilesByMarker;

    public const EXT_TYPE_INVOICE = 'easybill_invoice';

    public const EXT_TYPE_CUSTOMER = 'contact';

    public const MARKER_PREFIX = 'workdiary:';

    public function __construct(
        private readonly BillingPositionBuilder $positionBuilder,
        private readonly EasybillClientFactory $clients,
    ) {}

    public function supports(TransferTarget $target): bool {
        return $target === TransferTarget::Easybill;
    }

    public function transfer(BillingTransfer $transfer): TargetResult {
        $config = EasybillConfig::resolve((int) $transfer->organization_id);
        if (empty($config['api_key'])) {
            throw new RuntimeException((string) __('finance.error.easybill_not_configured'));
        }

        // (1) Bereits übergeben? (harte Idempotenz je Transfer)
        $existing = $this->existingReference($transfer, EasybillPlugin::ID, self::EXT_TYPE_INVOICE);
        if ($existing !== null) {
            return new TargetResult(externalReference: $existing);
        }

        $client = $this->clients->for((int) $transfer->organization_id);
        $marker = self::MARKER_PREFIX . $transfer->payload_hash;

        // (2) Reconciliation: hat ein früherer, unklarer Lauf den Beleg
        // bereits erzeugt? external_id-Scan statt blinder Wiederholung.
        $adopted = $this->findByMarker($client, $marker);
        if ($adopted !== null) {
            return new TargetResult(externalReference: $this->storeReference($transfer, $adopted, $marker, adopted: true));
        }

        $transfer->loadMissing(['items', 'customer']);
        $customerReference = $this->resolveCustomerReference($transfer, $client);

        $vatRate = (float) $config['default_vat_rate'];
        $positions = $this->positions($transfer, $vatRate);
        if ($positions === []) {
            throw new RuntimeException((string) __('finance.error.no_sources'));
        }

        // Rechnungstexte des Nachweises (MVP-491), sonst der Standardtext.
        $intro = filled($transfer->intro_text)
            ? (string) $transfer->intro_text
            : (string) __('finance.easybill.introduction', [
                'channel' => $transfer->channel->label(),
                'from' => $transfer->period_from?->format('d.m.Y') ?? '—',
                'to' => $transfer->period_to?->format('d.m.Y') ?? '—',
            ]);

        $document = [
            'type' => 'INVOICE',
            'customer_id' => (int) $customerReference->external_id,
            'currency' => $transfer->customer->currency->value,
            'document_date' => now()->format('Y-m-d'),
            'text_prefix' => $intro,
            'text' => filled($transfer->closing_text) ? (string) $transfer->closing_text : '',
            // Quellmarker: easybill-Feld external_id trägt die Idempotenz-
            // Kennung — Grundlage der Reconciliation (erscheint nicht auf dem Beleg).
            'external_id' => $marker,
            'items' => $positions,
        ];

        // E-Rechnungs-Format des Belegs (bestimmt auch den Rückabruf über
        // /documents/{id}/download): xrechnung3_0_xml bzw. zugferd2_5_en16931.
        $format = (string) ($config['einvoice_format'] ?? '');
        if ($format !== '') {
            $document['file_format_config'] = [['type' => $format]];
        }

        try {
            $created = $client->createInvoiceDraft($document);
        } catch (ConnectException) {
            // Timeout/Netzabriss NACH dem Senden: Ausgang unklar — kein
            // blindes Retry; der nächste Lauf reconciled über external_id.
            throw new RuntimeException((string) __('finance.error.easybill_outcome_unclear'));
        }

        $externalId = (string) ($created['id'] ?? '');
        if ($externalId === '') {
            throw new RuntimeException('easybill POST /documents returned no id.');
        }

        return new TargetResult(externalReference: $this->storeReference($transfer, $created, $marker, adopted: false));
    }

    // ── Reconciliation ──────────────────────────────────────────────────

    /**
     * Rechnungen der letzten Tage nach dem Quellmarker im external_id-Feld
     * durchsuchen (begrenztes document_date-Fenster).
     *
     * @return array<string, mixed>|null
     */
    private function findByMarker(EasybillClient $client, string $marker): ?array {
        $pageSize = max(1, (int) config('plugins.easybill.page_size', 100));
        $days = max(1, (int) config('plugins.easybill.reconcile_scan_days', 7));
        $from = now()->subDays($days)->format('Y-m-d');
        $to = now()->format('Y-m-d');

        for ($page = 1; $page <= 10; $page++) {
            $rows = $client->invoicesByDateRange($from, $to, $page, $pageSize);
            foreach ($rows as $row) {
                if (is_array($row) && (string) ($row['external_id'] ?? '') === $marker && ! empty($row['id'] ?? null)) {
                    return ['id' => (string) $row['id']] + $row;
                }
            }
            if (count($rows) < $pageSize) {
                break;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $document */
    private function storeReference(BillingTransfer $transfer, array $document, string $marker, bool $adopted): ExternalReference {
        // Nachweis über den gemeinsamen Baustein (Vollaudit 2026-07, M41).
        return $this->storeMarkerReference($transfer, EasybillPlugin::ID, self::EXT_TYPE_INVOICE, 'easybill', 'document', $document, $marker, $adopted);
    }

    // ── Kunden-Projektion ───────────────────────────────────────────────

    /**
     * Kundenauflösung in drei Stufen (Muster Lexoffice/sevDesk): bestehende
     * ExternalReference → easybill-Nummernfilter → Kunde anlegen. Die
     * Referenz wird gespeichert, damit Folge-Übergaben idempotent auflösen.
     */
    private function resolveCustomerReference(BillingTransfer $transfer, EasybillClient $client): ExternalReference {
        /** @var Customer $customer */
        $customer = $transfer->customer;

        $existing = ExternalReference::query()
            ->forPlugin($transfer->organization_id, EasybillPlugin::ID, self::EXT_TYPE_CUSTOMER)
            ->forReferenceable($customer)
            ->first();
        if ($existing instanceof ExternalReference) {
            return $existing;
        }

        $number = trim((string) $customer->number);

        $matched = null;
        if ($number !== '') {
            foreach ($client->customersByNumber($number) as $row) {
                if (is_array($row) && (string) ($row['number'] ?? '') === $number && ! empty($row['id'] ?? null)) {
                    $matched = $row;
                    break;
                }
            }
        }

        if ($matched === null) {
            $payload = array_filter([
                'company_name' => (string) $customer->name,
                'number' => $number !== '' ? $number : null,
            ], static fn($value): bool => $value !== null);

            try {
                $matched = $client->createCustomer($payload);
            } catch (ConnectException) {
                // Ausgang unklar — nächster Lauf findet den Kunden über die
                // Nummer wieder, statt ihn doppelt anzulegen.
                throw new RuntimeException((string) __('finance.error.easybill_outcome_unclear'));
            }
        }

        $customerId = (string) ($matched['id'] ?? '');
        if ($customerId === '') {
            throw new RuntimeException('easybill customer projection returned no id.');
        }

        return ExternalReference::updateOrCreate(
            [
                'plugin_id' => EasybillPlugin::ID,
                'external_type' => self::EXT_TYPE_CUSTOMER,
                'referenceable_type' => $customer->getMorphClass(),
                'referenceable_id' => $customer->getKey(),
            ],
            [
                'organization_id' => $transfer->organization_id,
                'external_id' => $customerId,
                'synced_at' => now(),
            ],
        );
    }

    // ── Positionen ──────────────────────────────────────────────────────

    /**
     * Eine Position je eingefrorener {@see \App\Models\Finance\BillingTransferPosition}
     * (MVP-487). easybill kennt keinen Artikelkatalog in workDiary — Bezeichnung,
     * Einheit, Text und Preis kommen daher aus der Position.
     *
     * @return list<array<string, mixed>>
     */
    private function positions(BillingTransfer $transfer, float $vatRate): array {
        // Vollständigkeits-Guard der Quellen bleibt (M41).
        $transfer->channel === TransferChannel::Time
            ? $this->loadTimeEntries($transfer)
            : $this->loadMaterialUsages($transfer);

        $positions = [];

        foreach ($this->positionBuilder->positionsFor($transfer) as $position) {
            if ($position->quantityFloat() <= 0) {
                continue;
            }

            $description = trim($position->name . "\n" . (string) $position->description);

            $positions[] = [
                'type' => 'POSITION',
                'description' => $description,
                'quantity' => round($position->quantityFloat(), 2),
                // easybill-Vertrag: Preise in Cents (150 = 1,50 €).
                'single_price_net' => round($position->unitPriceFloat() * 100, 2),
                'vat_percent' => $position->vat_rate !== null ? (float) $position->vat_rate : $vatRate,
                'unit' => (string) ($position->unit_name ?: __('finance.easybill.unit_hour')),
            ];
        }

        return $positions;
    }
}
