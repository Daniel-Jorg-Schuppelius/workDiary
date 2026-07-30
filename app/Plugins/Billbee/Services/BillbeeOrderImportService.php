<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillbeeOrderImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Billbee\Services;

use App\Models\{BillbeeOrder, Customer, Organization};
use App\Plugins\Billbee\Api\BillbeeClientFactory;
use App\Plugins\Billbee\BillbeePlugin;
use App\Services\Integration\{IntegrationResolver, MatchProfileRegistry};
use App\Services\Integration\Match\MatchProfile;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Billbee-Bestellimport Inbox-First (MVP-433): pollt geänderte Bestellungen
 * (modifiedAtMin-Aufholpunkt aus dem Spiegel, Überlappung gegen Uhrendrift),
 * upsertet den Spiegel `billbee_orders` (Unique je Billbee-Order-ID) und
 * ordnet Käufer über den {@see IntegrationResolver} zu — eindeutige Treffer
 * bzw. bestehende Referenzen verlinken, alles andere wird Inbox-Vorschlag.
 * Kanalherkunft = Billbee-Plattformname (Amazon/eBay/…).
 */
class BillbeeOrderImportService {
    public function __construct(
        private readonly BillbeeClientFactory $clients,
        private readonly IntegrationResolver $resolver,
        private readonly MatchProfileRegistry $profiles,
    ) {}

    /** @return array{imported: int, updated: int, linked: int, staged: int} */
    public function import(Organization $organization): array {
        $client = $this->clients->for((int) $organization->id);
        $profile = $this->profiles->for(Customer::class);
        if (! $profile instanceof MatchProfile) {
            throw new RuntimeException('Kein MatchProfile für Customer registriert.');
        }

        $pageSize = max(1, min(250, (int) config('plugins.billbee.page_size', 100)));
        $counters = ['imported' => 0, 'updated' => 0, 'linked' => 0, 'staged' => 0];
        $since = $this->checkpoint($organization);

        for ($page = 1; $page <= 1000; $page++) {
            $result = $client->orders($since, $page, $pageSize);
            foreach ($result['data'] as $order) {
                if (! is_array($order)) {
                    continue;
                }
                $this->ingest($organization, $profile, $order, $counters);
            }
            if ($page >= $result['total_pages'] || $result['data'] === []) {
                break;
            }
        }

        return $counters;
    }

    /**
     * @param array<string, mixed> $order
     * @param array{imported: int, updated: int, linked: int, staged: int} $counters
     */
    private function ingest(Organization $organization, MatchProfile $profile, array $order, array &$counters): void {
        $billbeeId = (string) ($order['BillBeeOrderId'] ?? '');
        if ($billbeeId === '') {
            return;
        }

        $buyer = is_array($order['Buyer'] ?? null) ? $order['Buyer'] : [];
        $seller = is_array($order['Seller'] ?? null) ? $order['Seller'] : [];
        $buyerExternalId = (string) ($buyer['Id'] ?? '');

        $customer = $buyerExternalId !== ''
            ? $this->resolveCustomer($organization, $profile, $buyerExternalId, $buyer, $counters)
            : null;

        $row = BillbeeOrder::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'billbee_order_id' => $billbeeId],
            [
                'external_order_id' => self::stringOrNull($order['Id'] ?? null),
                'order_number' => self::stringOrNull($order['OrderNumber'] ?? null),
                // Kanalherkunft: Plattform der Verkäufer-/Käuferverbindung,
                // sonst der Billbee-Account-Name.
                'channel' => self::stringOrNull($seller['Platform'] ?? $buyer['Platform'] ?? $order['ApiAccountName'] ?? null),
                'state' => (int) ($order['State'] ?? 0),
                'currency' => self::stringOrNull($order['Currency'] ?? null),
                'total_gross' => number_format((float) ($order['TotalCost'] ?? 0), 2, '.', ''),
                'buyer_external_id' => $buyerExternalId !== '' ? $buyerExternalId : null,
                'buyer' => $buyer !== [] ? $buyer : null,
                'items' => is_array($order['OrderItems'] ?? null) ? $order['OrderItems'] : null,
                'raw' => $order,
                'ordered_at' => self::dateOrNull($order['CreatedAt'] ?? null),
                'billbee_modified_at' => self::dateOrNull($order['LastModifiedAt'] ?? $order['UpdatedAt'] ?? $order['CreatedAt'] ?? null),
                'customer_id' => $customer?->id,
                'inbox_status' => $customer !== null ? BillbeeOrder::INBOX_LINKED : BillbeeOrder::INBOX_OPEN,
            ],
        );

        $row->wasRecentlyCreated ? $counters['imported']++ : $counters['updated']++;
    }

    /**
     * Käuferauflösung Inbox-First: bestehende Referenz/eindeutiger Treffer
     * verlinkt, sonst Inbox-Vorschlag (Kanalherkunft im Untertitel). Nach
     * einer Verlinkung werden offene Spiegelzeilen desselben Käufers
     * nachgezogen.
     *
     * @param array<string, mixed> $buyer
     * @param array{imported: int, updated: int, linked: int, staged: int} $counters
     */
    private function resolveCustomer(Organization $organization, MatchProfile $profile, string $buyerExternalId, array $buyer, array &$counters): ?Customer {
        $name = trim((string) ($buyer['FullName'] ?? ''));
        if ($name === '') {
            $name = trim(trim((string) ($buyer['FirstName'] ?? '')) . ' ' . trim((string) ($buyer['LastName'] ?? '')));
        }

        $outcome = $this->resolver->resolve(
            $organization,
            BillbeePlugin::ID,
            $profile,
            'customer',
            $buyerExternalId,
            array_filter([
                'name' => $name !== '' ? $name : null,
                'email' => self::stringOrNull($buyer['Email'] ?? null),
            ], static fn($value): bool => $value !== null),
            $buyer,
            source: 'billbee',
        );

        if (! $outcome->isResolved()) {
            // Feldabweichung trotz bestehender Referenz (Conflict-Item): die
            // ZUORDNUNG steht fest — nur der Datenabgleich bleibt offen und
            // sichtbar in der Inbox. Ohne Referenz bleibt die Zeile offen.
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
        BillbeeOrder::query()
            ->where('organization_id', $organization->id)
            ->where('buyer_external_id', $buyerExternalId)
            ->whereNull('customer_id')
            ->update(['customer_id' => $customer->id, 'inbox_status' => BillbeeOrder::INBOX_LINKED]);

        return $customer;
    }

    /** Kunde aus bestehender Käufer-Referenz (Zuordnung bereits entschieden). */
    private function customerByReference(Organization $organization, string $buyerExternalId): ?Customer {
        $reference = \App\Models\ExternalReference::query()
            ->forPlugin($organization, BillbeePlugin::ID, 'customer')
            ->forExternalId($buyerExternalId)
            ->first();
        $referenceable = $reference?->referenceable;

        return $referenceable instanceof Customer ? $referenceable : null;
    }

    /** Aufholpunkt: jüngste bekannte Billbee-Änderung minus Überlappung. */
    private function checkpoint(Organization $organization): string {
        $latest = BillbeeOrder::query()
            ->where('organization_id', $organization->id)
            ->max('billbee_modified_at');

        $overlap = max(0, (int) config('plugins.billbee.overlap_minutes', 5));
        if ($latest !== null) {
            return CarbonImmutable::parse((string) $latest)->subMinutes($overlap)->toIso8601String();
        }

        $window = max(1, (int) config('plugins.billbee.initial_window_days', 30));

        return CarbonImmutable::now()->subDays($window)->toIso8601String();
    }

    private static function stringOrNull(mixed $value): ?string {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private static function dateOrNull(mixed $value): ?CarbonImmutable {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? CarbonImmutable::parse($value) : null;
    }
}
