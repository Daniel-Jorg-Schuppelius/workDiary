<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyReceiptImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Etsy\Services;

use App\Models\{ArticleVariant, Customer, EtsyConnection, EtsyReceipt, ExternalArticleMapping, Organization};
use App\Plugins\Etsy\Api\EtsyClientFactory;
use App\Plugins\Etsy\{EtsyConfig, EtsyPlugin};
use App\Services\Integration\{IntegrationResolver, MatchProfileRegistry};
use App\Services\Integration\Match\MatchProfile;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

/**
 * Etsy-Bestellimport Inbox-First (Feature 101, MVP-495; Muster Billbee):
 * Sweep über `min_last_modified` (Epoch-Aufholpunkt aus den
 * Connection-Checkpoints, Überlappung gegen Uhrendrift), aufsteigend nach
 * `updated` sortiert — der Checkpoint wird nach JEDER Seite fortgeschrieben,
 * ein abgebrochener Lauf verliert nichts. Seiten-Budget je Lauf schont das
 * Etsy-Tageslimit (gekappte Läufe melden `truncated`, keine stille Kappung).
 * Upsert je (Org, receipt_id); Käufer werden über den
 * {@see IntegrationResolver} zugeordnet — eindeutige Treffer bzw. bestehende
 * Referenzen verlinken, alles andere wird Inbox-Vorschlag, Gast-Käufe ohne
 * `buyer_user_id` bleiben bewusst ohne Inbox-Fall (Policy §18.4).
 * Der Webhook-Pfad (MVP-496) läuft über {@see importSingle()} in denselben
 * Einzel-Ingest — beide Wege sind dadurch idempotent zueinander.
 */
class EtsyReceiptImportService {
    public function __construct(
        private readonly EtsyClientFactory $clients,
        private readonly IntegrationResolver $resolver,
        private readonly MatchProfileRegistry $profiles,
    ) {}

    /** @return array{imported: int, updated: int, linked: int, staged: int, truncated: bool} */
    public function import(Organization $organization): array {
        $connection = $this->activeConnection($organization);
        $client = $this->clients->for($connection);
        $profile = $this->customerProfile();

        $config = EtsyConfig::resolve((int) $organization->id);
        $limit = max(1, min(100, (int) config('plugins.etsy.page_size', 100)));
        $budget = max(1, (int) $config['sync_page_budget']);
        $counters = ['imported' => 0, 'updated' => 0, 'linked' => 0, 'staged' => 0, 'truncated' => false];

        $since = $this->checkpoint($connection, $organization, $config['import_from'] ?? null);

        try {
            $offset = 0;
            $lastPageFull = false;
            for ($page = 0; $page < $budget; $page++) {
                $result = $client->receipts((int) $connection->shop_id, $since, $limit, $offset);
                $maxSeen = 0;
                foreach ($result['results'] as $receipt) {
                    $this->ingest($organization, $profile, $receipt, $counters);
                    $maxSeen = max($maxSeen, $this->modifiedEpoch($receipt));
                }
                // Aufsteigend sortiert → nach jeder Seite fortschreiben.
                if ($maxSeen > $connection->checkpoint('receipts_last_modified')) {
                    $connection->rememberCheckpoint('receipts_last_modified', $maxSeen);
                }

                $lastPageFull = count($result['results']) >= $limit;
                if (! $lastPageFull) {
                    break;
                }
                $offset += $limit;
            }
            $counters['truncated'] = $lastPageFull;

            $connection->forceFill([
                'last_synced_at' => CarbonImmutable::now(),
                'last_sync_counters' => $counters,
            ])->save();
            $connection->recordConnectionSuccess();
        } catch (Throwable $e) {
            // Nur die Fehlerklasse — nie Payload/Token (MVP-315-Regel).
            $connection->recordConnectionFailure(class_basename($e));

            throw $e;
        }

        return $counters;
    }

    /**
     * Einzel-Receipt nachladen und einspielen (Webhook-Pfad, MVP-496). Die
     * URL wird aus shop_id + receipt_id gegen die fixe Base-URL gebaut — die
     * `resource_url` aus dem Payload wird NIE abgerufen (SSRF-Disziplin).
     */
    public function importSingle(Organization $organization, int $receiptId): void {
        $connection = $this->activeConnection($organization);
        $receipt = $this->clients->for($connection)->receipt((int) $connection->shop_id, $receiptId);
        if ($receipt === null) {
            return; // 404: der Sweep heilt, falls das Receipt später auftaucht.
        }

        $counters = ['imported' => 0, 'updated' => 0, 'linked' => 0, 'staged' => 0, 'truncated' => false];
        $this->ingest($organization, $this->customerProfile(), $receipt, $counters);
    }

    // ── Intern ──────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $receipt
     * @param array{imported: int, updated: int, linked: int, staged: int, truncated: bool} $counters
     */
    private function ingest(Organization $organization, MatchProfile $profile, array $receipt, array &$counters): void {
        $receiptId = (int) ($receipt['receipt_id'] ?? 0);
        if ($receiptId <= 0) {
            return;
        }

        $buyerExternalId = trim((string) ($receipt['buyer_user_id'] ?? ''));
        $buyerExternalId = ($buyerExternalId === '0') ? '' : $buyerExternalId;

        // Gast-Käufe (keine buyer_user_id) bewusst ohne Auflösung/Inbox —
        // Einmalkäufer würden die Inbox fluten (Policy §18.4).
        $customer = $buyerExternalId !== ''
            ? $this->resolveCustomer($organization, $profile, $buyerExternalId, $receipt, $counters)
            : null;

        $grandtotal = is_array($receipt['grandtotal'] ?? null) ? $receipt['grandtotal'] : [];

        $row = EtsyReceipt::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'receipt_id' => $receiptId],
            [
                'status' => self::stringOrNull($receipt['status'] ?? null),
                'was_paid' => (bool) ($receipt['is_paid'] ?? false),
                'was_shipped' => (bool) ($receipt['is_shipped'] ?? false),
                'currency' => self::stringOrNull($grandtotal['currency_code'] ?? null),
                'total_gross' => self::money($receipt['grandtotal'] ?? null),
                'total_shipping' => self::money($receipt['total_shipping_cost'] ?? null),
                'total_tax' => self::money($receipt['total_tax_cost'] ?? null),
                'discount' => self::money($receipt['discount_amt'] ?? null),
                'buyer_external_id' => $buyerExternalId !== '' ? $buyerExternalId : null,
                'buyer' => $this->buyer($receipt),
                'items' => $this->items($receipt),
                'raw' => $this->trimmedRaw($receipt),
                'ordered_at' => self::epochOrNull($receipt['created_timestamp'] ?? $receipt['create_timestamp'] ?? null),
                'etsy_modified_at' => self::epochOrNull($receipt['updated_timestamp'] ?? $receipt['update_timestamp'] ?? null),
                'customer_id' => $customer?->id,
                'inbox_status' => $customer !== null ? EtsyReceipt::INBOX_LINKED : EtsyReceipt::INBOX_OPEN,
            ],
        );

        $row->wasRecentlyCreated ? $counters['imported']++ : $counters['updated']++;

        $this->mapArticles($organization, $row->items ?? []);
    }

    /**
     * SKU→Artikel-Zuordnung (MVP-495, Muster BillbeeArticleMappingService):
     * je Transaction-Position eine `ExternalArticleMapping`-Zeile
     * (external_id = Etsy-listing_id, external_number = SKU); eindeutige
     * SKU-Treffer im Variantenstamm werden verlinkt, der Rest bleibt als
     * `pending` sichtbar — kein Inbox-Fall (Policy, Feature-Doku).
     *
     * @param array<int, mixed> $items
     */
    private function mapArticles(Organization $organization, array $items): void {
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $listingId = isset($item['listing_id']) ? (string) $item['listing_id'] : '';
            if ($listingId === '') {
                continue;
            }
            $sku = trim((string) ($item['sku'] ?? ''));

            $variant = $sku !== ''
                ? ArticleVariant::query()->withoutGlobalScopes()
                    ->where('sku', $sku)
                    ->whereHas('article', fn($q) => $q->withoutGlobalScopes()->where('organization_id', $organization->id))
                    ->first()
                : null;

            ExternalArticleMapping::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'plugin_id' => EtsyPlugin::ID,
                    'external_id' => $listingId,
                ],
                [
                    'external_number' => $sku !== '' ? $sku : null,
                    'article_variant_id' => $variant?->id,
                    'article_id' => $variant?->article_id,
                    'sync_status' => $variant !== null ? 'synced' : 'pending',
                    'last_synced_at' => now(),
                ],
            );
        }
    }

    /**
     * Käuferauflösung Inbox-First (Billbee-Muster): bestehende Referenz/
     * eindeutiger Treffer verlinkt, sonst Inbox-Vorschlag; nach einer
     * Verlinkung werden offene Spiegelzeilen desselben Käufers nachgezogen.
     *
     * @param array<string, mixed> $receipt
     * @param array{imported: int, updated: int, linked: int, staged: int, truncated: bool} $counters
     */
    private function resolveCustomer(Organization $organization, MatchProfile $profile, string $buyerExternalId, array $receipt, array &$counters): ?Customer {
        $outcome = $this->resolver->resolve(
            $organization,
            EtsyPlugin::ID,
            $profile,
            'customer',
            $buyerExternalId,
            array_filter([
                'name' => self::stringOrNull($receipt['name'] ?? null),
                'email' => self::stringOrNull($receipt['buyer_email'] ?? null),
            ], static fn(?string $value): bool => $value !== null),
            $this->buyer($receipt) ?? [],
            source: 'etsy',
        );

        if (! $outcome->isResolved()) {
            // Conflict-Item trotz bestehender Referenz: die ZUORDNUNG steht
            // fest — nur der Datenabgleich bleibt sichtbar in der Inbox.
            $customer = $this->customerByReference($organization, $buyerExternalId);
            if (! $customer instanceof Customer) {
                $counters['staged']++;

                return null;
            }
        } else {
            /** @var Customer $customer */
            $customer = $outcome->model;
        }

        $counters['linked']++;

        // Wiederkäufer: offene Spiegelzeilen desselben Käufers nachziehen.
        EtsyReceipt::query()
            ->where('organization_id', $organization->id)
            ->where('buyer_external_id', $buyerExternalId)
            ->whereNull('customer_id')
            ->update(['customer_id' => $customer->id, 'inbox_status' => EtsyReceipt::INBOX_LINKED]);

        return $customer;
    }

    /** Kunde aus bestehender Käufer-Referenz (Zuordnung bereits entschieden). */
    private function customerByReference(Organization $organization, string $buyerExternalId): ?Customer {
        $reference = \App\Models\ExternalReference::query()
            ->forPlugin($organization, EtsyPlugin::ID, 'customer')
            ->forExternalId($buyerExternalId)
            ->first();
        $referenceable = $reference?->referenceable;

        return $referenceable instanceof Customer ? $referenceable : null;
    }

    private function activeConnection(Organization $organization): EtsyConnection {
        $connection = EtsyConnection::query()
            ->where('organization_id', $organization->id)
            ->first();
        if (! $connection instanceof EtsyConnection || ! $connection->isActive()) {
            throw new RuntimeException((string) __('Keine aktive Etsy-Verbindung für diese Organisation.'));
        }

        return $connection;
    }

    private function customerProfile(): MatchProfile {
        $profile = $this->profiles->for(Customer::class);
        if (! $profile instanceof MatchProfile) {
            throw new RuntimeException('Kein MatchProfile für Customer registriert.');
        }

        return $profile;
    }

    /**
     * Aufholpunkt (Epoch-Sekunden): Checkpoint → jüngste bekannte Änderung im
     * Spiegel → `import_from`-Setting → Erstlauf-Fenster; immer minus
     * Überlappung (Uhrendrift).
     */
    private function checkpoint(EtsyConnection $connection, Organization $organization, ?string $importFrom): int {
        $overlap = max(0, (int) config('plugins.etsy.overlap_minutes', 5)) * 60;

        $checkpoint = $connection->checkpoint('receipts_last_modified');
        if ($checkpoint > 0) {
            return max(0, $checkpoint - $overlap);
        }

        $latest = EtsyReceipt::query()
            ->where('organization_id', $organization->id)
            ->max('etsy_modified_at');
        if ($latest !== null) {
            return max(0, CarbonImmutable::parse((string) $latest)->getTimestamp() - $overlap);
        }

        if ($importFrom !== null && trim($importFrom) !== '') {
            try {
                return CarbonImmutable::parse(trim($importFrom))->startOfDay()->getTimestamp();
            } catch (Throwable) {
                // Unlesbares Datum → Erstlauf-Fenster.
            }
        }

        $window = max(1, (int) config('plugins.etsy.initial_window_days', 30));

        return CarbonImmutable::now()->subDays($window)->getTimestamp();
    }

    /** @param array<string, mixed> $receipt */
    private function modifiedEpoch(array $receipt): int {
        $value = $receipt['updated_timestamp'] ?? $receipt['update_timestamp'] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Käufer datensparsam: Name, E-Mail, Versandadresse — keine
     * Nachrichten-Freitexte (Policy §18.3, DSGVO-Datenminimierung).
     *
     * @param array<string, mixed> $receipt
     * @return array<string, string>|null
     */
    private function buyer(array $receipt): ?array {
        $buyer = array_filter([
            'name' => self::stringOrNull($receipt['name'] ?? null),
            'email' => self::stringOrNull($receipt['buyer_email'] ?? null),
            'first_line' => self::stringOrNull($receipt['first_line'] ?? null),
            'second_line' => self::stringOrNull($receipt['second_line'] ?? null),
            'city' => self::stringOrNull($receipt['city'] ?? null),
            'state' => self::stringOrNull($receipt['state'] ?? null),
            'zip' => self::stringOrNull($receipt['zip'] ?? null),
            'country_iso' => self::stringOrNull($receipt['country_iso'] ?? null),
        ], static fn(?string $value): bool => $value !== null);

        return $buyer !== [] ? $buyer : null;
    }

    /**
     * Positionen als reduzierte Transactions (SKU-/Artikel-Anker, Menge,
     * Preis als Decimal-String) — nicht der volle Transaction-Payload.
     *
     * @param array<string, mixed> $receipt
     * @return list<array<string, mixed>>|null
     */
    private function items(array $receipt): ?array {
        $items = [];
        foreach (is_array($receipt['transactions'] ?? null) ? $receipt['transactions'] : [] as $transaction) {
            if (! is_array($transaction)) {
                continue;
            }
            $items[] = array_filter([
                'transaction_id' => isset($transaction['transaction_id']) ? (int) $transaction['transaction_id'] : null,
                'listing_id' => isset($transaction['listing_id']) ? (int) $transaction['listing_id'] : null,
                'sku' => self::stringOrNull($transaction['sku'] ?? null),
                'title' => self::stringOrNull($transaction['title'] ?? null),
                'quantity' => isset($transaction['quantity']) ? (int) $transaction['quantity'] : null,
                'price' => self::money($transaction['price'] ?? null),
            ], static fn($value): bool => $value !== null);
        }

        return $items !== [] ? $items : null;
    }

    /**
     * Roh-Payload gekürzt (Policy §18.3): ohne die bereits in Spalten/json
     * gemappten Felder, ohne Käufer-PII-Duplikate und ohne Freitexte.
     *
     * @param array<string, mixed> $receipt
     * @return array<string, mixed>|null
     */
    private function trimmedRaw(array $receipt): ?array {
        $trimmed = array_diff_key($receipt, array_flip([
            'transactions', 'shipments',
            'name', 'buyer_email', 'first_line', 'second_line', 'city', 'state', 'zip', 'formatted_address', 'country_iso',
            'message_from_buyer', 'message_from_seller', 'message_from_payment', 'gift_message', 'gift_sender',
            'grandtotal', 'subtotal', 'total_price', 'total_shipping_cost', 'total_tax_cost', 'discount_amt',
        ]));

        return $trimmed !== [] ? $trimmed : null;
    }

    /** Money-Objekt {amount, divisor} → Decimal-String (nie float durchreichen). */
    private static function money(mixed $value): string {
        if (! is_array($value)) {
            return '0.00';
        }
        $amount = is_numeric($value['amount'] ?? null) ? (int) $value['amount'] : 0;
        $divisor = is_numeric($value['divisor'] ?? null) ? (int) $value['divisor'] : 100;
        if ($divisor <= 0) {
            $divisor = 100;
        }

        return number_format($amount / $divisor, 2, '.', '');
    }

    private static function stringOrNull(mixed $value): ?string {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private static function epochOrNull(mixed $value): ?CarbonImmutable {
        return is_numeric($value) && (int) $value > 0
            ? CarbonImmutable::createFromTimestampUTC((int) $value)
            : null;
    }
}
