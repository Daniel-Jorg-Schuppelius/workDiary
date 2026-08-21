<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingSymmetryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Models\{Customer, ExternalReference, Organization, Supplier, User};
use App\Models\Finance\AccountingVoucher;
use App\Plugins\SevDesk\Services\SevDeskVoucherPullService;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Finance\Accounting\ContactPushService;
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Buchhaltungs-Symmetrie (Feature 122, MVP-611): Beleg-Pull aus sevDesk und
 * Kontakt-Push in die Buchhaltung.
 *
 * Die beiden Leitplanken sind wichtiger als der Transport: Führt die
 * Buchhaltung die Stammdaten, wird nicht gepusht; und die eigene USt-IdNr.
 * geht nie an einen Fremdkontakt.
 */
final class AccountingSymmetryTest extends TestCase {
    use RefreshDatabase;

    private const VOUCHERS = 'https://my.sevdesk.de/api/v1/Voucher*';

    private Organization $org;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        config()->set('plugins.sevdesk.enabled', true);
        config()->set('plugins.sevdesk.api_key', 'test-token');
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function voucher(array $overrides = []): array {
        return $overrides + [
            'id' => '9001',
            'objectName' => 'Voucher',
            'creditDebit' => 'D',
            'status' => '100',
            'voucherNumber' => 'BELEG-1',
            'voucherDate' => '2026-08-01 00:00:00',
            'sumGross' => '119.00',
            'sumNet' => '100.00',
            'currency' => 'EUR',
            'supplierName' => 'Baumarkt GmbH',
        ];
    }

    public function test_voucher_pull_mirrors_and_is_idempotent(): void {
        Supplier::factory()->create(['organization_id' => $this->org->id, 'name' => 'Baumarkt GmbH']);
        FakePluginHttp::fake([self::VOUCHERS => FakePluginHttp::response(['objects' => [$this->voucher()]])]);

        $first = app(SevDeskVoucherPullService::class)->pull($this->org->id, 1);

        $this->assertSame(1, $first['created']);
        $voucher = AccountingVoucher::query()->firstOrFail();
        $this->assertSame(SevDeskPlugin::ID, $voucher->plugin_id);
        $this->assertSame('BELEG-1', $voucher->voucher_number);
        $this->assertSame('119.00', (string) $voucher->total_amount);
        $this->assertNotNull($voucher->supplier_id);

        FakePluginHttp::fake([self::VOUCHERS => FakePluginHttp::response(['objects' => [$this->voucher(['sumGross' => '130.00'])]])]);
        $second = app(SevDeskVoucherPullService::class)->pull($this->org->id, 1);

        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['updated']);
        $this->assertSame(1, AccountingVoucher::query()->count());
        $this->assertSame('130.00', (string) AccountingVoucher::query()->firstOrFail()->total_amount);
    }

    public function test_voucher_without_id_is_ignored(): void {
        FakePluginHttp::fake([self::VOUCHERS => FakePluginHttp::response(['objects' => [$this->voucher(['id' => ''])]])]);

        $result = app(SevDeskVoucherPullService::class)->pull($this->org->id, 1);

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, AccountingVoucher::query()->count());
    }

    public function test_pull_without_api_key_does_nothing(): void {
        config()->set('plugins.sevdesk.api_key', '');
        $fake = FakePluginHttp::fake([self::VOUCHERS => FakePluginHttp::response(['objects' => []])]);

        $result = app(SevDeskVoucherPullService::class)->pull($this->org->id, 1);

        $this->assertSame(['read' => 0, 'created' => 0, 'updated' => 0], $result);
        $fake->assertNothingSent();
    }

    public function test_mirrored_voucher_appears_in_the_document_feed(): void {
        FakePluginHttp::fake([self::VOUCHERS => FakePluginHttp::response(['objects' => [$this->voucher()]])]);
        app(SevDeskVoucherPullService::class)->pull($this->org->id, 1);

        $filters = new \App\Services\Billing\DocumentFeedFilters(
            organizationId: (int) $this->org->id,
            userId: (int) $this->admin->id,
            from: \Carbon\CarbonImmutable::parse('2026-07-01')->startOfDay(),
            to: \Carbon\CarbonImmutable::parse('2026-09-30')->endOfDay(),
            sources: ['invoice' => true, 'quote' => true, 'voucher' => true, 'incoming_einvoice' => true, 'expense' => true],
        );

        $rows = (new \App\Services\Billing\DocumentFeedQuery($filters))->paginate(50, 'doc_date', 'desc')->items();

        $mirrored = collect($rows)->firstWhere('number', 'BELEG-1');
        $this->assertNotNull($mirrored, 'Der gespiegelte Beleg fehlt im Belegfluss.');
        $this->assertSame('sevdesk', (string) $mirrored->origin);
    }

    public function test_own_vat_id_and_email_are_stripped_from_the_payload(): void {
        Setting::set('einvoice.vat_id', 'DE278737004', SettingScope::Organization, $this->org);
        Setting::set('einvoice.contact_email', 'info@example.test', SettingScope::Organization, $this->org);

        $fields = app(ContactPushService::class)->withoutOwnIdentity([
            'vat_id' => 'DE 278 737 004',
            'email' => 'INFO@example.test',
        ]);

        $this->assertArrayNotHasKey('vat_id', $fields);
        $this->assertArrayNotHasKey('email', $fields);
    }

    public function test_foreign_vat_id_survives(): void {
        Setting::set('einvoice.vat_id', 'DE278737004', SettingScope::Organization, $this->org);

        $fields = app(ContactPushService::class)->withoutOwnIdentity(['vat_id' => 'DE999999999']);

        $this->assertSame('DE999999999', $fields['vat_id']);
    }

    public function test_push_is_blocked_when_accounting_leads(): void {
        Setting::set(ContactPushService::AUTHORITY_KEY, ContactPushService::AUTHORITY_ACCOUNTING, SettingScope::Organization, $this->org);
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);

        $this->assertFalse(app(ContactPushService::class)->pushAllowed());

        $this->expectException(RuntimeException::class);
        app(ContactPushService::class)->push($customer, SevDeskPlugin::ID);
    }

    public function test_push_records_the_external_reference(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id, 'number' => 'K-0042', 'name' => 'Kunde AG']);
        FakePluginHttp::fake([
            'https://my.sevdesk.de/api/v1/Contact*' => FakePluginHttp::response(['objects' => ['id' => '4711']]),
        ]);

        $externalId = app(ContactPushService::class)->push($customer, SevDeskPlugin::ID);

        $this->assertSame('4711', $externalId);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => SevDeskPlugin::ID,
            'external_type' => 'contact',
            'external_id' => '4711',
            'referenceable_id' => $customer->id,
        ]);
    }

    public function test_second_push_updates_instead_of_duplicating(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id, 'number' => 'K-0042', 'name' => 'Kunde AG']);
        $fake = FakePluginHttp::fake([
            // Erste Antwort: Suche findet den Kontakt → PUT statt POST.
            'https://my.sevdesk.de/api/v1/Contact*' => FakePluginHttp::response(['objects' => [['id' => '4711', 'customerNumber' => 'K-0042']]]),
        ]);

        app(ContactPushService::class)->push($customer, SevDeskPlugin::ID);

        $fake->assertSent(fn ($request): bool => $request->getMethod() === 'PUT'
            && str_contains((string) $request->getUri(), '/Contact/4711'));
        $this->assertSame(1, ExternalReference::query()->count());
    }

    public function test_http_push_action_reports_the_external_id(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id, 'number' => 'K-0099', 'name' => 'Kunde AG']);
        FakePluginHttp::fake([
            'https://my.sevdesk.de/api/v1/Contact*' => FakePluginHttp::response(['objects' => ['id' => '5150']]),
        ]);

        $this->actingAs($this->admin)
            ->post(route('accounting.contacts.push', $customer), ['plugin' => SevDeskPlugin::ID])
            ->assertRedirect();

        $this->assertDatabaseHas('external_references', ['external_id' => '5150']);
    }

    public function test_command_reports_the_authority_instead_of_pushing(): void {
        Setting::set(ContactPushService::AUTHORITY_KEY, ContactPushService::AUTHORITY_ACCOUNTING, SettingScope::Organization, $this->org);
        Customer::factory()->create(['organization_id' => $this->org->id]);
        $fake = FakePluginHttp::fake(['https://my.sevdesk.de/api/v1/Contact*' => FakePluginHttp::response(['objects' => ['id' => '1']])]);

        $this->artisan('accounting:push-contacts', ['plugin' => SevDeskPlugin::ID])->assertExitCode(0);

        $fake->assertNothingSent();
        $this->assertSame(0, ExternalReference::query()->count());
    }
}
