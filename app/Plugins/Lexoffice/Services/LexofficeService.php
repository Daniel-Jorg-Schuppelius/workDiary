<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Services;

use APIToolkit\Contracts\Interfaces\NamedEntityInterface;
use APIToolkit\Entities\ID;
use APIToolkit\Exceptions\{ApiException, TooManyRequestsException};
use App\Models\Customer\Customer;
use App\Models\Supplier\Supplier;
use App\Models\Time\TimeEntry;
use App\Plugins\Lexoffice\Api\LexofficeClientFactory;
use App\Plugins\Lexoffice\Exceptions\{LexofficeApiException, LexofficeNotConfiguredException, LexofficeRateLimitException};
use App\Plugins\Lexoffice\LexofficeConfig;
use App\Services\Finance\Accounting\ContactPushService;
use App\Services\Stammdaten\ContactIdentity;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\{JsonHelper, PersonNameHelper};
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Collection;
use Lexoffice\API\Client;
use Lexoffice\API\Endpoints\{ContactsEndpoint, ProfileEndpoint, VouchersEndpoint};
use Lexoffice\Entities\Contacts\Contact;
use Lexoffice\Entities\Files\File as LexofficeFile;
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

    /** @var array<string, mixed>|null */
    private ?array $defaults = null;

    /** @param  int|null  $organizationId  null = gebundene Organisation bzw. Einzelplatz-Konfiguration */
    public function __construct(
        private readonly ?int $organizationId,
        private readonly LexofficeMapper $mapper = new LexofficeMapper,
    ) {}

    public function isConfigured(): bool {
        return app(LexofficeClientFactory::class)->isConfigured($this->organizationId);
    }

    /** @return array<string, mixed> */
    private function defaults(): array {
        return $this->defaults ??= LexofficeConfig::resolve($this->organizationId)['defaults'];
    }

    /**
     * Kurzer Health-Ping gegen den `/profile`-Endpunkt der Lexoffice-API.
     * Liefert `true` bei HTTP 2xx, `false` bei echtem Fehler (z. B. 401).
     *
     * Transiente Zustände werden NICHT als `false` gemeldet, sondern als
     * Exception nach oben gereicht, damit der Caller (PluginHealth) sie als
     * `degraded` einstufen kann und das Plugin nicht auto-deaktiviert wird:
     *   - 429 (Rate-Limit) → {@see LexofficeRateLimitException}
     *   - Netz-/Timeout-Fehler → {@see ConnectException}
     *
     * @throws LexofficeRateLimitException|ConnectException
     */
    public function ping(): bool {
        $status = $this->profileStatus();

        return $status !== null && $status >= 200 && $status < 300;
    }

    /**
     * HTTP-Status des /profile-Endpunkts (für den Healthcheck, damit dieser
     * 401/403/5xx differenziert melden kann). `null`, wenn das Plugin nicht
     * konfiguriert ist. Ein 429 (Rate-Limit) führt — wie bei {@see ping()} —
     * weiterhin zur {@see LexofficeRateLimitException}.
     *
     * @throws LexofficeRateLimitException|ConnectException
     */
    public function profileStatus(): ?int {
        if (! $this->isConfigured()) {
            return null;
        }
        $client = $this->client();
        $timeout = $client->getTimeout();
        $client->setTimeout(5.0);

        try {
            (new ProfileEndpoint($client))->get();
        } catch (TooManyRequestsException) {
            throw new LexofficeRateLimitException();
        } catch (ApiException $e) {
            return $e->getCode();
        } finally {
            $client->setTimeout($timeout);
        }

        return 200;
    }

    /**
     * Ein gedrosselter Client für alle Aufrufe des Dienstes: zwei Clients takten
     * je für sich und überschreiten zusammen die zwei Anfragen je Sekunde —
     * 429 beim POST, den das Toolkit nicht wiederholt (Befund 2026-10-17).
     */
    private function client(): Client {
        return $this->client ??= app(LexofficeClientFactory::class)->for($this->organizationId);
    }

    /**
     * Überträgt einen Kunden bzw. Lieferanten als Lexoffice-Kontakt und
     * liefert ID und Kontaktnummer (MVP-1131/1133). Ein verknüpfter Kontakt
     * wird über seine ID aktualisiert; ohne Verknüpfung nur ein Suchtreffer,
     * der derselbe Kontakt ist ({@see ContactIdentity}), sonst entsteht ein
     * neuer. Die Nummer vergibt immer Lexoffice; die Antwort auf das Anlegen
     * trägt nur die ID, daher der Abruf danach.
     *
     * @return array{id: string, number: ?string}
     */
    public function pushContact(Customer|Supplier $party, ?string $linkedId = null): array {
        // Ohne Schlüssel blieben Suche und Abruf still leer und ein Duplikat entstünde nicht erkennbar.
        if (! $this->isConfigured()) {
            throw new LexofficeNotConfiguredException();
        }
        $linked = $linkedId !== null ? $this->fetchContact($linkedId) : null;
        $existing = $linked ?? $this->findSameContact($party);
        // Führt die Buchhaltung die Stammdaten, wird ein Treffer nur verknüpft, nicht überschrieben.
        if ($linked === null && $existing !== null && ! app(ContactPushService::class)->pushAllowed()) {
            return ['id' => (string) $existing['id'], 'number' => $this->contactNumber($existing, $party)];
        }

        $payload = $party instanceof Supplier
            ? $this->mapper->supplierToContactPayload($party, $this->defaults())
            : $this->mapper->customerToContactPayload($party, $this->defaults());

        $contacts = new ContactsEndpoint($this->client());
        if ($existing !== null) {
            $existingId = (string) $existing['id'];
            $payload['version'] = (int) ($existing['version'] ?? 0);
            $payload['id'] = $existingId;

            try {
                $resource = $contacts->update(new ID($existingId), Contact::fromJson(JsonHelper::encode($payload)));
            } catch (ApiException $e) {
                throw LexofficeApiException::fromApiException($e, __('Kontakte'), __('Kontakte bearbeiten'));
            }
            $id = (string) ($resource->toArray()['id'] ?? '');
            if ($id === '') {
                throw new RuntimeException('Lexoffice contact update returned no id.');
            }

            return ['id' => $id, 'number' => $this->contactNumber($existing, $party)];
        }

        try {
            $resource = $contacts->create(Contact::fromJson(JsonHelper::encode($payload)));
        } catch (ApiException $e) {
            throw LexofficeApiException::fromApiException($e, __('Kontakte'), __('Kontakte anlegen'));
        }
        $id = (string) ($resource->toArray()['id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('Lexoffice contact create returned no id.');
        }

        return ['id' => $id, 'number' => $this->contactNumber($this->fetchContact($id) ?? [], $party)];
    }

    /**
     * Kontakte nach E-Mail und Name (Phase 152, MVP-1226) — ungeprüft, die
     * Identität prüft der Kern.
     *
     * @return list<array<string, mixed>>
     */
    public function findContacts(?string $email, ?string $name): array {
        $rows = [];
        foreach ([['email' => $email], ['name' => $name]] as $query) {
            $value = trim((string) reset($query));
            if ($value === '') {
                continue;
            }
            foreach ($this->searchContacts([array_key_first($query) => $value]) as $candidate) {
                $rows[(string) ($candidate['id'] ?? count($rows))] = $candidate;
            }
        }

        return array_values($rows);
    }

    /**
     * Sucht einen vorhandenen Lexoffice-Kontakt über E-Mail bzw. — mit
     * USt-IdNr. — über den Namen. Ein Treffer zählt nur, wenn er derselbe
     * Kontakt ist; die Namenssuche trifft sonst auch fremde Firmen.
     *
     * @return array<string, mixed>|null
     */
    private function findSameContact(Customer|Supplier $party): ?array {
        $queries = [];
        if ((string) $party->email !== '') {
            $queries[] = ['email' => (string) $party->email];
        }
        if ((string) $party->vat_id !== '') {
            $queries[] = ['name' => $party->displayLabel()];
        }

        foreach ($queries as $query) {
            foreach ($this->searchContacts($query) as $candidate) {
                if (isset($candidate['id']) && app(ContactIdentity::class)->same($party, self::identityFields($candidate))) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $query
     * @return list<array<string, mixed>>
     */
    private function searchContacts(array $query): array {
        if (! $this->isConfigured()) {
            return [];
        }
        try {
            $page = (new ContactsEndpoint($this->client()))->search($query + ['page' => 0, 'size' => 25]);
        } catch (ApiException) {
            return [];
        }

        return array_values(array_map(static fn (NamedEntityInterface $contact): array => $contact->toArray(), $page->getValues()));
    }

    /** @return array<string, mixed>|null */
    private function fetchContact(string $externalId): ?array {
        if (! $this->isConfigured()) {
            return null;
        }
        try {
            $body = (new ContactsEndpoint($this->client()))->get(new ID($externalId))->toArray();
        } catch (ApiException) {
            return null;
        }

        return isset($body['id']) ? $body : null;
    }

    /** @param array<string, mixed> $contact */
    private function contactNumber(array $contact, Customer|Supplier $party): ?string {
        $number = data_get($contact, $party instanceof Supplier ? 'roles.vendor.number' : 'roles.customer.number');

        return $number === null || $number === '' ? null : (string) $number;
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array{name: string, company: string, vat_id: string}
     */
    private static function identityFields(array $contact): array {
        $company = (string) data_get($contact, 'company.name', '');
        $person = PersonNameHelper::join(['first_name' => data_get($contact, 'person.firstName'), 'last_name' => data_get($contact, 'person.lastName')]);

        return [
            'name' => $company !== '' ? $company : $person,
            'company' => $company,
            'vat_id' => (string) data_get($contact, 'company.vatRegistrationId', ''),
        ];
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
        $defaults = $this->defaults() + ['external_contact_id' => $externalContactId];
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

    /**
     * Auslage als Einkaufsbeleg anlegen (Feature 106). Die Belegdateien hängt
     * danach {@see attachVoucherFiles()} an: `POST /files` mit `type=voucher`
     * legte in Lexware einen eigenen zweiten Beleg an (MVP-1112).
     *
     * Der Push ist **terminal**: `VouchersEndpoint::delete()` wirft
     * NotAllowedException; Korrekturen laufen als Gegenbeleg im führenden
     * System, nie als Update von hier.
     *
     * @return array{external_id: string, payload: array<string, mixed>}
     */
    public function createExpenseVoucher(\App\Models\Travel\Expense $expense, string $categoryId): array {
        $payload = $this->mapper->expenseToVoucherPayload($expense, $categoryId);

        $resource = (new VouchersEndpoint($this->client()))->create(Voucher::fromJson(JsonHelper::encode($payload)));
        $id = $resource->getId()->toString();
        if ($id === '') {
            throw new RuntimeException('Lexoffice voucher create returned no id.');
        }

        return ['external_id' => $id, 'payload' => $payload];
    }

    /** @param  list<string>  $filePaths  lokale Pfade der Belegdateien */
    public function attachVoucherFiles(string $voucherId, array $filePaths): void {
        $endpoint = new VouchersEndpoint($this->client());
        foreach ($filePaths as $filePath) {
            $endpoint->addFile(new \APIToolkit\Entities\ID($voucherId), new LexofficeFile(['filePath' => $filePath]));
        }
    }

    /**
     * Gegenbeleg zu einer gepushten Auslage (MVP-802): Einkaufsgutschrift über
     * denselben Betrag mit Verweis auf den ursprünglichen Beleg.
     *
     * @return array{external_id: string, payload: array<string, mixed>}
     */
    public function createExpenseCounterVoucher(\App\Models\Travel\Expense $expense, string $categoryId, string $originalReference, string $reason): array {
        $payload = $this->mapper->expenseToCounterVoucherPayload($expense, $categoryId, $originalReference, $reason);

        $resource = (new VouchersEndpoint($this->client()))->create(Voucher::fromJson(JsonHelper::encode($payload)));
        $id = $resource->getId()->toString();
        if ($id === '') {
            throw new RuntimeException('Lexoffice voucher create returned no id.');
        }

        return ['external_id' => $id, 'payload' => $payload];
    }
}
