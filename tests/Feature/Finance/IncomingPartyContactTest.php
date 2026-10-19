<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingPartyContactTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Billing\DocumentDirection;
use App\Enums\Document\DocumentType;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceRecognition};
use App\Enums\Stammdaten\ContactSalutation;
use App\Models\Customer\Customer;
use App\Models\Document\Document;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\{Organization, PluginSetting, User};
use App\Models\Supplier\Supplier;
use App\Modules\ModuleRegistry;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Easybill\Services\EasybillIncomingPartyLookup;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Services\LexofficeIncomingPartyLookup;
use App\Plugins\OrgaMax\Enums\OrgaMaxConnectionStatus;
use App\Plugins\OrgaMax\Models\OrgaMaxConnection;
use App\Plugins\OrgaMax\Services\OrgaMaxIncomingPartyLookup;
use App\Plugins\SevDesk\Services\SevDeskIncomingPartyLookup;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Invoicing\Contracts\IncomingPartyLookup;
use App\Services\Invoicing\Dto\IncomingPartyCandidate;
use App\Services\Invoicing\EInvoice\IncomingInvoiceParties;
use App\Services\Stammdaten\ContactPersons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Feature\Plugins\OrgaMax\OrgaMaxApiResponses;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** Gefälschte Kontaktsuche mit Ansprechpartnern. */
final class PartyContactFakeLookup implements IncomingPartyLookup {
    public function key(): string {
        return 'fakecontact';
    }

    public function label(): string {
        return 'Fakebuch';
    }

    public function isEnabled(Organization $organization): bool {
        return true;
    }

    public function lookup(IncomingEInvoice $incoming): array {
        return [new IncomingPartyCandidate('fakecontact', 'Fakebuch', 'c-9', 'Stimmgerecht oHG', 'K-10007', 'DE286103889', 'info@stimmgerecht.test',
            persons: [
                ContactPersons::fromExternal('Herr', 'Ralph', 'Hartig', email: 'r@stimmgerecht.test') ?? [],
                ContactPersons::fromExternal('Frau', 'Birgit', 'Hartig', primary: true) ?? [],
            ])];
    }
}

/**
 * Kandidaten aus dem Buchhaltungsprogramm tragen die Ansprechpartner: Lexoffice
 * (contactPersons, Privatperson), easybill (Namensfelder des Kunden), sevDesk
 * (Personen mit parent, Privatperson), orgaMAX (contactPersons, isMainContact);
 * die Übernahme legt die Partei mit Hauptansprechpartner und Liste an.
 */
final class IncomingPartyContactTest extends TestCase {
    use OrgaMaxApiResponses;
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    public function test_lexoffice_candidates_carry_the_persons_and_a_private_person_as_main_contact(): void {
        PluginSetting::create(['organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'enabled' => true, 'settings' => ['api_key' => 'k']]);
        FakePluginHttp::fake(['https://api.lexware.io/v1/contacts*' => FakePluginHttp::response(['content' => [
            ['id' => 'lex-1', 'company' => ['name' => 'Stimmgerecht oHG', 'contactPersons' => [
                ['salutation' => 'Herr', 'firstName' => 'Ralph', 'lastName' => 'Hartig', 'emailAddress' => 'r@stimmgerecht.test'],
                ['salutation' => 'Frau Dr.', 'firstName' => 'Birgit Maria', 'lastName' => 'Hartig', 'primary' => true],
            ]]],
            ['id' => 'lex-2', 'person' => ['salutation' => 'Herr', 'firstName' => 'Max', 'lastName' => 'Mustermann']],
        ], 'totalPages' => 1])]);

        $candidates = app(LexofficeIncomingPartyLookup::class)->lookup($this->incoming(['seller_name' => 'Stimmgerecht oHG']));

        $this->assertCount(2, $candidates);
        $this->assertNull($candidates[0]->contact);
        $this->assertSame(['Ralph', 'Birgit', 'Maria', 'Hartig', 'Dr.', true], [
            $candidates[0]->persons[0]['first_name'], $candidates[0]->persons[1]['first_name'], $candidates[0]->persons[1]['middle_names'],
            $candidates[0]->persons[1]['last_name'], $candidates[0]->persons[1]['title'], $candidates[0]->persons[1]['primary'],
        ]);
        $this->assertSame(['mr', 'Max', 'Mustermann'], [$candidates[1]->contact['salutation'] ?? null, $candidates[1]->contact['first_name'] ?? null, $candidates[1]->contact['last_name'] ?? null]);
        $this->assertSame([], $candidates[1]->persons);
    }

    public function test_easybill_candidate_takes_salutation_title_and_names_of_the_customer(): void {
        PluginSetting::create(['organization_id' => $this->organization->id, 'plugin_id' => EasybillPlugin::ID, 'enabled' => true, 'settings' => ['api_key' => 'k']]);
        FakePluginHttp::fake(['https://api.easybill.de/rest/v1/customers?*' => FakePluginHttp::response(['items' => [
            ['id' => 501, 'company_name' => 'ACME GmbH', 'salutation' => 2, 'title' => 'Dr.', 'first_name' => 'Anna Maria', 'last_name' => 'Müller', 'emails' => ['info@acme.test']],
        ]])]);

        $candidates = app(EasybillIncomingPartyLookup::class)->lookup($this->incoming(['seller_name' => 'ACME GmbH']));

        $this->assertSame(['ms', 'Dr.', 'Anna', 'Maria', 'Müller', true], [
            $candidates[0]->contact['salutation'] ?? null, $candidates[0]->contact['title'] ?? null, $candidates[0]->contact['first_name'] ?? null,
            $candidates[0]->contact['middle_names'] ?? null, $candidates[0]->contact['last_name'] ?? null, $candidates[0]->contact['primary'] ?? null,
        ]);
    }

    public function test_sevdesk_candidate_loads_the_persons_of_an_organisation_and_a_private_person_is_the_contact(): void {
        PluginSetting::create(['organization_id' => $this->organization->id, 'plugin_id' => SevDeskPlugin::ID, 'enabled' => true, 'settings' => ['api_key' => 'k']]);
        $fake = FakePluginHttp::fake(['https://my.sevdesk.de/api/v1/Contact?*' => static function (RequestInterface $request) {
            if (str_contains(urldecode($request->getUri()->getQuery()), 'parent[id]=77')) {
                return FakePluginHttp::response(['objects' => [
                    ['id' => 78, 'surename' => 'Ralph', 'familyname' => 'Hartig', 'gender' => 'm', 'parent' => ['id' => 77, 'objectName' => 'Contact']],
                    ['id' => 79, 'surename' => 'Birgit', 'familyname' => 'Hartig', 'gender' => 'w', 'academicTitle' => 'Dr.', 'parent' => ['id' => 77, 'objectName' => 'Contact']],
                ]]);
            }

            return FakePluginHttp::response(['objects' => [
                ['id' => 77, 'name' => 'Stimmgerecht oHG', 'customerNumber' => '10007'],
                ['id' => 80, 'surename' => 'Max', 'familyname' => 'Mustermann', 'gender' => 'm'],
            ]]);
        }]);

        $candidates = app(SevDeskIncomingPartyLookup::class)->lookup($this->incoming(['seller_name' => 'Stimmgerecht oHG']));

        $this->assertCount(2, $candidates);
        $this->assertNull($candidates[0]->contact);
        $this->assertSame([['mr', 'Ralph', 'Hartig', null], ['ms', 'Birgit', 'Hartig', 'Dr.']], array_map(
            static fn (array $p): array => [$p['salutation'], $p['first_name'], $p['last_name'], $p['title']],
            $candidates[0]->persons,
        ));
        $this->assertSame(['mr', 'Max', 'Mustermann'], [$candidates[1]->contact['salutation'] ?? null, $candidates[1]->contact['first_name'] ?? null, $candidates[1]->contact['last_name'] ?? null]);
        $fake->assertSent(static fn (RequestInterface $r): bool => str_contains(urldecode($r->getUri()->getQuery()), 'parent[objectName]=Contact'));
    }

    public function test_orgamax_candidate_takes_the_main_contact_person_or_the_customer_itself(): void {
        OrgaMaxConnection::create([
            'organization_id' => $this->organization->id, 'mode' => OrgaMaxConnection::MODE_PRIVATE, 'api_key' => 'k', 'api_secret' => 's', 'ownership_id' => 'own-1',
            'bearer_token' => 'token', 'token_expires_at' => Carbon::now()->addHour(), 'status' => OrgaMaxConnectionStatus::Active,
        ]);
        FakePluginHttp::fake(['https://api.orgamax.de/openapi/customer?*' => self::listResponse([
            ['id' => 'om-1', 'name' => 'Stimmgerecht oHG', 'companyName' => 'Stimmgerecht oHG', 'contactPersons' => [
                ['salutation' => 'Herr', 'firstName' => 'Ralph', 'lastName' => 'Hartig', 'email' => 'r@stimmgerecht.test', 'isMainContact' => false],
                ['salutation' => 'Frau', 'title' => 'Dr.', 'firstName' => 'Birgit', 'lastName' => 'Hartig', 'phone1' => '+49 172 1', 'isMainContact' => true],
            ]],
            ['id' => 'om-2', 'name' => 'Max Mustermann', 'salutation' => 'Herr', 'firstName' => 'Max', 'lastName' => 'Mustermann'],
        ])]);

        $candidates = app(OrgaMaxIncomingPartyLookup::class)->lookup($this->incoming(['buyer_name' => 'Stimmgerecht oHG', 'direction' => DocumentDirection::Outgoing]));

        $this->assertCount(2, $candidates);
        $this->assertSame(['ms', 'Dr.', 'Birgit', 'Hartig', '+49 172 1'], [
            $candidates[0]->contact['salutation'] ?? null, $candidates[0]->contact['title'] ?? null, $candidates[0]->contact['first_name'] ?? null,
            $candidates[0]->contact['last_name'] ?? null, $candidates[0]->contact['phone'] ?? null,
        ]);
        $this->assertSame(['Ralph', 'Birgit'], array_column($candidates[0]->persons, 'first_name'));
        $this->assertSame(['mr', 'Max', 'Mustermann'], [$candidates[1]->contact['salutation'] ?? null, $candidates[1]->contact['first_name'] ?? null, $candidates[1]->contact['last_name'] ?? null]);
    }

    public function test_adopting_a_candidate_creates_the_party_with_main_contact_and_list(): void {
        app(ModuleRegistry::class)->contribute(IncomingPartyLookup::class, PartyContactFakeLookup::class);
        $incoming = $this->incoming(['seller_name' => 'Stimmgerecht oHG', 'seller_vat_id' => 'DE286103889']);

        $supplier = app(IncomingInvoiceParties::class)->adopt($incoming, 'fakecontact:c-9', $this->admin);

        $this->assertInstanceOf(Supplier::class, $supplier);
        $this->assertSame([ContactSalutation::Ms, 'Birgit', 'Hartig', 'Birgit Hartig'], [$supplier->contact_salutation, $supplier->contact_first_name, $supplier->contact_last_name, $supplier->contact_name]);
        $this->assertSame(['Ralph', 'Birgit'], array_column($supplier->contact_persons ?? [], 'first_name'));

        // Ohne Liste füllt die Person des Kandidaten den Hauptansprechpartner; ohne beides bleibt er leer.
        $customer = app(IncomingInvoiceParties::class)->create($this->organization, false, ['name' => 'Max Mustermann', 'contact' => ContactPersons::fromExternal('Herr', 'Max', 'Mustermann', primary: true)]);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame([ContactSalutation::Mr, 'Max Mustermann'], [$customer->contact_salutation, $customer->contact_name]);
        $this->assertNull(app(IncomingInvoiceParties::class)->create($this->organization, false, ['name' => 'Ohne Person AG'])->contact_name);
    }

    /** @param  array<string, mixed>  $attributes */
    private function incoming(array $attributes): IncomingEInvoice {
        $document = Document::factory()->create(['organization_id' => $this->organization->id, 'document_type' => DocumentType::Invoice, 'created_by_user_id' => $this->admin->id]);

        return IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id, 'document_id' => $document->id, 'sha256' => hash('sha256', uniqid('inv', true)),
            'source' => 'mail', 'received_at' => now(), 'status' => IncomingEInvoiceStatus::Received,
            'recognition' => IncomingInvoiceRecognition::Extracted,
            'invoice_number' => 'RE-' . uniqid(), 'seller_name' => 'Irgendwer', 'currency' => 'EUR', 'amount_gross' => '119.00',
            'summary' => [],
            ...$attributes,
        ]);
    }
}
