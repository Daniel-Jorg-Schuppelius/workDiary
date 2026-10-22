<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeContactSyncTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Integration\IntegrationInboxStatus;
use App\Models\Customer\Customer;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Plugins\Lexoffice\Enums\LexofficeMatchPolicy;
use App\Plugins\Lexoffice\Exceptions\LexofficeApiException;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Services\LexofficeContactSync;
use App\Services\Stammdaten\CustomerMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

class LexofficeContactSyncTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->pluginSecret('lexoffice', ['api_key' => 'test-key']);
    }

    /** @param list<array<string, mixed>> $items */
    private function fakeContacts(array $items): FakePluginHttp {
        // Je Abruf eine frische Antwort: das SDK liest den Body ohne Zurückspulen.
        return FakePluginHttp::fake([
            'https://api.lexware.io/v1/contacts*' => static fn () => FakePluginHttp::response([
                'content' => $items,
                'totalPages' => 1,
            ], 200),
        ]);
    }

    public function test_sync_links_existing_customer_via_vat_id_match(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME GmbH',
            'company' => 'ACME GmbH',
            'vat_id' => 'DE111111117',
            'email' => 'info@acme.test',
        ]);

        $this->fakeContacts([[
            'id' => 'lex-acme',
            'company' => ['name' => 'ACME GmbH', 'vatRegistrationId' => 'DE111111117'],
            'emailAddresses' => ['business' => ['info@acme.test']],
        ]]);

        $result = (new LexofficeContactSync)->sync(
            $this->organization,
            LexofficeMatchPolicy::LocalWins,
        );

        $this->assertSame(1, $result['linked']);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_id' => $customer->id,
            'external_id' => 'lex-acme',
        ]);
    }

    public function test_lexoffice_wins_policy_updates_local_fields(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Alte Firma',
            'company' => 'Alte Firma',
            'vat_id' => 'DE111111125',
            'email' => 'old@example.test',
        ]);

        $this->fakeContacts([[
            'id' => 'lex-2',
            'company' => ['name' => 'Neue Firma GmbH', 'vatRegistrationId' => 'DE111111125'],
            'emailAddresses' => ['business' => ['new@example.test']],
            'addresses' => ['billing' => [[
                'street' => 'Hauptstr. 1',
                'zip' => '12345',
                'city' => 'Berlin',
                'countryCode' => 'DE',
            ]]],
        ]]);

        $result = (new LexofficeContactSync)->sync(
            $this->organization,
            LexofficeMatchPolicy::LexofficeWins,
        );

        $this->assertSame(1, $result['updated']);
        $customer->refresh();
        $this->assertSame('Neue Firma GmbH', $customer->company);
        $this->assertSame('new@example.test', $customer->email);
        $this->assertSame('Hauptstr. 1', $customer->address_street);
    }

    public function test_manual_review_policy_records_conflict_without_changing_local(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Original AG',
            'company' => 'Original AG',
            'vat_id' => 'DE111111133',
            'email' => 'orig@example.test',
        ]);

        $this->fakeContacts([[
            'id' => 'lex-3',
            'company' => ['name' => 'Original AG', 'vatRegistrationId' => 'DE111111133'],
            'emailAddresses' => ['business' => ['remote-changed@example.test']],
        ]]);

        $result = (new LexofficeContactSync)->sync(
            $this->organization,
            LexofficeMatchPolicy::ManualReview,
        );

        $this->assertSame(1, $result['conflicts']);
        $customer->refresh();
        $this->assertSame('orig@example.test', $customer->email, 'Local data must remain unchanged in manual_review mode');

        // MVP-103: Konflikte landen jetzt in der universellen Zuordnungs-Inbox
        // (statt pending_external_conflicts).
        $this->assertDatabaseHas('integration_inbox_items', [
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'case_type' => \App\Models\Integration\IntegrationInboxItem::CASE_CONFLICT,
            'referenceable_id' => $customer->id,
            'external_id' => 'lex-3',
            'status' => IntegrationInboxStatus::Open->value,
        ]);
        $this->assertDatabaseMissing('pending_external_conflicts', ['plugin_id' => LexofficePlugin::ID]);

        $item = \App\Models\Integration\IntegrationInboxItem::query()->first();
        $this->assertNotNull($item);
        $this->assertContains('email', $item->diff_fields ?? []);
        // mapped_snapshot trägt den Remote-Wert für die spätere „Remote übernehmen"-Aktion.
        $this->assertSame('remote-changed@example.test', $item->mapped_snapshot['email'] ?? null);
    }

    public function test_create_missing_local_creates_new_customer_when_no_match_found(): void {
        $this->fakeContacts([[
            'id' => 'lex-4',
            'company' => ['name' => 'Brandneu UG', 'vatRegistrationId' => 'DE777777773'],
            'emailAddresses' => ['business' => ['hello@brandneu.test']],
            'addresses' => ['billing' => [[
                'street' => 'Neue Str. 5',
                'zip' => '20095',
                'city' => 'Hamburg',
                'countryCode' => 'DE',
            ]]],
        ]]);

        $result = (new LexofficeContactSync)->sync(
            $this->organization,
            LexofficeMatchPolicy::LocalWins,
            createMissingLocal: true,
        );

        $this->assertSame(1, $result['created']);
        $this->assertDatabaseHas('customers', [
            'organization_id' => $this->organization->id,
            'company' => 'Brandneu UG',
            'vat_id' => 'DE777777773',
            'address_city' => 'Hamburg',
        ]);
    }

    public function test_merged_customer_contact_resolves_via_alias_without_duplicate(): void {
        $source = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Alt GmbH',
            'company' => 'Alt GmbH',
        ]);
        $target = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Neu GmbH',
            'company' => 'Neu GmbH',
        ]);
        foreach ([['lex-alt', $source], ['lex-neu', $target]] as [$extId, $customer]) {
            ExternalReference::create([
                'organization_id' => $this->organization->id,
                'plugin_id' => LexofficePlugin::ID,
                'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
                'referenceable_type' => $customer->getMorphClass(),
                'referenceable_id' => $customer->id,
                'external_id' => $extId,
                'synced_at' => now(),
            ]);
        }

        // Merge: Quell-Ref 'lex-alt' kollidiert mit der Ziel-Ref → wird Alias.
        app(CustomerMergeService::class)->merge($source, $target);

        // Lexoffice liefert den gemergten Kontakt weiterhin — trotz
        // createMissingLocal darf weder ein Duplikat noch ein
        // Mehrdeutig-Inbox-Item entstehen.
        $this->fakeContacts([[
            'id' => 'lex-alt',
            'company' => ['name' => 'Alt GmbH'],
        ]]);

        $result = (new LexofficeContactSync)->sync(
            $this->organization,
            LexofficeMatchPolicy::LocalWins,
            createMissingLocal: true,
        );

        $this->assertSame(1, $result['matched']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['ambiguous']);
        $this->assertSame(1, Customer::query()->where('organization_id', $this->organization->id)->count());
        $this->assertSame(0, IntegrationInboxItem::query()->count());
        // Das Ziel behält seine Stammdaten — der Alias-Kontakt überschreibt nichts.
        $this->assertSame('Neu GmbH', $target->fresh()->name);
    }

    public function test_known_external_reference_is_recognized_as_matched(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Schon verknüpft',
            'company' => 'Schon verknüpft',
            'vat_id' => 'DE777777781',
        ]);

        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->id,
            'external_id' => 'lex-5',
            'synced_at' => now(),
        ]);

        $this->fakeContacts([[
            'id' => 'lex-5',
            'company' => ['name' => 'Schon verknüpft', 'vatRegistrationId' => 'DE777777781'],
        ]]);

        $result = (new LexofficeContactSync)->sync(
            $this->organization,
            LexofficeMatchPolicy::LocalWins,
        );

        $this->assertSame(1, $result['matched']);
        $this->assertSame(0, $result['linked']);
        $this->assertSame(0, $result['created']);
    }

    public function test_lexoffice_wins_imports_extended_contact_fields(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Detailfirma',
            'company' => 'Detailfirma',
            'vat_id' => 'DE777777790',
            'tax_number' => null,
            'mobile' => null,
            'fax' => null,
        ]);

        $this->fakeContacts([[
            'id' => 'lex-detail',
            'company' => [
                'name' => 'Detailfirma',
                'vatRegistrationId' => 'DE777777790',
                'taxNumber' => '21/815/00815',
            ],
            'roles' => ['customer' => ['number' => 10042]],
            'note' => 'Wichtiger Kunde',
            'emailAddresses' => ['business' => ['info@detail.test']],
            'phoneNumbers' => [
                'mobile' => ['+49 170 999'],
                'fax' => ['+49 30 999'],
            ],
            'addresses' => [
                'billing' => [[
                    'street' => 'Rechnung 1',
                    'zip' => '10115',
                    'city' => 'Berlin',
                    'countryCode' => 'DE',
                ]],
                'shipping' => [[
                    'street' => 'Lieferung 9',
                    'zip' => '20095',
                    'city' => 'Hamburg',
                    'countryCode' => 'DE',
                ]],
            ],
        ]]);

        (new LexofficeContactSync)->sync(
            $this->organization,
            LexofficeMatchPolicy::LexofficeWins,
        );

        $customer->refresh();
        $this->assertSame('21/815/00815', $customer->tax_number);
        $this->assertSame('+49 170 999', $customer->mobile);
        $this->assertSame('+49 30 999', $customer->fax);
        $this->assertSame('Wichtiger Kunde', $customer->comment);
        $this->assertSame('10042', $customer->accounting_number, 'Nummer an der Kontakt-Referenz (MVP-1028)');

        // city ist at-rest verschlüsselt → über das Model (entschlüsselt) prüfen,
        // nicht per assertDatabaseHas gegen den Ciphertext.
        $shipping = \App\Models\Contacts\ContactAddress::query()
            ->where('addressable_id', $customer->id)
            ->where('kind', 'shipping')
            ->first();
        $this->assertNotNull($shipping);
        $this->assertSame('Hamburg', $shipping->city);
    }

    public function test_official_number_overwrites_local_draft_number(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Entwurfskunde',
            'company' => 'Entwurfskunde',
            'vat_id' => 'DE811907980',
            'number' => 'ENTWURF-ABCD1234',
            'number_source' => 'lexoffice',
        ]);

        $this->fakeContacts([[
            'id' => 'lex-draft',
            'company' => ['name' => 'Entwurfskunde', 'vatRegistrationId' => 'DE811907980'],
            'roles' => ['customer' => ['number' => 20055]],
        ]]);

        (new LexofficeContactSync)->sync(
            $this->organization,
            LexofficeMatchPolicy::LocalWins,
        );

        $customer->refresh();
        $this->assertSame('20055', $customer->accounting_number);
        $this->assertSame('20055', (string) $customer->number);
    }

    public function test_a_mislabelled_vat_id_from_lexoffice_is_not_taken_over(): void {
        // In Lexoffice steht regelmäßig eine Steuernummer im USt-IdNr.-Feld.
        // Übernähmen wir sie, stünde sie hier dauerhaft im falschen Feld — und
        // liefe in jede USt-relevante Ausgabe.
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Fehlbeschriftet GmbH',
            'company' => 'Fehlbeschriftet GmbH',
            'vat_id' => null,
        ]);

        $this->fakeContacts([[
            'id' => 'lex-mislabelled',
            'company' => ['name' => 'Fehlbeschriftet GmbH', 'vatRegistrationId' => '16/526/00164'],
            'roles' => ['customer' => ['number' => 20099]],
        ]]);

        (new LexofficeContactSync)->sync($this->organization, LexofficeMatchPolicy::LexofficeWins);

        $this->assertNull($customer->fresh()->vat_id, 'Steuernummer gehört nicht in das USt-IdNr.-Feld');
    }

    /** Kontakt mit Verknüpfung und Lexoffice-Vergabe für die Nummernfälle (MVP-1132). */
    private function linkedDraftCustomer(string $name, string $externalId): Customer {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => $name,
            'number' => 'ENTWURF-' . strtoupper(substr(md5($name), 0, 8)),
            'number_source' => LexofficePlugin::ID,
        ]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->id,
            'external_id' => $externalId,
        ]);

        return $customer;
    }

    public function test_taken_number_becomes_an_inbox_case_and_the_sync_goes_on(): void {
        Customer::factory()->create(['organization_id' => $this->organization->id, 'number' => '10001', 'number_source' => 'local']);
        $blocked = $this->linkedDraftCustomer('Blockiert', 'lex-b');
        $next = $this->linkedDraftCustomer('Danach', 'lex-c');
        $this->fakeContacts([
            ['id' => 'lex-b', 'company' => ['name' => 'Blockiert'], 'roles' => ['customer' => ['number' => 10001]]],
            ['id' => 'lex-c', 'company' => ['name' => 'Danach'], 'roles' => ['customer' => ['number' => 10002]]],
        ]);

        (new LexofficeContactSync)->sync($this->organization, LexofficeMatchPolicy::LocalWins);

        $this->assertStringStartsWith('ENTWURF-', (string) $blocked->fresh()?->number);
        $this->assertSame('10002', $next->fresh()?->number);
        $this->assertSame('10001', $blocked->fresh()?->accounting_number);
        $this->assertDatabaseHas('integration_inbox_items', [
            'referenceable_id' => $blocked->id,
            'case_type' => IntegrationInboxItem::CASE_CONFLICT,
            'status' => IntegrationInboxStatus::Open->value,
        ]);
    }

    public function test_follow_up_sync_takes_the_number_once_the_holder_is_renumbered(): void {
        $holder = Customer::factory()->create(['organization_id' => $this->organization->id, 'number' => '10001', 'number_source' => 'local']);
        $blocked = $this->linkedDraftCustomer('Blockiert', 'lex-b');
        $this->fakeContacts([['id' => 'lex-b', 'company' => ['name' => 'Blockiert'], 'roles' => ['customer' => ['number' => 10001]]]]);
        (new LexofficeContactSync)->sync($this->organization, LexofficeMatchPolicy::LocalWins);

        $holder->update(['number' => 'K-0001']);
        (new LexofficeContactSync)->sync($this->organization, LexofficeMatchPolicy::LocalWins);

        $this->assertSame('10001', $blocked->fresh()?->number);
        $this->assertDatabaseHas('integration_inbox_items', [
            'referenceable_id' => $blocked->id,
            'status' => IntegrationInboxStatus::ResolvedRemote->value,
        ]);
    }

    public function test_accepting_the_number_names_the_holder_until_it_is_free(): void {
        $holder = Customer::factory()->create(['organization_id' => $this->organization->id, 'number' => '10001', 'number_source' => 'local']);
        $blocked = $this->linkedDraftCustomer('Blockiert', 'lex-b');
        $this->fakeContacts([['id' => 'lex-b', 'company' => ['name' => 'Blockiert'], 'roles' => ['customer' => ['number' => 10001]]]]);
        (new LexofficeContactSync)->sync($this->organization, LexofficeMatchPolicy::LocalWins);
        $item = IntegrationInboxItem::query()->where('referenceable_id', $blocked->id)->firstOrFail();
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->post(route('admin.integration.inbox.accept-remote', $item))
            ->assertSessionHas('error', __('numbering.error.taken_local', ['number' => '10001', 'holder' => $holder->displayLabel()]));

        $holder->update(['number' => 'K-0001']);
        $this->actingAs($admin)->post(route('admin.integration.inbox.accept-remote', $item))->assertSessionHas('success');

        $this->assertSame('10001', $blocked->fresh()?->number);
        $this->assertSame(IntegrationInboxStatus::ResolvedRemote, $item->fresh()?->status);
    }

    public function test_customer_created_from_lexoffice_carries_its_number_right_away(): void {
        app(\App\Services\Numbering\NumberSequenceService::class)
            ->setFormat($this->organization, \App\Enums\Numbering\NumberScope::Customer, ['source' => LexofficePlugin::ID]);
        $this->fakeContacts([[
            'id' => 'lex-new',
            'company' => ['name' => 'Aus Lexoffice GmbH'],
            'roles' => ['customer' => ['number' => 10050]],
        ]]);

        (new LexofficeContactSync)->sync($this->organization, LexofficeMatchPolicy::LocalWins, createMissingLocal: true);

        $this->assertDatabaseHas('customers', [
            'organization_id' => $this->organization->id,
            'name' => 'Aus Lexoffice GmbH',
            'number' => '10050',
            'number_source' => LexofficePlugin::ID,
        ]);
    }

    /** SDK-Kontaktendpunkt: dieselben Seitenabrufe wie zuvor, Ende nach totalPages. */
    public function test_sync_pages_through_the_contacts_endpoint(): void {
        $page = static fn (string $id, string $name): \GuzzleHttp\Psr7\Response => FakePluginHttp::response([
            'content' => [['id' => $id, 'company' => ['name' => $name], 'roles' => ['customer' => []]]],
            'totalPages' => 2,
        ]);
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/contacts*' => [$page('lex-p1', 'Seite Eins GmbH'), $page('lex-p2', 'Seite Zwei GmbH')],
        ]);

        $result = (new LexofficeContactSync)->sync($this->organization, LexofficeMatchPolicy::LocalWins, createMissingLocal: true);

        $this->assertSame(2, $result['created']);
        $this->assertSame(
            ['GET https://api.lexware.io/v1/contacts?page=0&size=100', 'GET https://api.lexware.io/v1/contacts?page=1&size=100'],
            array_map(static fn (array $entry): string => $entry['request']->getMethod() . ' ' . $entry['request']->getUri(), $fake->recorded()),
        );
    }

    public function test_sync_reports_a_missing_scope_like_before(): void {
        FakePluginHttp::fake(['https://api.lexware.io/v1/contacts*' => FakePluginHttp::response(['message' => 'forbidden'], 403)]);

        try {
            (new LexofficeContactSync)->sync($this->organization, LexofficeMatchPolicy::LocalWins);
            $this->fail('403 muss als LexofficeApiException durchschlagen.');
        } catch (LexofficeApiException $e) {
            $this->assertStringContainsString('403', $e->getMessage());
            $this->assertStringContainsString(__('Kontakte abrufen'), $e->getMessage());
        }
    }
}
