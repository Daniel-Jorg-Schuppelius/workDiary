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

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Models\{Customer, ExternalReference, LexofficeVoucher, Organization, Supplier};
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Illuminate\Support\Carbon;
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
    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl = 'https://api.lexoffice.io/v1',
    ) {}

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client('lexoffice', $this->baseUrl);
            $this->api->setAuthentication(new BearerAuthentication((string) $this->apiKey));
        }

        return $this->api;
    }

    /**
     * @return array{contacts: int, created: int, updated: int, archived: int, paid_dates: int}
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
                if (! empty($item['id'])) {
                    $seen[] = (string) $item['id'];
                }
                $verb = $this->upsertVoucherItem($organization->id, $contactExternalId, $owners, $item);
                if ($verb === 'created') {
                    $created++;
                } elseif ($verb === 'updated') {
                    $updated++;
                }
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
            'paid_dates' => $this->enrichPaidDates($organization->id),
        ];
    }

    /**
     * Zahlungsdaten nachladen (Phase-54-Nachtrag): Die voucherlist liefert
     * KEIN paidDate — für bezahlte Belege ohne Zahlungsdatum wird es über
     * den Payments-Endpunkt geholt. Damit kann der Zahlungsverhaltens-
     * Report Zahldauer/DSO auch bei externer Rechnungshoheit rechnen.
     * $limit deckelt die Zusatz-Requests je Lauf (Ratelimit 2 req/s);
     * Rest folgt beim nächsten Sync. Fehler je Beleg (z. B. 404 für
     * Belegarten ohne Zahlung) werden toleriert.
     */
    public function enrichPaidDates(int $organizationId, int $limit = 100): int {
        $candidates = LexofficeVoucher::query()
            ->where('organization_id', $organizationId)
            ->where('voucher_status', 'paid')
            ->whereNull('paid_date')
            ->whereIn('voucher_type', ['invoice', 'downpaymentinvoice', 'creditnote'])
            ->orderByDesc('voucher_date')
            ->limit($limit)
            ->get(['id', 'external_id']);

        $enriched = 0;
        foreach ($candidates as $voucher) {
            usleep(600_000); // sanftes Throttling wie beim voucherlist-Abruf

            $response = $this->api()->getResponse($this->baseUrl . '/payments/' . $voucher->external_id);
            if (! $response->successful()) {
                continue;
            }

            $paidDate = $response->json('paidDate');
            if (! is_string($paidDate) || $paidDate === '') {
                continue;
            }

            $voucher->forceFill(['paid_date' => substr($paidDate, 0, 10)])->save();
            $enriched++;
        }

        return $enriched;
    }

    /**
     * Synchronisiert die Lexoffice-Belege EINES Kontakts (Kunde oder Lieferant)
     * on-demand — z. B. ausgelöst über den „Synchronisieren"-Button auf der
     * Detailseite. Archiviert nur die nicht mehr sichtbaren Belege DIESES
     * Kontakts (kontaktscoped, nicht org-weit).
     *
     * @return array{contacts: int, created: int, updated: int, archived: int, paid_dates: int}
     */
    public function syncFor(Customer|Supplier $owner): array {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new RuntimeException('Lexoffice API key is not configured (LEXOFFICE_API_KEY).');
        }

        $organizationId = (int) $owner->organization_id;

        $ref = ExternalReference::query()
            ->forPlugin($organizationId, LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_CONTACT)
            ->forReferenceable($owner)
            ->first(['external_id']);

        if ($ref === null) {
            return ['contacts' => 0, 'created' => 0, 'updated' => 0, 'archived' => 0, 'paid_dates' => 0];
        }

        $contactExternalId = (string) $ref->external_id;
        $owners = $this->ownersForContact($organizationId, $contactExternalId);

        $created = 0;
        $updated = 0;
        /** @var array<int, string> $seen */
        $seen = [];

        foreach ($this->fetchVouchers($contactExternalId) as $item) {
            if (! empty($item['id'])) {
                $seen[] = (string) $item['id'];
            }
            $verb = $this->upsertVoucherItem($organizationId, $contactExternalId, $owners, $item);
            if ($verb === 'created') {
                $created++;
            } elseif ($verb === 'updated') {
                $updated++;
            }
        }

        $archived = LexofficeVoucher::query()
            ->where('organization_id', $organizationId)
            ->where('contact_external_id', $contactExternalId)
            ->where('archived', false)
            ->when($seen !== [], fn (\Illuminate\Database\Eloquent\Builder $q) => $q->whereNotIn('external_id', $seen))
            ->update(['archived' => true]);

        return [
            'contacts' => 1,
            'created' => $created,
            'updated' => $updated,
            'archived' => (int) $archived,
            'paid_dates' => $this->enrichPaidDates($organizationId),
        ];
    }

    /**
     * Legt einen einzelnen voucherlist-Eintrag an oder aktualisiert ihn.
     *
     * @param  array{customer_id: ?int, supplier_id: ?int}  $owners
     * @param  array<string, mixed>  $item
     * @return 'created'|'updated'|null  null, wenn der Eintrag keine id hat.
     */
    private function upsertVoucherItem(int $organizationId, string $contactExternalId, array $owners, array $item): ?string {
        if (empty($item['id'])) {
            return null;
        }
        $externalId = (string) $item['id'];

        $attrs = $this->itemToAttrs($item) + [
            'contact_external_id' => $contactExternalId,
            'customer_id' => $owners['customer_id'],
            'supplier_id' => $owners['supplier_id'],
            'archived' => (bool) ($item['archived'] ?? false),
            'payload' => $item,
            'synced_at' => now(),
        ];

        $existing = LexofficeVoucher::query()
            ->where('organization_id', $organizationId)
            ->where('external_id', $externalId)
            ->first();

        if ($existing === null) {
            LexofficeVoucher::create($attrs + [
                'organization_id' => $organizationId,
                'external_id' => $externalId,
            ]);

            return 'created';
        }

        $existing->fill($attrs)->save();

        return 'updated';
    }

    /**
     * Ermittelt die lokalen Eigentümer (Kunde/Lieferant) eines Kontakts; ein
     * Kontakt mit Doppelrolle setzt beide.
     *
     * @return array{customer_id: ?int, supplier_id: ?int}
     */
    private function ownersForContact(int $organizationId, string $contactExternalId): array {
        $refs = ExternalReference::query()
            ->forPlugin($organizationId, LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_CONTACT)
            ->forExternalId($contactExternalId)
            ->get(['referenceable_type', 'referenceable_id']);

        $owners = ['customer_id' => null, 'supplier_id' => null];
        $customerMorph = (new Customer)->getMorphClass();
        $supplierMorph = (new Supplier)->getMorphClass();

        foreach ($refs as $ref) {
            if ($ref->referenceable_type === $customerMorph) {
                $owners['customer_id'] = (int) $ref->referenceable_id;
            } elseif ($ref->referenceable_type === $supplierMorph) {
                $owners['supplier_id'] = (int) $ref->referenceable_id;
            }
        }

        return $owners;
    }

    /**
     * Baut die Zuordnung Kontakt-external_id → lokale Kunden-/Lieferanten-ID.
     *
     * @return array<string, array{customer_id: ?int, supplier_id: ?int}>
     */
    private function buildContactMap(Organization $organization): array {
        $refs = ExternalReference::query()
            ->forPlugin($organization, LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_CONTACT)
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

            $response = $this->api()
                ->getResponse($this->baseUrl . '/voucherlist', [
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
                throw LexofficeApiException::fromResponse($response, __('Belege'), __('Belegliste filtern und abrufen'));
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
