<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactNumberIssuingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Numbering;

use App\Enums\Integration\IntegrationInboxStatus;
use App\Enums\Numbering\NumberScope;
use App\Models\Customer\Customer;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Platform\{PluginSetting, User};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Finance\Accounting\ContactPushService;
use App\Services\Numbering\{NumberAuthority, NumberSequenceService};
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Phase 141 (MVP-1130/1131/1134): Kundennummern vom Buchhaltungsprogramm —
 * Auswahl je Kreis, Übertragen beim Anlegen, Nachlauf, gesperrtes Feld.
 */
class ContactNumberIssuingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const LEX_ID = '7a1b2c3d-1111-4222-8333-944455556666';

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();

        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'k'],
        ]);
    }

    private function lexofficeIssuesCustomerNumbers(): void {
        app(NumberSequenceService::class)->setFormat($this->organization, NumberScope::Customer, ['source' => LexofficePlugin::ID]);
    }

    /** Lexoffice legt an (nur ID) und liefert die Nummer beim Abruf. */
    private function fakeLexoffice(int $number = 10077): FakePluginHttp {
        return FakePluginHttp::fake([
            'https://api.lexware.io/v1/contacts?*' => FakePluginHttp::response(['content' => [], 'totalPages' => 0]),
            'https://api.lexware.io/v1/contacts/*' => FakePluginHttp::response([
                'id' => self::LEX_ID,
                'version' => 1,
                'roles' => ['customer' => ['number' => $number]],
                'company' => ['name' => 'Neukunde GmbH'],
            ]),
            'https://api.lexware.io/v1/contacts' => FakePluginHttp::response(['id' => self::LEX_ID, 'resourceUri' => 'x', 'version' => 1]),
        ]);
    }

    public function test_number_page_offers_active_issuers_for_customers_only(): void {
        $response = $this->actingAs($this->admin)->get(route('admin.number-formats.index'))->assertOk();

        $rows = collect((array) $response->viewData('rows'))->keyBy(fn (array $row): string => $row['scope']->value);
        $this->assertSame(['local', LexofficePlugin::ID], array_keys($rows[NumberScope::Customer->value]['sourceOptions']));
        $this->assertNull($rows[NumberScope::Invoice->value]['sourceOptions']);
        $response->assertSee(__('numbering.source.column'));
    }

    public function test_number_page_saves_the_issuer_and_rejects_others(): void {
        $payload = ['padding' => 4, 'starts_at' => 0, 'prefix' => 'K'];

        $this->actingAs($this->admin)
            ->put(route('admin.number-formats.update'), $payload + ['scope' => NumberScope::Customer->value, 'source' => LexofficePlugin::ID])
            ->assertSessionHasNoErrors();
        $this->assertSame(LexofficePlugin::ID, app(NumberAuthority::class)->sourceFor($this->organization, NumberScope::Customer));

        $this->actingAs($this->admin)
            ->put(route('admin.number-formats.update'), $payload + ['scope' => NumberScope::Customer->value, 'source' => 'sevdesk'])
            ->assertSessionHasErrors(['source' => __('validation.in', ['attribute' => __('numbering.source.column')])]);
        $this->actingAs($this->admin)
            ->put(route('admin.number-formats.update'), $payload + ['scope' => NumberScope::Invoice->value, 'source' => LexofficePlugin::ID])
            ->assertSessionHasErrors('source');
    }

    public function test_lexoffice_switch_applies_on_save_to_document_scopes(): void {
        app(LexofficePlugin::class)->onSettingsSaved($this->organization->id, ['number_authority' => '1']);

        $authority = app(NumberAuthority::class);
        $this->assertSame(LexofficePlugin::ID, $authority->sourceFor($this->organization, NumberScope::Invoice));
        $this->assertSame(NumberAuthority::SOURCE_LOCAL, $authority->sourceFor($this->organization, NumberScope::Customer));
    }

    public function test_new_customer_gets_the_lexoffice_number_right_away(): void {
        $this->lexofficeIssuesCustomerNumbers();
        $fake = $this->fakeLexoffice();

        $this->actingAs($this->admin)
            ->post(route('customers.store'), ['name' => 'Neukunde GmbH', 'company' => 'Neukunde GmbH', 'currency' => 'EUR'])
            ->assertRedirect()
            ->assertSessionHas('info', __('numbering.flash.issued', ['number' => '10077', 'program' => 'Lexoffice']));

        $customer = Customer::query()->where('name', 'Neukunde GmbH')->firstOrFail();
        $this->assertSame('10077', $customer->number);
        $this->assertSame('10077', $customer->accounting_number);
        $fake->assertSent(fn (RequestInterface $r): bool => $r->getMethod() === 'POST' && str_ends_with($r->getUri()->getPath(), '/contacts'));
    }

    public function test_failed_transfer_keeps_the_draft_and_warns(): void {
        $this->lexofficeIssuesCustomerNumbers();
        FakePluginHttp::fake(['https://api.lexware.io/*' => FakePluginHttp::response(['message' => 'down'], 500)]);

        $this->actingAs($this->admin)
            ->post(route('customers.store'), ['name' => 'Wartekunde', 'currency' => 'EUR'])
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertTrue(NumberSequenceService::isDraft(Customer::query()->where('name', 'Wartekunde')->value('number')));
    }

    public function test_nothing_is_transferred_when_accounting_leads_the_master_data(): void {
        $this->lexofficeIssuesCustomerNumbers();
        Setting::set(ContactPushService::AUTHORITY_KEY, ContactPushService::AUTHORITY_ACCOUNTING, SettingScope::Organization, $this->organization);
        $fake = $this->fakeLexoffice();

        $this->actingAs($this->admin)
            ->post(route('customers.store'), ['name' => 'Stammdatenkunde', 'currency' => 'EUR'])
            ->assertRedirect()
            ->assertSessionMissing('warning');

        $fake->assertNothingSent();
        $this->assertTrue(NumberSequenceService::isDraft(Customer::query()->where('name', 'Stammdatenkunde')->value('number')));
    }

    public function test_number_taken_by_another_customer_goes_to_the_inbox(): void {
        $this->lexofficeIssuesCustomerNumbers();
        $holder = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Altkunde', 'number' => '10077', 'number_source' => 'local']);
        $this->fakeLexoffice();

        $this->actingAs($this->admin)
            ->post(route('customers.store'), ['name' => 'Neukunde GmbH', 'currency' => 'EUR'])
            ->assertRedirect()
            ->assertSessionHas('warning', __('numbering.flash.conflict', ['number' => '10077', 'program' => 'Lexoffice', 'holder' => $holder->displayLabel()]));

        $customer = Customer::query()->where('name', 'Neukunde GmbH')->firstOrFail();
        $this->assertTrue(NumberSequenceService::isDraft($customer->number));
        $item = IntegrationInboxItem::query()->where('referenceable_id', $customer->id)->firstOrFail();
        $this->assertSame(IntegrationInboxItem::CASE_CONFLICT, $item->case_type);
        $this->assertSame(IntegrationInboxStatus::Open, $item->status);
        $this->assertSame(['number'], $item->diff_fields);
        $this->assertStringContainsString($holder->displayLabel(), (string) $item->displayTitleText('Lexoffice'));
    }

    public function test_follow_up_command_fetches_missing_numbers(): void {
        $this->lexofficeIssuesCustomerNumbers();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Importkunde', 'number' => null]);
        $this->assertTrue(NumberSequenceService::isDraft($customer->number));
        $this->fakeLexoffice(10088);

        $this->artisan('accounting:issue-contact-numbers', ['--organization' => $this->organization->id])->assertSuccessful();

        $this->assertSame('10088', $customer->fresh()?->number);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => ExternalReference::TYPE_CONTACT,
            'referenceable_id' => $customer->id,
            'external_number' => '10088',
        ]);
    }

    public function test_number_field_is_locked_while_the_program_issues_numbers(): void {
        $this->lexofficeIssuesCustomerNumbers();
        $legacy = Customer::factory()->create(['organization_id' => $this->organization->id, 'number' => 'K-0001', 'number_source' => 'local']);

        $this->actingAs($this->admin)->get(route('customers.create'))
            ->assertOk()
            ->assertSee(__('numbering.field.locked_hint', ['program' => 'Lexoffice']));
        $this->actingAs($this->admin)
            ->post(route('customers.store'), ['name' => 'Eigenmächtig', 'number' => 'X-1', 'currency' => 'EUR'])
            ->assertSessionHasErrors('number');

        // Bestand mit eigener Nummer bleibt änderbar.
        $this->actingAs($this->admin)->get(route('customers.edit', $legacy))
            ->assertOk()
            ->assertDontSee(__('numbering.field.locked_hint', ['program' => 'Lexoffice']));
    }
}
