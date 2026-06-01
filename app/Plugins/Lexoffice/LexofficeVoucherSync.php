<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucherSync.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use App\Models\{Customer, ExternalReference, LexofficeVoucher, Organization, Supplier};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Zieht die Lexoffice-Belege (voucherlist) für alle verknüpften Kontakte einer
 * Organisation und legt sie im lokalen Cache `lexoffice_vouchers` ab.
 *
 * Pro verknüpftem Kontakt (ExternalReference type='contact') wird die
 * voucherlist mit `voucherType=any` und `voucherStatus=any` paginiert geladen.
 * Die Zuordnung zu lokalen Kunden/Lieferanten erfolgt über die external_id des
 * Kontakts — ein Kontakt mit Doppelrolle setzt sowohl customer_id als auch
 * supplier_id.
 *
 * Verwendet den HTTP-Client direkt (analog Article-/Contact-Sync).
 *
 * Quelle: https://developers.lexoffice.io/docs/#voucherlist-endpoint
 */
class LexofficeVoucherSync {
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl = 'https://api.lexoffice.io/v1',
    ) {}

    /**
     * @return array{contacts: int, created: int, updated: int, archived: int}
     */
    public function sync(Organization $organization): array {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new RuntimeException('Lexoffice API key is not configured (LEXOFFICE_API_KEY).');
        }

        $contactMap = $this->buildContactMap($organization);

        $created = 0;
        $updated = 0;
        /** @var array<int, string> $seen */
        $seen = [];

        foreach ($contactMap as $contactExternalId => $owners) {
            foreach ($this->fetchVouchers($contactExternalId) as $item) {
                if (empty($item['id'])) {
                    continue;
                }
                $externalId = (string) $item['id'];
                $seen[] = $externalId;

                $attrs = $this->itemToAttrs($item) + [
                    'contact_external_id' => $contactExternalId,
                    'customer_id' => $owners['customer_id'],
                    'supplier_id' => $owners['supplier_id'],
                    'archived' => (bool) ($item['archived'] ?? false),
                    'payload' => $item,
                    'synced_at' => now(),
                ];

                $existing = LexofficeVoucher::query()
                    ->where('organization_id', $organization->id)
                    ->where('external_id', $externalId)
                    ->first();

                if ($existing === null) {
                    LexofficeVoucher::create($attrs + [
                        'organization_id' => $organization->id,
                        'external_id' => $externalId,
                    ]);
                    $created++;

                    continue;
                }

                $existing->fill($attrs)->save();
                $updated++;
            }
        }

        // In Lexoffice nicht mehr sichtbare Belege als archiviert markieren.
        $archived = LexofficeVoucher::query()
            ->where('organization_id', $organization->id)
            ->where('archived', false)
            ->when($seen !== [], fn($q) => $q->whereNotIn('external_id', $seen))
            ->update(['archived' => true]);

        return [
            'contacts' => count($contactMap),
            'created' => $created,
            'updated' => $updated,
            'archived' => (int) $archived,
        ];
    }

    /**
     * Baut die Zuordnung Kontakt-external_id → lokale Kunden-/Lieferanten-ID.
     *
     * @return array<string, array{customer_id: ?int, supplier_id: ?int}>
     */
    private function buildContactMap(Organization $organization): array {
        $refs = ExternalReference::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)
            ->get(['external_id', 'referenceable_type', 'referenceable_id']);

        $customerMorph = (new Customer)->getMorphClass();
        $supplierMorph = (new Supplier)->getMorphClass();

        /** @var array<string, array{customer_id: ?int, supplier_id: ?int}> $map */
        $map = [];
        foreach ($refs as $ref) {
            $externalId = (string) $ref->external_id;
            if (! isset($map[$externalId])) {
                $map[$externalId] = ['customer_id' => null, 'supplier_id' => null];
            }
            if ($ref->referenceable_type === $customerMorph) {
                $map[$externalId]['customer_id'] = (int) $ref->referenceable_id;
            } elseif ($ref->referenceable_type === $supplierMorph) {
                $map[$externalId]['supplier_id'] = (int) $ref->referenceable_id;
            }
        }

        return $map;
    }

    /**
     * Lädt alle Belege eines Kontakts (paginiert).
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchVouchers(string $contactExternalId): array {
        $page = 0;
        $pageSize = 250;
        /** @var array<int, array<string, mixed>> $all */
        $all = [];

        do {
            $response = $this->requestVoucherlist($contactExternalId, $page, $pageSize);

            /** @var array<string, mixed> $body */
            $body = $response->json() ?? [];
            foreach ((array) ($body['content'] ?? []) as $item) {
                if (is_array($item)) {
                    /** @var array<string, mixed> $item */
                    $all[] = $item;
                }
            }

            $totalPages = (int) ($body['totalPages'] ?? 1);
            $page++;
        } while ($page < $totalPages);

        return $all;
    }

    /**
     * Führt eine voucherlist-Anfrage aus und behandelt das Lexoffice-Ratelimit
     * (HTTP 429) mit Backoff. Drosselt zusätzlich auf < 2 Requests/Sekunde.
     */
    private function requestVoucherlist(string $contactExternalId, int $page, int $pageSize): \Illuminate\Http\Client\Response {
        $attempts = 0;
        do {
            // Sanftes Throttling, um das Ratelimit (2 req/s) nicht zu reißen.
            usleep(600_000);

            $response = Http::withToken((string) $this->apiKey)
                ->acceptJson()
                ->get($this->baseUrl . '/voucherlist', [
                    'voucherType' => 'any',
                    'voucherStatus' => 'any',
                    'contactId' => $contactExternalId,
                    'page' => $page,
                    'size' => $pageSize,
                ]);

            if ($response->status() === 429 && $attempts < 5) {
                $retryAfter = (int) ($response->header('Retry-After') ?: 0);
                usleep(max($retryAfter, 1) * 1_000_000);
                $attempts++;

                continue;
            }

            if (! $response->successful()) {
                throw new RuntimeException('Lexoffice voucherlist request failed: ' . $response->status() . ' ' . $response->body());
            }

            return $response;
        } while ($attempts <= 5);

        throw new RuntimeException('Lexoffice voucherlist request failed after retries (rate limit).');
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function itemToAttrs(array $item): array {
        return [
            'voucher_type' => $this->str($item, 'voucherType'),
            'voucher_status' => $this->str($item, 'voucherStatus'),
            'voucher_number' => $this->str($item, 'voucherNumber'),
            'voucher_date' => $this->date($item, 'voucherDate'),
            'due_date' => $this->date($item, 'dueDate'),
            'total_amount' => isset($item['totalAmount']) ? (float) $item['totalAmount'] : null,
            'open_amount' => isset($item['openAmount']) ? (float) $item['openAmount'] : null,
            'currency' => $this->str($item, 'currency') ?: 'EUR',
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function str(array $item, string $key): ?string {
        $value = $item[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function date(array $item, string $key): ?string {
        $value = $item[$key] ?? null;
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
