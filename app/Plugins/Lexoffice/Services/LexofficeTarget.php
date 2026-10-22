<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Services;

use APIToolkit\Entities\ID;
use APIToolkit\Exceptions\ApiException;
use App\Enums\Finance\{TransferChannel, TransferTarget};
use App\Models\Customer\Customer;
use App\Models\Finance\BillingTransfer;
use App\Models\Integration\ExternalReference;
use App\Plugins\Lexoffice\Api\LexofficeClientFactory;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin};
use App\Services\Finance\BillingPositionBuilder;
use App\Services\Finance\Targets\Concerns\{LoadsBillingSources, ReconcilesByMarker};
use App\Services\Finance\Targets\{FacturationTarget, TargetResult};
use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\ConnectException;
use InvalidArgumentException;
use Lexoffice\API\Client;
use Lexoffice\API\Endpoints\Documents\InvoicesEndpoint;
use Lexoffice\API\Endpoints\VoucherListEndpoint;
use Lexoffice\Entities\Documents\Invoices\Invoice;
use RuntimeException;

/**
 * Übergibt einen bestätigten BillingTransfer als RECHNUNGSENTWURF an
 * Lexoffice (Feature 045, „Lexoffice führt"): POST /v1/invoices OHNE
 * finalize — Lexoffice behält die Rechnungshoheit (Nummer, Finalisierung).
 *
 * - Positionen: die beim Bestätigen eingefrorenen
 *   {@see \App\Models\Finance\BillingTransferPosition} (MVP-487) — Taktung,
 *   Preisfindung, Standardleistung und Text stecken im
 *   {@see BillingPositionBuilder}, hier bleibt nur die Abbildung auf den
 *   Lexoffice-Vertrag (inkl. Artikel-`id` und `description`).
 * - Kontakt: bestehende ExternalReference (contact) des Kunden; sonst
 *   Lookup über die Lexoffice-Kontaktsuche; als letzter Weg der bestehende
 *   pushContact-Mechanismus des {@see LexofficePlugin}.
 *
 * Kontaktsuche, Belegliste, Abruf und POST teilen sich einen gedrosselten
 * SDK-Client. Fehler werden als RuntimeException hochgereicht; der
 * Controller ruft dann markFailed().
 *
 * Gegen Dubletten (Konsolidierungs-Audit 2026-10, k2-01): bestehender Nachweis
 * gewinnt; ein Abbruch nach dem Senden gilt als „Ausgang unklar"; nach einem
 * Fehlversuch sucht der nächste Lauf den Entwurf über den Quellmarker. Lexoffice
 * hat kein internes Notizfeld — der Marker steht deshalb in der Schlussbemerkung
 * des Entwurfs (Entscheidung des Inhabers).
 */
class LexofficeTarget implements FacturationTarget {
    use LoadsBillingSources;
    use ReconcilesByMarker;

    public const EXT_TYPE_INVOICE = 'invoice';

    /** Kurzform des Positions-Hashes: steht sichtbar auf dem Entwurf. */
    public const MARKER_PREFIX = 'WD-';

    private const MARKER_HASH_LENGTH = 16;

    public function __construct(
        private readonly BillingPositionBuilder $positions,
        private readonly LexofficeArticleCatalogSource $articles,
        private readonly LexofficeContactLookup $contacts,
    ) {}

    public function supports(TransferTarget $target): bool {
        return $target === TransferTarget::Lexoffice;
    }

    public function transfer(BillingTransfer $transfer): TargetResult {
        $config = $this->config($transfer);

        $existing = $this->existingReference($transfer, LexofficePlugin::ID, self::EXT_TYPE_INVOICE);
        if ($existing !== null) {
            return new TargetResult(externalReference: $existing);
        }

        $transfer->loadMissing(['items', 'customer']);
        $client = app(LexofficeClientFactory::class)->sdk((string) $config['api_key'], $config['base_url'], $config['request_interval']);
        $marker = self::MARKER_PREFIX . substr((string) $transfer->payload_hash, 0, self::MARKER_HASH_LENGTH);
        $contactId = $this->resolveContactId($transfer->customer, $client, $config);

        // Der Marker-Scan kostet je Entwurf eine Anfrage — nur nach einem Fehlversuch nötig.
        $firstFailure = $transfer->journal()->where('event', 'failed')->min('created_at');
        if ($firstFailure !== null) {
            $adopted = $this->findByMarker($client, $contactId, $marker, CarbonImmutable::parse($firstFailure));
            if ($adopted !== null) {
                return new TargetResult(externalReference: $this->storeReference($transfer, $adopted, $marker, adopted: true));
            }
        }

        $payload = $this->invoicePayload($transfer, $config, $contactId, $marker);

        try {
            // Rechnungsentwurf — bewusst KEIN ?finalize=true (Hoheit bei Lexoffice).
            $body = (new InvoicesEndpoint($client))->create(Invoice::fromArray($payload))->toArray();
        } catch (ConnectException) {
            // Abbruch nach dem Senden: der Entwurf kann angelegt sein — der nächste Lauf sucht ihn.
            throw new RuntimeException((string) __('lexoffice::finance.error.lexoffice_outcome_unclear'));
        } catch (ApiException $e) {
            throw new RuntimeException(sprintf(
                'Lexoffice invoice draft failed: HTTP %d %s',
                $e->getCode(),
                mb_substr((string) $e->getContent(), 0, 500),
            ), 0, $e);
        } catch (InvalidArgumentException $e) {
            // isValid() der Entität: nichts gesendet, der Controller markiert den Fehlversuch.
            throw new RuntimeException('Lexoffice invoice draft failed: ' . $e->getMessage(), 0, $e);
        }

        if ((string) ($body['id'] ?? '') === '') {
            throw new RuntimeException('Lexoffice invoice draft returned no id.');
        }

        // Keine App-URL aus SDK/Config ableitbar → externalUrl bewusst weglassen.
        return new TargetResult(externalReference: $this->storeReference($transfer, $body + ['_request' => $payload], $marker, adopted: false));
    }

    /**
     * Entwürfe des Kontakts seit dem ersten Fehlversuch nach dem Marker
     * durchsuchen. Die Belegliste trägt die Bemerkung nicht, deshalb je
     * Kandidat ein Detailabruf — begrenzt auf die jüngsten Entwürfe.
     *
     * @return array<string, mixed>|null
     */
    private function findByMarker(Client $client, string $contactId, string $marker, CarbonImmutable $since): ?array {
        $limit = max(1, (int) config('plugins.lexoffice.reconcile_scan_limit', 25));
        try {
            $rows = (new VoucherListEndpoint($client))->search([
                'voucherType' => 'invoice',
                'voucherStatus' => 'draft',
                'contactId' => $contactId,
                'sort' => 'createdDate,DESC',
                'page' => 0,
                'size' => $limit,
            ])->getValues();
        } catch (ApiException $e) {
            throw new RuntimeException(sprintf('Lexoffice voucherlist failed: HTTP %d', $e->getCode()), 0, $e);
        }

        // Ein Tag Spielraum gegen Zeitzonen- und Uhrenabweichung.
        $since = $since->subDay();
        foreach ($rows as $row) {
            $row = $row->toArray();
            $id = (string) ($row['id'] ?? '');
            $created = (string) ($row['createdDate'] ?? '');
            if ($id === '' || ($created !== '' && CarbonImmutable::parse($created)->lt($since))) {
                continue;
            }

            try {
                $detail = (new InvoicesEndpoint($client))->get(new ID($id))->toArray();
            } catch (ApiException) {
                continue;
            }
            if (str_contains((string) ($detail['remark'] ?? ''), $marker)) {
                return ['id' => $id] + $detail;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $invoice  Lexoffice-Antwort bzw. übernommener Entwurf */
    private function storeReference(BillingTransfer $transfer, array $invoice, string $marker, bool $adopted): ExternalReference {
        $externalId = (string) $invoice['id'];

        return ExternalReference::create([
            'organization_id' => $transfer->organization_id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => self::EXT_TYPE_INVOICE,
            'referenceable_type' => $transfer->getMorphClass(),
            'referenceable_id' => $transfer->getKey(),
            'external_id' => $externalId,
            'payload' => ['lexoffice_id' => $externalId, 'marker' => $marker, 'adopted_via_reconciliation' => $adopted] + $invoice,
            'synced_at' => now(),
        ]);
    }

    /**
     * Rechnungs-Payload: Positionen, Kontakt, Steuer- und
     * Leistungszeitraum-Konditionen.
     *
     * Bewusst nur für die Anlage — die Lexoffice-API kennt für Belege weder
     * Update noch Delete (im SDK haben nur Vouchers/Articles/Contacts ein
     * update()/delete()). Korrekturen laufen deshalb über „Korrektur
     * vorbereiten" am Nachweis: Entwurf drüben löschen, hier neu übertragen.
     *
     * @param  array{api_key: ?string, base_url: string, defaults: array<string, mixed>}  $config
     * @return array<string, mixed>
     */
    private function invoicePayload(BillingTransfer $transfer, array $config, string $contactId, string $marker): array {
        $customer = $transfer->customer;
        $defaults = (array) $config['defaults'];
        $currency = $customer->currency->value;

        $lineItems = $this->lineItems($transfer, $currency, $defaults);
        if ($lineItems === []) {
            throw new RuntimeException((string) __('finance.error.no_sources'));
        }

        $from = $transfer->period_from?->toDateString();
        $to = $transfer->period_to?->toDateString();

        $payload = [
            'voucherDate' => now()->format('Y-m-d\TH:i:s.vP'),
            'address' => ['contactId' => $contactId],
            'lineItems' => $lineItems,
            'totalPrice' => ['currency' => $currency],
            'taxConditions' => ['taxType' => (string) ($defaults['default_tax_type'] ?? 'net')],
            'shippingConditions' => $from !== null && $to !== null
                ? ['shippingType' => 'serviceperiod', 'shippingDate' => $from . 'T00:00:00.000+01:00', 'shippingEndDate' => $to . 'T00:00:00.000+01:00']
                : ['shippingType' => 'none'],
            // Rechnungstexte des Nachweises (MVP-491); ohne sie der bisherige
            // Standardtext, damit nie ein leerer Beleg rausgeht.
            'introduction' => filled($transfer->intro_text)
                ? (string) $transfer->intro_text
                : (string) __('lexoffice::finance.lexoffice.introduction', [
                    'channel' => $transfer->channel->label(),
                    'from' => $from ?? '—',
                    'to' => $to ?? '—',
                ]),
        ];

        $markerLine = (string) __('lexoffice::finance.lexoffice.transfer_marker', ['marker' => $marker]);
        $payload['remark'] = filled($transfer->closing_text)
            ? $transfer->closing_text . "\n\n" . $markerLine
            : $markerLine;

        return $payload;
    }

    /**
     * @return array{api_key: ?string, base_url: string, defaults: array<string, mixed>, request_interval: float}
     */
    private function config(BillingTransfer $transfer): array {
        $config = LexofficeConfig::resolve($transfer->organization_id);
        if (empty($config['api_key'])) {
            throw new RuntimeException((string) __('lexoffice::finance.error.lexoffice_not_configured'));
        }

        return $config;
    }

    // ── Positionen ──────────────────────────────────────────────────────

    /**
     * Ein lineItem je eingefrorener Position (MVP-487): Taktung, Preisfindung,
     * Standardleistung und Text stecken im {@see BillingPositionBuilder} — hier
     * bleibt nur die Abbildung auf den Lexoffice-Vertrag.
     *
     * Mit hinterlegter Standardleistung wird der Artikel referenziert
     * (`id` + `type` service/material), sonst wie bisher `custom`. Der
     * Positionstext (Standardtext + Leistungstext + Leistungsdatum) geht in
     * `description` — bislang blieb das Feld ungenutzt.
     *
     * @param  array<string, mixed>  $defaults
     * @return list<array<string, mixed>>
     */
    private function lineItems(BillingTransfer $transfer, string $currency, array $defaults): array {
        // Vollständigkeits-Guard der Quellen bleibt (M41): fehlt eine Quelle,
        // ist der Nachweis unvollständig — dann lieber gar nicht senden.
        $transfer->channel === TransferChannel::Time
            ? $this->loadTimeEntries($transfer)
            : $this->loadMaterialUsages($transfer);

        $vatRate = (float) ($defaults['default_vat_rate'] ?? 19.0);
        $items = [];

        foreach ($this->positions->positionsFor($transfer) as $position) {
            if ($position->quantityFloat() <= 0) {
                continue;
            }

            $articleId = $this->articles->externalId((int) $transfer->organization_id, $position->article_ref);
            $item = [
                'type' => $articleId !== null ? 'service' : 'custom',
                'name' => $position->name,
                'quantity' => $position->quantityFloat(),
                'unitName' => (string) ($position->unit_name ?: __('invoicing.unit_hour')),
                'unitPrice' => [
                    'currency' => $currency,
                    'netAmount' => round($position->unitPriceFloat(), 2),
                    'taxRatePercentage' => $position->vat_rate !== null ? (float) $position->vat_rate : $vatRate,
                ],
            ];

            if (filled($position->description)) {
                $item['description'] = (string) $position->description;
            }
            if ($articleId !== null) {
                $item['id'] = $articleId;
            }

            $items[] = $item;
        }

        return $items;
    }

    // ── Kontakt ─────────────────────────────────────────────────────────

    /**
     * Kontakt-Auflösung in drei Stufen: Nachweis bzw. Kontaktsuche
     * ({@see LexofficeContactLookup}) → pushContact (bestehender Mechanismus,
     * legt Kontakt + ExternalReference an).
     *
     * @param  array{api_key: ?string, base_url: string, defaults: array<string, mixed>, request_interval: float}  $config
     */
    private function resolveContactId(Customer $customer, Client $client, array $config): string {
        $known = $this->contacts->find($customer, $client);
        if ($known !== null) {
            return $known;
        }

        // Bestehender pushContact-Mechanismus (mit der für die Org aufgelösten
        // Konfiguration — analog LexofficePlugin::healthCheck()).
        $plugin = new LexofficePlugin(new LexofficeService(
            apiKey: $config['api_key'],
            mapper: new LexofficeMapper,
            defaults: (array) $config['defaults'],
            baseUrl: (string) $config['base_url'],
            requestInterval: $config['request_interval'],
        ));

        return $plugin->pushContact($customer);
    }
}
