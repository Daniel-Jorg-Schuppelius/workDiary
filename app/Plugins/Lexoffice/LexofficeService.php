<?php

namespace App\Plugins\Lexoffice;

use App\Models\Customer;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Lexoffice\API\Client;
use Lexoffice\API\Endpoints\ContactsEndpoint;
use Lexoffice\API\Endpoints\VouchersEndpoint;
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
    ) {
    }

    public function isConfigured(): bool {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    private function client(): Client {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Lexoffice API key is not configured (LEXOFFICE_API_KEY).');
        }

        return $this->client ??= new Client($this->apiKey);
    }

    /**
     * Push a customer to Lexoffice as a contact. Returns the external id.
     */
    public function createContact(Customer $customer): string {
        $payload = $this->mapper->customerToContactPayload($customer, $this->defaults);

        $endpoint = new ContactsEndpoint($this->client());
        $contact = Contact::fromJson(json_encode($payload, JSON_THROW_ON_ERROR));

        $resource = $endpoint->create($contact);
        $id = $resource->getId()->toString();
        if ($id === '') {
            throw new RuntimeException('Lexoffice contact create returned no id.');
        }

        return $id;
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
        $voucher = Voucher::fromJson(json_encode($payload, JSON_THROW_ON_ERROR));

        $resource = $endpoint->create($voucher);
        $id = $resource->getId()->toString();
        if ($id === '') {
            throw new RuntimeException('Lexoffice voucher create returned no id.');
        }

        return ['external_id' => $id, 'payload' => $payload];
    }
}
