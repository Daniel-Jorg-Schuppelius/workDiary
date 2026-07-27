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
use App\Models\{Customer, ExternalReference, TimeEntry};
use App\Models\Finance\BillingTransfer;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeMapper, LexofficePlugin, LexofficeService};
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use App\Services\Invoicing\BillableTimeAggregator;
use RuntimeException;

/**
 * Übergibt einen bestätigten BillingTransfer als RECHNUNGSENTWURF an
 * Lexoffice (Feature 045, „Lexoffice führt"): POST /v1/invoices OHNE
 * finalize — Lexoffice behält die Rechnungshoheit (Nummer, Finalisierung).
 *
 * - Kanal Zeit: Positionen über die bestehende Abrechnungsaggregation
 *   ({@see BillableTimeAggregator}) — dieselbe Taktung/Blockbildung wie bei
 *   lokalen Rechnungen und beim Voucher-Export; Beträge aus den
 *   rate-Snapshots der Einträge.
 * - Kanal Material: eine Position je Materialverwendung (Menge, Einheit,
 *   Einzelpreis netto).
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
        private readonly BillableTimeAggregator $aggregator,
    ) {}

    public function supports(TransferTarget $target): bool {
        return $target === TransferTarget::Lexoffice;
    }

    public function transfer(BillingTransfer $transfer): TargetResult {
        $config = LexofficeConfig::resolve($transfer->organization_id);
        if (empty($config['api_key'])) {
            throw new RuntimeException((string) __('finance.error.lexoffice_not_configured'));
        }

        $transfer->loadMissing(['items', 'customer']);
        $customer = $transfer->customer;
        $defaults = (array) $config['defaults'];
        $currency = $customer->currency->value;

        $lineItems = $transfer->channel === TransferChannel::Time
            ? $this->timeLineItems($transfer, $currency, $defaults)
            : $this->materialLineItems($transfer, $currency, $defaults);

        if ($lineItems === []) {
            throw new RuntimeException((string) __('finance.error.no_sources'));
        }

        $contactId = $this->resolveContactId($customer, $config);

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
            'introduction' => (string) __('finance.lexoffice.introduction', [
                'channel' => $transfer->channel->label(),
                'from' => $from ?? '—',
                'to' => $to ?? '—',
            ]),
        ];

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

    // ── Positionen ──────────────────────────────────────────────────────

    /**
     * Zeit-Positionen über die BESTEHENDE Aggregation (Taktung + Blockbildung,
     * identisch zu InvoiceGenerator/LexofficeMapper): ein lineItem je Block,
     * quantity = aufgerundete Stunden, unitPrice = Satz aus den
     * rate-Snapshots der Einträge.
     *
     * @param  array<string, mixed>  $defaults
     * @return list<array<string, mixed>>
     */
    private function timeLineItems(BillingTransfer $transfer, string $currency, array $defaults): array {
        // Quellen über das gemeinsame Skelett (Vollaudit 2026-07, M41).
        $entries = $this->loadTimeEntries($transfer);

        $vatRate = (float) ($defaults['default_vat_rate'] ?? 19.0);
        $entriesById = $entries->keyBy('id');
        $items = [];

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

            $items[] = [
                'type' => 'custom',
                'name' => $block->displayName($transfer, withDescription: true),
                'quantity' => $hours,
                'unitName' => 'h',
                'unitPrice' => [
                    'currency' => $currency,
                    'netAmount' => round($rate, 2),
                    'taxRatePercentage' => $vatRate,
                ],
            ];
        }

        return $items;
    }

    /**
     * Material-Positionen: ein lineItem je Materialverwendung (Menge,
     * Einheit, Einzelpreis netto aus dem Verwendungs-Snapshot).
     *
     * @param  array<string, mixed>  $defaults
     * @return list<array<string, mixed>>
     */
    private function materialLineItems(BillingTransfer $transfer, string $currency, array $defaults): array {
        // Quellen über das gemeinsame Skelett (Vollaudit 2026-07, M41).
        $usages = $this->loadMaterialUsages($transfer);

        $vatRate = (float) ($defaults['default_vat_rate'] ?? 19.0);
        $items = [];

        foreach ($usages as $usage) {
            $name = trim((string) $usage->description) ?: (string) __('Material');
            $date = $usage->timesheet?->work_date?->format('d.m.Y');
            if ($date !== null) {
                $name .= ' (' . $date . ')';
            }

            $items[] = [
                'type' => 'custom',
                'name' => $name,
                'quantity' => round(($usage->quantity?->getValue()->toFloat() ?? 0.0), 2),
                'unitName' => $usage->unit !== '' ? (string) $usage->unit : (string) __('invoicing.unit_piece'),
                'unitPrice' => [
                    'currency' => $currency,
                    'netAmount' => round($usage->unit_price?->toFloat() ?? 0.0, 2),
                    'taxRatePercentage' => $vatRate,
                ],
            ];
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
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)
            ->where('referenceable_type', $customer->getMorphClass())
            ->where('referenceable_id', $customer->getKey())
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
