<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax;

use APIToolkit\Entities\ID;
use App\Enums\Stammdaten\ContactSalutation;
use App\Exceptions\ContactNumberTakenException;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{ContactSyncer, Plugin, PluginCapability};
use App\Plugins\OrgaMax\Api\OrgaMaxClientFactory;
use App\Plugins\OrgaMax\Enums\OrgaMaxConnectionStatus;
use App\Plugins\OrgaMax\Models\OrgaMaxConnection;
use App\Plugins\Support\ApiHealthProbe;
use App\Services\Finance\Accounting\ContactPushService;
use App\Services\Numbering\NumberSequenceService;
use App\Services\Stammdaten\{ContactIdentity, ContactNumberAdopter, ContactPersons};
use CommonToolkit\Helper\Data\PersonNameHelper;
use Orgamax\API\Endpoints\CustomersEndpoint;
use Orgamax\API\Endpoints\Settings\AccountSettingEndpoint;
use Orgamax\Entities\Customers\{ContactPerson as OrgaMaxContactPerson, ContactPersons as OrgaMaxContactPersons, Customer as OrgaMaxCustomer, CustomerDefaultAddress};
use RuntimeException;

/**
 * orgaMAX-Buchhaltung-Plugin (Feature 077, MVP-305–315).
 *
 * Bindet ausschließlich orgaMAX **Buchhaltung** über die offizielle OpenAPI
 * an (https://api.orgamax.de/openapi, OAS 3.0.2) — die nicht unterstützte
 * orgaMAX-ERP-Variante wird sichtbar zurückgewiesen; keine Screen-Scraping-,
 * Datenbank- oder undokumentierten Anbindungen. Stammdaten laufen über
 * ExternalReference + Integrations-Inbox (keine Schattenstammdaten), die
 * Faktura-Übergabe über den {@see \App\Plugins\OrgaMax\Services\OrgaMaxTarget}
 * ({@see \App\Enums\Finance\TransferTarget::OrgaMax}). Wie JTL-Wawi ohne
 * eigene {@see \App\Plugins\Contracts\PluginCapability} — die Fähigkeiten
 * hängen an FacturationTarget-/Outbox-Verträgen.
 */
class OrgaMaxPlugin extends AbstractPlugin implements ContactSyncer {
    public const ID = 'orgamax';

    public const SERVICE_PROVIDER = OrgaMaxServiceProvider::class;

    public function name(): string {
        return 'orgaMAX Buchhaltung';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return __('Bindet orgaMAX Buchhaltung über die offizielle OpenAPI an: Kunden-/Lieferanten-/Artikelprojektion, Faktura-Übergabe als orgaMAX-Auftrag sowie Rechnungs-, Zahlungs- und PDF-Projektion. Nicht für orgaMAX ERP.');
    }

    /**
     * Angekündigt wird, was die Klasse selbst implementiert (Kontakt-Push).
     * Die Belegübergabe läuft über die
     * {@see \App\Services\Finance\Targets\FacturationTargetRegistry}
     * (Audit 2026-08, W1.6).
     *
     * @return array<int, PluginCapability>
     */
    public function capabilities(): array {
        return [PluginCapability::ContactSync];
    }

    /** @return array{route: string, label: string, icon: string}|null */
    public function adminPanel(): ?array {
        return [
            'route' => 'admin.orgamax.index',
            'label' => __('orgaMAX Buchhaltung'),
            'icon' => 'calculator',
        ];
    }

    /** @return array<int, array<string, mixed>> Eigenes Admin-Panel statt Auto-Form. */
    /**
     * Kontakt-Push (Feature 122, MVP-611): anlegen oder aktualisieren, nie
     * löschen. Ein verknüpfter Kunde wird über seine ID aktualisiert; ein
     * Treffer über die Kundennummer nur, wenn er derselbe Kunde ist
     * (MVP-1133). orgaMAX vergibt keine Kundennummern für die App: die Doku
     * sagt nicht zu, dass ohne Nummer eine entsteht. Eine Entwurfsnummer
     * eines anderen Programms geht nicht hinüber.
     */
    /**
     * `customerDefaultAddress` ersetzt die gespeicherte Anschrift ganz: Beide
     * Anschriften gehen mit ihrer `id` zurück, nur der Name der Rechnungsanschrift
     * ändert sich. Null, wenn orgaMAX keine Anschrift führt oder der Name stimmt.
     */
    private function renamedDefaultAddress(CustomersEndpoint $endpoint, string $id, string $name): ?CustomerDefaultAddress {
        $address = $endpoint->get(new ID($id))?->getCustomerDefaultAddress();
        $billing = $address?->getBillingAddress();
        if ($address === null || $billing === null || trim((string) $billing->getId()) === '' || trim($name) === '') {
            return null;
        }
        $person = ! filled($billing->getCompanyName()) && (filled($billing->getFirstName()) || filled($billing->getLastName()));
        $current = $person ? PersonNameHelper::join(['first_name' => $billing->getFirstName(), 'last_name' => $billing->getLastName()]) : trim((string) $billing->getCompanyName());
        if ($current === trim($name)) {
            return null;
        }
        if ($person) {
            $parts = PersonNameHelper::split($name);
            $billing->setFirstName(PersonNameHelper::join(['first_name' => $parts['first_name'], 'middle_names' => $parts['middle_names']]) ?: null);
            $billing->setLastName($parts['last_name']);
        } else {
            $billing->setCompanyName($name);
        }

        return $address;
    }

    public function pushContact(Customer $customer): string {
        \App\Services\Stammdaten\CollectiveContacts::assertPushable($customer);
        $connection = OrgaMaxConnection::query()
            ->where('organization_id', $customer->organization_id)
            ->first();
        if (! $connection instanceof OrgaMaxConnection) {
            throw new RuntimeException('orgaMAX connection is not configured.');
        }

        $endpoint = new CustomersEndpoint(app(OrgaMaxClientFactory::class)->for($connection));
        $number = NumberSequenceService::isDraft($customer->number) ? '' : trim((string) $customer->number);

        // Der Stammdatenimport verknüpft unter `customer`, der Push unter `contact`.
        $existingId = (string) ExternalReference::query()
            ->forPlugin($customer->organization_id, self::ID)
            ->whereIn('external_type', [ExternalReference::TYPE_CONTACT, 'customer'])
            ->forReferenceable($customer)
            ->value('external_id');
        if ($existingId === '' && $number !== '') {
            foreach (($endpoint->search(['number' => $number, 'limit' => 10])?->getValues() ?? []) as $row) {
                if ((string) $row->getNumber() === $number && (string) $row->getId() !== '') {
                    $name = (string) ($row->getCompanyName() ?: $row->getName() ?: PersonNameHelper::join(['first_name' => $row->getFirstName(), 'last_name' => $row->getLastName()]));
                    if (! app(ContactIdentity::class)->same($customer, ['name' => $name, 'vat_id' => $row->getVatNumber()])) {
                        throw ContactNumberTakenException::remote($number, $this->name(), $name);
                    }
                    $existingId = (string) $row->getId();
                    // Führt die Buchhaltung die Stammdaten, wird ein Treffer nur verknüpft, nicht überschrieben.
                    if (! app(ContactPushService::class)->pushAllowed()) {
                        app(ContactNumberAdopter::class)->linked($customer, self::ID, $existingId, $number);

                        return $existingId;
                    }

                    break;
                }
            }
        }

        // Eigene USt-IdNr./E-Mail nie an einen Fremdkontakt (MVP-611).
        $fields = app(ContactPushService::class)->withoutOwnIdentity([
            'vat_id' => (string) ($customer->vat_id ?? ''),
            'email' => (string) ($customer->email ?? ''),
        ]);

        $payload = new OrgaMaxCustomer;
        $payload->setName((string) $customer->name);
        if ($number !== '') {
            $payload->setNumber($number);
        }
        if (($fields['email'] ?? '') !== '') {
            $payload->setEmail((string) $fields['email']);
        }
        if (($fields['vat_id'] ?? '') !== '') {
            $payload->setVatNumber((string) $fields['vat_id']);
        }

        if ($existingId !== '') {
            // Den Namen liest orgaMAX nur aus der Rechnungsanschrift (Befund B8); ohne gespeicherte Anschrift bleibt sie unberührt.
            $address = $this->renamedDefaultAddress($endpoint, $existingId, (string) $customer->name);
            if ($address !== null) {
                $payload->setCustomerDefaultAddress($address);
            }
            $endpoint->update(new ID($existingId), $payload);
            app(ContactNumberAdopter::class)->linked($customer, self::ID, $existingId, $number);

            return $existingId;
        }

        // Hauptansprechpartner nur beim Anlegen (MVP-1138): ohne orgaMAX-ID
        // legte ein Aktualisieren ihn bei jedem Übertragen erneut an.
        $contact = ContactPersons::primaryOf($customer);
        if ($contact !== null && ($contact['last_name'] ?? null) !== null) {
            $person = new OrgaMaxContactPerson;
            $person->setSalutation(match ($contact['salutation']) {
                ContactSalutation::Mr->value => 'Herr',
                ContactSalutation::Ms->value => 'Frau',
                default => null,
            });
            $person->setTitle($contact['title']);
            $person->setFirstName(PersonNameHelper::join(['first_name' => $contact['first_name'], 'middle_names' => $contact['middle_names']]) ?: null);
            $person->setLastName($contact['last_name']);
            $person->setEmail($contact['email']);
            $person->setPhone1($contact['phone']);
            $person->setIsMainContact(true);
            $payload->setContactPersons(new OrgaMaxContactPersons([$person]));
        }

        $created = $endpoint->create($payload)->getData();
        $createdId = (string) ($created?->getId() ?? '');
        if ($createdId === '') {
            throw new RuntimeException('orgaMAX customer push returned no id.');
        }
        app(ContactNumberAdopter::class)->linked($customer, self::ID, $createdId, (string) ($created?->getNumber() ?? $number));

        return $createdId;
    }

    public function settingsSchema(): array {
        return [];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }

        $connection = OrgaMaxConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof OrgaMaxConnection) {
            return PluginHealth::degraded(__('Keine orgaMAX-Verbindung hinterlegt.'), 'not_configured');
        }
        if ($connection->status === OrgaMaxConnectionStatus::Blocked) {
            return PluginHealth::failing(__('Verbindung blockiert (:reason) — Details im orgaMAX-Admin.', ['reason' => (string) $connection->blocked_reason]), 'blocked');
        }
        if (in_array($connection->status, [OrgaMaxConnectionStatus::PendingCallback, OrgaMaxConnectionStatus::PendingConfirmation], true)) {
            return PluginHealth::degraded(__('Verbindung wartet auf Callback bzw. Kontobestätigung.'), 'pending');
        }
        if (! $connection->isActive()) {
            return PluginHealth::degraded(__('Verbindung unvollständig oder getrennt.'), 'inactive');
        }

        return ApiHealthProbe::probe(
            $this->name(),
            static fn () => (new AccountSettingEndpoint(app(OrgaMaxClientFactory::class)->for($connection)))->get(),
            ['auth' => (string) __('Token abgelaufen oder Scopes entzogen — Verbindung erneuern.')],
        );
    }
}
