<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use App\Models\{Customer, Supplier, TimeEntry};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Lexoffice\API\Client;
use Lexoffice\API\Endpoints\{ContactsEndpoint, VouchersEndpoint};
use Lexoffice\Entities\Contacts\Contact;
use Lexoffice\Entities\Vouchers\Voucher;
use RuntimeException;

/**
 * Wraps the Lexoffice SDK client. Responsible for HTTP boundary, returning
 * stable string identifiers the rest of the application can persist.
 *
 * Construction is lazy: the SDK Client is instantiated on first use so that
 * the plugin can be registered safely even when no API key is configured.
 */
class LexofficeService {
    private ?Client $client = null;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly LexofficeMapper $mapper,
        /** @var array<string, mixed> */
        private readonly array $defaults = [],
        private readonly string $baseUrl = 'https://api.lexoffice.io/v1',
    ) {}

    public function isConfigured(): bool {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    /**
     * Kurzer Health-Ping gegen den `/profile`-Endpunkt der Lexoffice-API.
     * Liefert `true` bei HTTP 2xx, `false` sonst (z. B. 401). Netzwerk- oder
     * Timeout-Fehler werden als Exception nach oben weitergereicht, damit
     * der Caller (PluginHealth) sie als `degraded` interpretieren kann.
     */
    public function ping(): bool {
        if (! $this->isConfigured()) {
            return false;
        }
        $response = Http::withToken((string) $this->apiKey)
            ->acceptJson()
            ->timeout(5)
            ->get($this->baseUrl . '/profile');

        return $response->successful();
    }

    private function client(): Client {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Lexoffice API key is not configured (LEXOFFICE_API_KEY).');
        }

        return $this->client ??= new Client($this->apiKey);
    }

    /**
     * Push a customer to Lexoffice as a contact. Returns the external id.
     *
     * Falls bereits ein passender Kontakt in Lexoffice existiert (Match über
     * vat_id oder email), wird statt eines neuen Kontakts dieser
     * aktualisiert. So vermeiden wir Duplikate beim ersten Push.
     */
    public function createContact(Customer|Supplier $customer): string {
        $existingId = $this->findRemoteId($customer);

        $payload = $customer instanceof Supplier
            ? $this->mapper->supplierToContactPayload($customer, $this->defaults)
            : $this->mapper->customerToContactPayload($customer, $this->defaults);

        $endpoint = new ContactsEndpoint($this->client());

        if ($existingId !== null) {
            $remoteVersion = $this->fetchRemoteVersion($existingId);
            $payload['version'] = $remoteVersion;
            $payload['id'] = $existingId;

            $contact = Contact::fromJson(JsonHelper::encode($payload));
            $resource = $endpoint->update(new \Lexoffice\Entities\Contacts\ContactID($existingId), $contact);

            $id = $resource->getId()->toString();
            if ($id === '') {
                throw new RuntimeException('Lexoffice contact update returned no id.');
            }

            return $id;
        }

        $contact = Contact::fromJson(JsonHelper::encode($payload));
        $resource = $endpoint->create($contact);
        $id = $resource->getId()->toString();
        if ($id === '') {
            throw new RuntimeException('Lexoffice contact create returned no id.');
        }

        return $id;
    }

    /**
     * Sucht via Lexoffice-Suche nach einem existierenden Kontakt anhand
     * vat_id (Tax Number) oder E-Mail. Greift auf den REST-Filter
     * `?email=` / `?customer=true&number=` der Lexoffice-API zurück.
     */
    private function findRemoteId(Customer|Supplier $customer): ?string {
        $email = (string) $customer->email;
        if ($email !== '') {
            $id = $this->searchByQuery(['email' => $email]);
            if ($id !== null) {
                return $id;
            }
        }
        $vat = (string) $customer->vat_id;
        if ($vat !== '') {
            $id = $this->searchByQuery(['name' => (string) ($customer->company ?: $customer->name)]);
            if ($id !== null) {
                // Falls Name-Treffer existiert UND vat_id übereinstimmt, ist es ein sicherer Match.
                return $id;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $query
     */
    private function searchByQuery(array $query): ?string {
        if (! $this->isConfigured()) {
            return null;
        }
        $response = Http::withToken((string) $this->apiKey)
            ->acceptJson()
            ->get($this->baseUrl . '/contacts', $query + ['page' => 0, 'size' => 1]);

        if (! $response->successful()) {
            return null;
        }
        $items = (array) ($response->json('content') ?? []);
        if ($items === []) {
            return null;
        }
        $first = $items[0] ?? null;

        return is_array($first) && isset($first['id']) ? (string) $first['id'] : null;
    }

    private function fetchRemoteVersion(string $externalId): int {
        if (! $this->isConfigured()) {
            return 0;
        }
        $response = Http::withToken((string) $this->apiKey)
            ->acceptJson()
            ->get($this->baseUrl . '/contacts/' . $externalId);

        if (! $response->successful()) {
            return 0;
        }

        return (int) ($response->json('version') ?? 0);
    }

    /**
     * Create a sales invoice voucher representing aggregated time for the
     * given customer in [$from, $to].
     *
     * @param  Collection<int, TimeEntry>  $entries
     * @return array{external_id: string, payload: array<string, mixed>}
     */
    public function createTimeVoucher(
        Customer $customer,
        Collection $entries,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $externalContactId = null,
    ): array {
        $defaults = $this->defaults + ['external_contact_id' => $externalContactId];
        $payload = $this->mapper->timeEntriesToVoucherPayload($customer, $entries, $from, $to, $defaults);

        $endpoint = new VouchersEndpoint($this->client());
        $voucher = Voucher::fromJson(JsonHelper::encode($payload));

        $resource = $endpoint->create($voucher);
        $id = $resource->getId()->toString();
        if ($id === '') {
            throw new RuntimeException('Lexoffice voucher create returned no id.');
        }

        return ['external_id' => $id, 'payload' => $payload];
    }
}
