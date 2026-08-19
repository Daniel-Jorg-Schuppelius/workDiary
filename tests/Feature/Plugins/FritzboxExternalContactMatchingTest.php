<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Models\{Customer, ExternalReference, IntegrationInboxItem, MsgraphContactConnection, PluginSetting, TimeEntry, User};
use App\Plugins\Fritzbox\{FritzboxImportService, FritzboxPlugin};
use App\Plugins\Fritzbox\Sources\FritzboxCall;
use App\Plugins\Lexoffice\{LexofficePhoneContactSource, LexofficePlugin};
use App\Plugins\Msgraph\MsgraphPhoneContactSource;
use App\Services\Contacts\{ExternalPhoneContactDirectory, ExternalPhoneContactSource};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** Externer Rufnummernabgleich des FRITZ!Box-Imports (Lexoffice/Microsoft 365). */
final class FritzboxExternalContactMatchingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $owner;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $this->owner->id])->save();
    }

    public function test_linked_lexoffice_contact_books_unknown_local_number_automatically(): void {
        $this->enableLexoffice();
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Externe Nummer GmbH',
            'phone' => null,
            'mobile' => null,
        ]);
        $this->linkContact(LexofficePlugin::ID, 'lex-contact-1', $customer);

        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/contacts*' => FakePluginHttp::response([
                'content' => [[
                    'id' => 'lex-contact-1',
                    'company' => ['name' => 'Externe Nummer GmbH'],
                    'phoneNumbers' => ['business' => ['0221 9567000']],
                ]],
                'totalPages' => 1,
            ]),
        ]);

        $status = $this->service(new LexofficePhoneContactSource)->bookCall(
            $this->organization,
            $this->config(),
            $this->makeCall(),
            $this->owner->id,
        );

        $this->assertSame('created', $status);
        $this->assertSame($customer->defaultProjectOrCreate()->id, TimeEntry::query()->withoutGlobalScopes()->firstOrFail()->project_id);
        $this->assertDatabaseMissing('integration_inbox_items', ['plugin_id' => FritzboxPlugin::ID]);
    }

    public function test_unlinked_microsoft_contact_enriches_inbox_without_auto_booking(): void {
        config()->set('plugins.msgraph.enabled', true);
        config()->set('plugins.msgraph.client_id', 'test-client');
        config()->set('plugins.msgraph.client_secret', 'test-secret');
        MsgraphContactConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'access-token',
            'status' => MsgraphContactConnection::STATUS_ACTIVE,
        ]);
        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/contacts*' => FakePluginHttp::response([
                'value' => [[
                    'id' => 'graph-contact-1',
                    'displayName' => 'Erika Extern',
                    'companyName' => 'Extern AG',
                    'businessPhones' => ['+49 221 9567000'],
                ]],
            ]),
        ]);

        $status = $this->service(new MsgraphPhoneContactSource)->bookCall(
            $this->organization,
            $this->config(),
            $this->makeCall(),
            $this->owner->id,
        );

        $this->assertSame('pending', $status);
        $this->assertSame(0, TimeEntry::query()->withoutGlobalScopes()->count());
        $snapshot = IntegrationInboxItem::query()->firstOrFail()->remote_snapshot;
        $this->assertSame('Erika Extern', $snapshot['name']);
        $this->assertSame(['Microsoft 365'], $snapshot['contact_sources']);
        $this->assertFalse($snapshot['contact_match_ambiguous']);
    }

    public function test_conflicting_external_contacts_never_book_automatically(): void {
        $this->enableLexoffice();
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => null,
            'mobile' => null,
        ]);
        $this->linkContact(LexofficePlugin::ID, 'lex-linked', $customer);
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/contacts*' => FakePluginHttp::response([
                'content' => [
                    ['id' => 'lex-linked', 'company' => ['name' => 'Kunde A'], 'phoneNumbers' => ['business' => ['02219567000']]],
                    ['id' => 'lex-unlinked', 'company' => ['name' => 'Kontakt B'], 'phoneNumbers' => ['business' => ['02219567000']]],
                ],
                'totalPages' => 1,
            ]),
        ]);

        $status = $this->service(new LexofficePhoneContactSource)->bookCall(
            $this->organization,
            $this->config(),
            $this->makeCall(),
            $this->owner->id,
        );

        $this->assertSame('pending', $status);
        $this->assertSame(0, TimeEntry::query()->withoutGlobalScopes()->count());
        $this->assertTrue(IntegrationInboxItem::query()->firstOrFail()->remote_snapshot['contact_match_ambiguous']);
    }

    private function enableLexoffice(): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => true,
            'settings' => [
                'api_key' => 'test-key',
                'base_url' => 'https://api.lexoffice.io/v1',
            ],
        ]);
    }

    private function linkContact(string $pluginId, string $externalId, Customer $customer): void {
        ExternalReference::query()->withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => $pluginId,
            'external_type' => 'contact',
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->id,
            'external_id' => $externalId,
        ]);
    }

    private function service(ExternalPhoneContactSource $source): FritzboxImportService {
        return new FritzboxImportService(new ExternalPhoneContactDirectory([$source]));
    }

    /** @return array<string, mixed> */
    private function config(): array {
        return [
            'default_billable' => true,
            'default_user_id' => null,
            'min_call_minutes' => 2,
            'call_lead_minutes' => 15,
            'own_number_allowlist' => [],
            'type3_outgoing' => false,
            'external_contact_matching' => true,
        ];
    }

    private function makeCall(): FritzboxCall {
        $startedAt = CarbonImmutable::parse('2026-08-19 09:00:00', 'UTC');

        return new FritzboxCall(
            type: FritzboxCall::TYPE_INCOMING,
            direction: FritzboxCall::DIR_IN,
            startedAt: $startedAt,
            endedAt: $startedAt->addMinutes(10),
            durationMinutes: 10,
            numberRaw: '02219567000',
            e164: '+492219567000',
            name: null,
            ownLine: '97911585',
        );
    }
}
