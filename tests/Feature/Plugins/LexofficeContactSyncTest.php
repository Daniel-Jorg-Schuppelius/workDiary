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

use App\Models\{Customer, ExternalReference, PendingExternalConflict};
use App\Plugins\Lexoffice\{LexofficeContactSync, LexofficeMatchPolicy, LexofficePlugin};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class LexofficeContactSyncTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function fakeContacts(array $items): void {
        Http::fake([
            'https://api.lexoffice.io/v1/contacts*' => Http::response([
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
            'vat_id' => 'DE123456789',
            'email' => 'info@acme.test',
        ]);

        $this->fakeContacts([[
            'id' => 'lex-acme',
            'company' => ['name' => 'ACME GmbH', 'vatRegistrationId' => 'DE123456789'],
            'emailAddresses' => ['business' => ['info@acme.test']],
        ]]);

        $result = (new LexofficeContactSync)->sync(
            $this->organization,
            LexofficeMatchPolicy::LocalWins,
            'test-key',
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
            'vat_id' => 'DE999',
            'email' => 'old@example.test',
        ]);

        $this->fakeContacts([[
            'id' => 'lex-2',
            'company' => ['name' => 'Neue Firma GmbH', 'vatRegistrationId' => 'DE999'],
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
            'test-key',
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
            'vat_id' => 'DE111',
            'email' => 'orig@example.test',
        ]);

        $this->fakeContacts([[
            'id' => 'lex-3',
            'company' => ['name' => 'Original AG', 'vatRegistrationId' => 'DE111'],
            'emailAddresses' => ['business' => ['remote-changed@example.test']],
        ]]);

        $result = (new LexofficeContactSync)->sync(
            $this->organization,
            LexofficeMatchPolicy::ManualReview,
            'test-key',
        );

        $this->assertSame(1, $result['conflicts']);
        $customer->refresh();
        $this->assertSame('orig@example.test', $customer->email, 'Local data must remain unchanged in manual_review mode');

        $this->assertDatabaseHas('pending_external_conflicts', [
            'plugin_id' => LexofficePlugin::ID,
            'conflict_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_id' => $customer->id,
            'external_id' => 'lex-3',
            'status' => PendingExternalConflict::STATUS_OPEN,
        ]);

        $conflict = PendingExternalConflict::query()->first();
        $this->assertNotNull($conflict);
        $this->assertContains('email', $conflict->diff_fields ?? []);
    }

    public function test_create_missing_local_creates_new_customer_when_no_match_found(): void {
        $this->fakeContacts([[
            'id' => 'lex-4',
            'company' => ['name' => 'Brandneu UG', 'vatRegistrationId' => 'DE777'],
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
            'test-key',
            createMissingLocal: true,
        );

        $this->assertSame(1, $result['created']);
        $this->assertDatabaseHas('customers', [
            'organization_id' => $this->organization->id,
            'company' => 'Brandneu UG',
            'vat_id' => 'DE777',
            'address_city' => 'Hamburg',
        ]);
    }

    public function test_known_external_reference_is_recognized_as_matched(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Schon verknüpft',
            'company' => 'Schon verknüpft',
            'vat_id' => 'DE555',
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
            'company' => ['name' => 'Schon verknüpft', 'vatRegistrationId' => 'DE555'],
        ]]);

        $result = (new LexofficeContactSync)->sync(
            $this->organization,
            LexofficeMatchPolicy::LocalWins,
            'test-key',
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
            'vat_id' => 'DE424242',
            'tax_number' => null,
            'mobile' => null,
            'fax' => null,
        ]);

        $this->fakeContacts([[
            'id' => 'lex-detail',
            'company' => [
                'name' => 'Detailfirma',
                'vatRegistrationId' => 'DE424242',
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
            'test-key',
        );

        $customer->refresh();
        $this->assertSame('21/815/00815', $customer->tax_number);
        $this->assertSame('+49 170 999', $customer->mobile);
        $this->assertSame('+49 30 999', $customer->fax);
        $this->assertSame('Wichtiger Kunde', $customer->comment);
        $this->assertSame('10042', (string) $customer->lexoffice_contact_number);

        $this->assertDatabaseHas('contact_addresses', [
            'addressable_id' => $customer->id,
            'kind' => 'shipping',
            'city' => 'Hamburg',
        ]);
    }

    public function test_official_number_overwrites_local_draft_number(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Entwurfskunde',
            'company' => 'Entwurfskunde',
            'vat_id' => 'DE606060',
            'number' => 'ENTWURF-ABCD1234',
            'number_source' => 'lexoffice',
        ]);

        $this->fakeContacts([[
            'id' => 'lex-draft',
            'company' => ['name' => 'Entwurfskunde', 'vatRegistrationId' => 'DE606060'],
            'roles' => ['customer' => ['number' => 20055]],
        ]]);

        (new LexofficeContactSync)->sync(
            $this->organization,
            LexofficeMatchPolicy::LocalWins,
            'test-key',
        );

        $customer->refresh();
        $this->assertSame('20055', (string) $customer->lexoffice_contact_number);
        $this->assertSame('20055', (string) $customer->number);
    }
}
