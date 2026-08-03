<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Finance\Targets;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Enums\Finance\{TransferChannel, TransferTarget};
use App\Models\{Customer, ExternalReference};
use App\Models\Finance\BillingTransfer;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeMapper, LexofficePlugin, LexofficeService};
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use App\Services\Finance\BillingPositionBuilder;
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
 * HTTP läuft über {@see PluginApiClient} (php-api-toolkit) — damit bleibt der
 * Adapter mit FakePluginHttp testbar. Fehler werden als RuntimeException
 * hochgereicht; der Controller ruft dann markFailed().
 */
class LexofficeTarget implements FacturationTarget {
    use Concerns\LoadsBillingSources;

    public const EXT_TYPE_INVOICE = 'invoice';

    public function __construct(
        private readonly BillingPositionBuilder $positions,
    ) {}

    public function supports(TransferTarget $target): bool {
        return $target === TransferTarget::Lexoffice;
    }

    public function transfer(BillingTransfer $transfer): TargetResult {
        $config = $this->config($transfer);
        $payload = $this->invoicePayload($transfer, $config);

        // Rechnungsentwurf — bewusst KEIN ?finalize=true (Hoheit bei Lexoffice).
        $response = $this->api($config)->postJson($config['base_url'] . '/invoices', $payload);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Lexoffice invoice draft failed: HTTP %d %s',
                $response->status(),
                mb_substr((string) $response->body(), 0, 500),
            ));
        }

        $body = (array) ($response->json() ?? []);
        $externalId = (string) ($body['id'] ?? '');
        if ($externalId === '') {
            throw new RuntimeException('Lexoffice invoice draft returned no id.');
        }

        $reference = ExternalReference::create([
            'organization_id' => $transfer->organization_id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => self::EXT_TYPE_INVOICE,
            'referenceable_type' => $transfer->getMorphClass(),
            'referenceable_id' => $transfer->getKey(),
            'external_id' => $externalId,
            'payload' => ['lexoffice_id' => $externalId] + $body + ['_request' => $payload],
            'synced_at' => now(),
        ]);

        // Keine App-URL aus SDK/Config ableitbar → externalUrl bewusst weglassen.
        return new TargetResult(externalReference: $reference);
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
    private function invoicePayload(BillingTransfer $transfer, array $config): array {
        $transfer->loadMissing(['items', 'customer']);
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
            'address' => ['contactId' => $this->resolveContactId($customer, $config)],
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
                : (string) __('finance.lexoffice.introduction', [
                    'channel' => $transfer->channel->label(),
                    'from' => $from ?? '—',
                    'to' => $to ?? '—',
                ]),
        ];

        if (filled($transfer->closing_text)) {
            $payload['remark'] = (string) $transfer->closing_text;
        }

        return $payload;
    }

    /**
     * @return array{api_key: ?string, base_url: string, defaults: array<string, mixed>}
     */
    private function config(BillingTransfer $transfer): array {
        $config = LexofficeConfig::resolve($transfer->organization_id);
        if (empty($config['api_key'])) {
            throw new RuntimeException((string) __('finance.error.lexoffice_not_configured'));
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

            $item = [
                'type' => $position->article_id !== null ? 'service' : 'custom',
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
            if ($position->article_id !== null) {
                $item['id'] = $position->article_id;
            }

            $items[] = $item;
        }

        return $items;
    }

    // ── Kontakt ─────────────────────────────────────────────────────────

    /**
     * Kontakt-Auflösung in drei Stufen: bestehende ExternalReference →
     * Lexoffice-Kontaktsuche (E-Mail) → pushContact (bestehender Mechanismus,
     * legt Kontakt + ExternalReference an).
     *
     * @param  array{api_key: ?string, base_url: string, defaults: array<string, mixed>}  $config
     */
    private function resolveContactId(Customer $customer, array $config): string {
        $existing = ExternalReference::query()
            ->forPlugin($customer->organization_id, LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_CONTACT)
            ->forReferenceable($customer)
            ->first();

        if ($existing !== null) {
            return $existing->external_id;
        }

        $email = (string) $customer->email;
        if ($email !== '') {
            $response = $this->api($config)
                ->getResponse($config['base_url'] . '/contacts', ['email' => $email, 'page' => 0, 'size' => 1]);

            if ($response->successful()) {
                $first = ((array) ($response->json('content') ?? []))[0] ?? null;
                if (is_array($first) && ! empty($first['id'])) {
                    ExternalReference::updateOrCreate(
                        [
                            'plugin_id' => LexofficePlugin::ID,
                            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
                            'referenceable_type' => $customer->getMorphClass(),
                            'referenceable_id' => $customer->getKey(),
                        ],
                        [
                            'organization_id' => $customer->organization_id,
                            'external_id' => (string) $first['id'],
                            'synced_at' => now(),
                        ],
                    );

                    return (string) $first['id'];
                }
            }
        }

        // Bestehender pushContact-Mechanismus (mit der für die Org aufgelösten
        // Konfiguration — analog LexofficePlugin::healthCheck()).
        $plugin = new LexofficePlugin(new LexofficeService(
            apiKey: $config['api_key'],
            mapper: new LexofficeMapper,
            defaults: (array) $config['defaults'],
            baseUrl: (string) $config['base_url'],
        ));

        return $plugin->pushContact($customer);
    }

    /** @param  array{api_key: ?string, base_url: string}  $config */
    private function api(array $config): PluginApiClient {
        $client = app(PluginHttpFactory::class)->client('lexoffice', (string) $config['base_url']);
        $client->setAuthentication(new BearerAuthentication((string) $config['api_key']));

        return $client;
    }
}
