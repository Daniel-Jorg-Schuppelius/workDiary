<?php
/*
 * Created on   : Mon Nov 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransferTaxCaseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\{BillingMode, TransferChannel, TransferStatus, TransferTarget};
use App\Enums\Invoicing\{PlaceOfSupplyKind, SalesTaxCase};
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Exceptions\UnmappableTaxCaseException;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Finance\BillingTransfer;
use App\Models\Integration\ExternalReference;
use App\Models\Material\MaterialUsage;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\{TimeEntry, Timesheet};
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\OrgaMax\Enums\OrgaMaxConnectionStatus;
use App\Plugins\OrgaMax\Models\OrgaMaxConnection;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Plugins\OrgaMax\Services\OrgaMaxTarget;
use App\Plugins\SevDesk\Api\SevDeskClient;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Finance\BillingTransferService;
use App\Services\Finance\Targets\{FileTarget, TransferTaxCases};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Cache, Storage};
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-1712: Die Faktura-Übergabe gibt den Steuerfall als Steuerart des
 * Zielsystems mit — wie der Rechnungslauf aus Kunde und Leistungsort der
 * Positionen ermittelt. Ein Fall ohne Steuerart beim Ziel oder mehrere Fälle
 * sperren das Übertragen vorher mit Grund; der Standardfall bleibt
 * unverändert.
 */
final class TransferTaxCaseTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    /** Lexware vergibt UUIDs; isValid() der Belegadresse prüft darauf. */
    private const string LEXOFFICE_CONTACT = 'e5b7a6c4-1f2d-4c3b-9a8e-7d6c5b4a3f21';

    private User $accountant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => ['country' => 'DE']]]);
        $this->accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->accountant);
    }

    // ── Lexoffice ───────────────────────────────────────────────────────

    public function test_lexoffice_sends_external_service_13b_for_an_eu_business(): void {
        $customer = $this->customer(['country' => 'AT', 'vat_id' => 'ATU13585627'], BillingMode::Lexoffice);
        $transfer = $this->timeTransfer($customer, TransferTarget::Lexoffice);
        $fake = FakePluginHttp::fake(['https://api.lexware.io/v1/invoices*' => FakePluginHttp::response(['id' => 'lex-1'], 201)]);

        $this->post(route('finance.transfers.execute', $transfer))->assertSessionHasNoErrors();

        $this->assertSame(TransferStatus::Transferred, $transfer->refresh()->status);
        $body = self::body($fake, '/invoices');
        $this->assertSame('externalService13b', $body['taxConditions']['taxType']);
        $this->assertStringContainsString('Reverse Charge', (string) $body['taxConditions']['taxTypeNote']);
        $this->assertEquals(0, $body['lineItems'][0]['unitPrice']['taxRatePercentage']);
    }

    /** Gegenprobe Inland: Voreinstellung und Satz wie bisher, kein Hinweis. */
    public function test_lexoffice_domestic_transfer_is_unchanged(): void {
        $customer = $this->customer(['country' => 'DE'], BillingMode::Lexoffice);
        $transfer = $this->timeTransfer($customer, TransferTarget::Lexoffice);
        $fake = FakePluginHttp::fake(['https://api.lexware.io/v1/invoices*' => FakePluginHttp::response(['id' => 'lex-2'], 201)]);

        $this->post(route('finance.transfers.execute', $transfer))->assertSessionHasNoErrors();

        $body = self::body($fake, '/invoices');
        $this->assertSame(['taxType' => 'net'], $body['taxConditions']);
        $this->assertEquals(19, $body['lineItems'][0]['unitPrice']['taxRatePercentage']);
    }

    /** Grundstück im EU-Ausland: Lexware kennt nur Drittländer — gesperrt, nichts gesendet, Nachweis bleibt bestätigt. */
    public function test_lexoffice_blocks_a_property_abroad_in_the_eu(): void {
        $customer = $this->customer(['country' => 'DE'], BillingMode::Lexoffice);
        $transfer = $this->timeTransfer($customer, TransferTarget::Lexoffice, PlaceOfSupplyKind::Property, 'AT');
        $fake = FakePluginHttp::fake([]);
        $reason = (string) __('lexoffice::finance.tax_case.not_taxable_outside_third_country');

        $this->get(route('finance.transfers.show', $transfer))->assertOk()
            ->assertSee(SalesTaxCase::NotTaxable->label())
            ->assertSee(__('finance.tax_case.blocked_title'))
            ->assertSee($reason);
        $this->post(route('finance.transfers.execute', $transfer))->assertSessionHasErrors(['transfer' => $reason]);

        $this->assertSame(TransferStatus::Confirmed, $transfer->refresh()->status);
        $fake->assertNothingSent();
        $this->assertSame(1, TimeEntry::query()->where('exported', false)->count());
    }

    /** Der Beleg aus Zeiten (Kundenseite) trägt keine Steuerart — außer dem Standardfall sperrt er. */
    public function test_lexoffice_time_voucher_refuses_other_cases(): void {
        $customer = $this->customer(['country' => 'CH'], BillingMode::Lexoffice);
        $this->timeEntry($this->project($customer));
        $fake = FakePluginHttp::fake([]);

        try {
            app(LexofficePlugin::class)->exportCustomerTime($customer, CarbonImmutable::parse('2030-04-01'), CarbonImmutable::parse('2030-04-30')->endOfDay());
            $this->fail('Zeitbeleg trotz nicht steuerbarer Leistung angelegt.');
        } catch (UnmappableTaxCaseException $e) {
            $this->assertStringContainsString(SalesTaxCase::NotTaxable->label(), $e->getMessage());
        }

        $fake->assertNothingSent();
        $this->assertSame(1, TimeEntry::query()->where('exported', false)->count());
    }

    // ── sevDesk ─────────────────────────────────────────────────────────

    public function test_sevdesk_domestic_reverse_charge_uses_rule_5_with_zero_rates(): void {
        $customer = $this->customer(['country' => 'DE', 'is_reverse_charge_recipient' => true], BillingMode::SevDesk);
        $transfer = $this->timeTransfer($customer, TransferTarget::SevDesk);
        $fake = FakePluginHttp::fake($this->sevDeskStubs());

        $this->post(route('finance.transfers.execute', $transfer))->assertSessionHasNoErrors();

        $body = self::body($fake, '/Invoice/Factory/saveInvoice');
        $this->assertEquals(['id' => 5, 'objectName' => 'TaxRule'], $body['invoice']['taxRule']);
        $this->assertEquals(0, $body['invoicePosSave'][0]['taxRate']);
    }

    public function test_sevdesk_service_to_a_third_country_uses_rule_17(): void {
        $customer = $this->customer(['country' => 'CH'], BillingMode::SevDesk);
        $transfer = $this->timeTransfer($customer, TransferTarget::SevDesk);
        $fake = FakePluginHttp::fake($this->sevDeskStubs());

        $this->post(route('finance.transfers.execute', $transfer))->assertSessionHasNoErrors();

        $this->assertEquals(17, self::body($fake, '/Invoice/Factory/saveInvoice')['invoice']['taxRule']['id']);
    }

    public function test_sevdesk_small_business_uses_rule_11(): void {
        $this->organization->update(['settings' => ['einvoice' => ['country' => 'DE', 'small_business' => '1']]]);
        $customer = $this->customer(['country' => 'DE'], BillingMode::SevDesk);
        $transfer = $this->timeTransfer($customer, TransferTarget::SevDesk);
        $fake = FakePluginHttp::fake($this->sevDeskStubs());

        $this->post(route('finance.transfers.execute', $transfer))->assertSessionHasNoErrors();

        $body = self::body($fake, '/Invoice/Factory/saveInvoice');
        $this->assertEquals(11, $body['invoice']['taxRule']['id']);
        $this->assertEquals(0, $body['invoicePosSave'][0]['taxRate']);
    }

    /** Gegenprobe Inland: die eingestellte Regel (Vorgabe 1) wie bisher. */
    public function test_sevdesk_domestic_transfer_keeps_the_configured_rule(): void {
        $customer = $this->customer(['country' => 'DE'], BillingMode::SevDesk);
        $transfer = $this->timeTransfer($customer, TransferTarget::SevDesk);
        $fake = FakePluginHttp::fake($this->sevDeskStubs());

        $this->post(route('finance.transfers.execute', $transfer))->assertSessionHasNoErrors();

        $body = self::body($fake, '/Invoice/Factory/saveInvoice');
        $this->assertEquals(['id' => 1, 'objectName' => 'TaxRule'], $body['invoice']['taxRule']);
        $this->assertEquals(19, $body['invoicePosSave'][0]['taxRate']);
    }

    /** Buchhaltung 1.0 kennt Reverse Charge an EU-Unternehmer nicht: gesperrt vor dem Senden. */
    public function test_sevdesk_version_one_blocks_eu_reverse_charge(): void {
        Cache::put(SevDeskClient::versionCacheKey($this->organization->id), '1.0', 600);
        $customer = $this->customer(['country' => 'AT', 'vat_id' => 'ATU13585627'], BillingMode::SevDesk);
        $transfer = $this->timeTransfer($customer, TransferTarget::SevDesk);
        $fake = FakePluginHttp::fake($this->sevDeskStubs());

        $this->post(route('finance.transfers.execute', $transfer))
            ->assertSessionHasErrors(['transfer' => (string) __('sevdesk::finance.tax_case.version_one', ['case' => SalesTaxCase::EuReverseCharge->label()])]);

        $this->assertSame(TransferStatus::Confirmed, $transfer->refresh()->status);
        $fake->assertNothingSent();
    }

    // ── easybill ────────────────────────────────────────────────────────

    public function test_easybill_goods_to_an_eu_business_are_an_intra_community_supply(): void {
        $customer = $this->customer(['country' => 'AT', 'vat_id' => 'ATU13585627'], BillingMode::Easybill);
        $transfer = $this->materialTransfer($customer, TransferTarget::Easybill);
        $fake = FakePluginHttp::fake($this->easybillStubs());

        $this->post(route('finance.transfers.execute', $transfer))->assertSessionHasNoErrors();

        $body = self::body($fake, '/rest/v1/documents', 'POST');
        $this->assertSame('IG', $body['vat_option']);
        $this->assertEquals(0, $body['items'][0]['vat_percent']);
    }

    public function test_easybill_goods_to_a_third_country_are_an_export(): void {
        $customer = $this->customer(['country' => 'CH'], BillingMode::Easybill);
        $transfer = $this->materialTransfer($customer, TransferTarget::Easybill);
        $fake = FakePluginHttp::fake($this->easybillStubs());

        $this->post(route('finance.transfers.execute', $transfer))->assertSessionHasNoErrors();

        $this->assertSame('AL', self::body($fake, '/rest/v1/documents', 'POST')['vat_option']);
    }

    /** Katalogleistung an einen Verbraucher im Drittland: nicht steuerbar (Drittland). */
    public function test_easybill_catalog_service_to_a_third_country_consumer_is_not_taxable(): void {
        $customer = $this->customer(['country' => 'CH', 'is_consumer' => true], BillingMode::Easybill);
        $transfer = $this->timeTransfer($customer, TransferTarget::Easybill, PlaceOfSupplyKind::Catalog);
        $fake = FakePluginHttp::fake($this->easybillStubs());

        $this->post(route('finance.transfers.execute', $transfer))->assertSessionHasNoErrors();

        $body = self::body($fake, '/rest/v1/documents', 'POST');
        $this->assertSame('nStb', $body['vat_option']);
        $this->assertEquals(0, $body['items'][0]['vat_percent']);
    }

    /** Gegenprobe Inland: kein vat_option, Satz der Position. */
    public function test_easybill_domestic_transfer_sends_no_vat_option(): void {
        $customer = $this->customer(['country' => 'DE'], BillingMode::Easybill);
        $transfer = $this->timeTransfer($customer, TransferTarget::Easybill);
        $fake = FakePluginHttp::fake($this->easybillStubs());

        $this->post(route('finance.transfers.execute', $transfer))->assertSessionHasNoErrors();

        $body = self::body($fake, '/rest/v1/documents', 'POST');
        $this->assertArrayNotHasKey('vat_option', $body);
        $this->assertEquals(19, $body['items'][0]['vat_percent']);
    }

    // ── orgaMAX ─────────────────────────────────────────────────────────

    /** Die orgaMAX-API führt keine Steuerart: Reverse Charge bleibt hier, mit Grund. */
    public function test_orgamax_blocks_reverse_charge(): void {
        $customer = $this->customer(['country' => 'AT', 'vat_id' => 'ATU13585627'], BillingMode::OrgaMax);
        $this->orgaMaxConnection($customer);
        $transfer = $this->timeTransfer($customer, TransferTarget::OrgaMax);
        $fake = FakePluginHttp::fake([]);
        $reason = (string) __('orgamax::orgamax.tax_case.unmappable', ['case' => SalesTaxCase::EuReverseCharge->label()]);

        $this->post(route('finance.transfers.execute', $transfer))->assertSessionHasErrors(['transfer' => $reason]);

        // Auch der direkte Aufruf des Ziels sendet nichts.
        try {
            app(OrgaMaxTarget::class)->transfer($transfer->refresh());
            $this->fail('orgaMAX-Auftrag trotz Reverse Charge angelegt.');
        } catch (UnmappableTaxCaseException $e) {
            $this->assertSame($reason, $e->getMessage());
        }
        $fake->assertNothingSent();
        $this->assertSame(TransferStatus::Confirmed, $transfer->status);
    }

    // ── Zielunabhängig ──────────────────────────────────────────────────

    /** Katalog- und allgemeine Leistung an einen Verbraucher im Drittland: zwei Fälle — wie beim Ausstellen zu teilen. */
    public function test_mixed_tax_cases_block_every_target(): void {
        Storage::fake(FileTarget::DISK);
        $customer = $this->customer(['country' => 'CH', 'is_consumer' => true], BillingMode::Workdiary);
        $this->timeEntry($this->project($customer, PlaceOfSupplyKind::Catalog));
        $this->timeEntry($this->project($customer));
        $transfer = $this->confirmed($customer, TransferTarget::File, TransferChannel::Time);

        $this->assertCount(2, app(TransferTaxCases::class)->for($transfer));
        $this->post(route('finance.transfers.execute', $transfer))->assertSessionHasErrors('transfer');

        $this->assertSame(TransferStatus::Confirmed, $transfer->refresh()->status);
        $this->assertSame([], Storage::disk(FileTarget::DISK)->allFiles());
        $this->expectException(UnmappableTaxCaseException::class);
        app(TransferTaxCases::class)->single($transfer);
    }

    /** Bestätigen meldet den Fall vorher, sperrt aber erst das Übertragen. */
    public function test_confirm_warns_about_an_unmappable_case(): void {
        $customer = $this->customer(['country' => 'AT', 'vat_id' => 'ATU13585627'], BillingMode::OrgaMax);
        $this->timeEntry($this->project($customer));
        $transfer = app(BillingTransferService::class)->createDraft($customer, TransferChannel::Time, TransferTarget::OrgaMax, ['from' => '2030-04-01', 'to' => '2030-04-30'], null, $this->accountant);

        $this->post(route('finance.transfers.confirm', $transfer))->assertSessionHas('warning');

        $this->assertSame(TransferStatus::Confirmed, $transfer->refresh()->status);
    }

    /** Das Datei-Paket hat keine Steuerspalte: der Fall ohne Steuer steht im Kopf. */
    public function test_file_package_names_a_case_without_tax(): void {
        Storage::fake(FileTarget::DISK);
        $customer = $this->customer(['country' => 'CH'], BillingMode::Workdiary);
        $transfer = $this->timeTransfer($customer, TransferTarget::File);

        $this->post(route('finance.transfers.execute', $transfer))->assertSessionHasNoErrors();

        $content = (string) Storage::disk(FileTarget::DISK)->get((string) $transfer->refresh()->file_path);
        $this->assertStringContainsString('# ' . __('finance.field.tax_case') . ': ' . SalesTaxCase::NotTaxable->label(), $content);
    }

    // ── Hilfen ──────────────────────────────────────────────────────────

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes, BillingMode $mode): Customer {
        $customer = Customer::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'Kunde ' . fake()->unique()->numberBetween(1, 99999),
            'number' => 'K-' . fake()->unique()->numberBetween(1000, 9999),
            'email' => 'buchhaltung@kunde.example',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'billing_mode' => $mode,
            'created_by' => $this->accountant->id,
        ]);

        // Bestehende Zuordnung beim Ziel: die Übergabe legt keinen Kontakt an.
        match ($mode) {
            BillingMode::Lexoffice => $this->contactReference($customer, LexofficePlugin::ID, self::LEXOFFICE_CONTACT),
            BillingMode::SevDesk => $this->contactReference($customer, SevDeskPlugin::ID, '501'),
            BillingMode::Easybill => $this->contactReference($customer, EasybillPlugin::ID, '501'),
            default => null,
        };
        match ($mode) {
            BillingMode::Lexoffice => $this->pluginSecret(LexofficePlugin::ID, ['base_url' => 'https://api.lexware.io/v1', 'api_key' => 'test-key']),
            BillingMode::SevDesk => $this->pluginSecret(SevDeskPlugin::ID, ['api_key' => 'tok-123']),
            BillingMode::Easybill => $this->pluginSecret(EasybillPlugin::ID, ['api_key' => 'key-123']),
            default => null,
        };

        return $customer;
    }

    private function contactReference(Customer $customer, string $pluginId, string $externalId): ExternalReference {
        return ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => $pluginId,
            'external_type' => ExternalReference::TYPE_CONTACT,
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->id,
            'external_id' => $externalId,
            'synced_at' => now(),
        ]);
    }

    private function orgaMaxConnection(Customer $customer): void {
        OrgaMaxConnection::create([
            'organization_id' => $this->organization->id,
            'mode' => OrgaMaxConnection::MODE_PRIVATE,
            'api_key' => 'k',
            'api_secret' => 's',
            'ownership_id' => 'own-1',
            'bearer_token' => 'token',
            'token_expires_at' => Carbon::now()->addHour(),
            'status' => OrgaMaxConnectionStatus::Active,
            'capabilities' => ['billing' => ['enabled' => true, 'leader' => 'orgamax']],
        ]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => OrgaMaxPlugin::ID,
            'external_type' => 'customer',
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->id,
            'external_id' => '4711',
            'synced_at' => now(),
        ]);
    }

    private function project(Customer $customer, ?PlaceOfSupplyKind $kind = null): Project {
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Projekt ' . fake()->unique()->numberBetween(1, 99999),
            'status' => ProjectStatus::Active->value,
            'is_default' => false,
        ]);
        if ($kind !== null) {
            $project->billingRules()->create([
                'organization_id' => $this->organization->id,
                'applies_to_kind' => null,
                'item_type' => 'service',
                'place_of_supply_kind' => $kind->value,
            ]);
        }

        return $project;
    }

    /** Zwei Stunden am 01.04.2030, mit Einsatzort im Land $siteCountry. */
    private function timeEntry(Project $project, ?string $siteCountry = null): TimeEntry {
        $order = $siteCountry !== null ? DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->accountant->id,
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'title' => 'Einsatz',
            'address_country' => $siteCountry,
        ]) : null;

        return TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'diary_entry_id' => $order?->id,
            'user_id' => $this->accountant->id,
            'date' => '2030-04-01',
            'minutes' => 120,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'exported' => false,
            'hourly_rate' => '90.00',
        ]);
    }

    private function timeTransfer(Customer $customer, TransferTarget $target, ?PlaceOfSupplyKind $kind = null, ?string $siteCountry = null): BillingTransfer {
        $this->timeEntry($this->project($customer, $kind), $siteCountry);

        return $this->confirmed($customer, $target, TransferChannel::Time);
    }

    private function materialTransfer(Customer $customer, TransferTarget $target): BillingTransfer {
        $timesheet = Timesheet::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->accountant->id,
            'project_id' => $this->project($customer)->id,
            'work_date' => '2030-04-02',
            'kind' => \App\Enums\Timesheet\TimesheetKind::Project,
            'status' => \App\Enums\Timesheet\TimesheetStatus::Draft,
        ]);
        MaterialUsage::create([
            'organization_id' => $this->organization->id,
            'timesheet_id' => $timesheet->id,
            'description' => 'Kabel',
            'quantity' => '3.000',
            'unit' => 'Stk',
            'unit_price' => '10.0000',
            'billed' => false,
        ]);

        return $this->confirmed($customer, $target, TransferChannel::Material);
    }

    private function confirmed(Customer $customer, TransferTarget $target, TransferChannel $channel): BillingTransfer {
        $service = app(BillingTransferService::class);
        $transfer = $service->createDraft($customer, $channel, $target, ['from' => '2030-04-01', 'to' => '2030-04-30'], null, $this->accountant);
        $service->confirm($transfer, $this->accountant);

        return $transfer->refresh();
    }

    /** @return array<string, mixed> */
    private function sevDeskStubs(): array {
        return [
            'https://my.sevdesk.de/api/v1/Tools/bookkeepingSystemVersion*' => FakePluginHttp::response(['objects' => ['version' => '2.0']]),
            'https://my.sevdesk.de/api/v1/SevUser*' => FakePluginHttp::response(['objects' => [['id' => 7]]]),
            'https://my.sevdesk.de/api/v1/Invoice?*' => FakePluginHttp::response(['objects' => []]),
            'https://my.sevdesk.de/api/v1/Invoice/Factory/saveInvoice' => FakePluginHttp::response(['objects' => ['invoice' => ['id' => 9001, 'status' => '100']]], 201),
        ];
    }

    /** @return array<string, mixed> */
    private function easybillStubs(): array {
        return [
            'https://api.easybill.de/rest/v1/documents?*' => FakePluginHttp::response(['items' => []]),
            'https://api.easybill.de/rest/v1/documents' => FakePluginHttp::response(['id' => 9001, 'type' => 'INVOICE', 'is_draft' => true], 201),
        ];
    }

    /** @return array<string, mixed> Rumpf der ersten Anfrage, deren Pfad auf $path endet */
    private static function body(FakePluginHttp $fake, string $path, string $method = 'POST'): array {
        foreach ($fake->recorded() as $entry) {
            /** @var RequestInterface $request */
            $request = $entry['request'];
            if ($request->getMethod() === $method && str_ends_with($request->getUri()->getPath(), $path)) {
                return (array) json_decode((string) $request->getBody(), true);
            }
        }
        self::fail('Keine Anfrage ' . $method . ' ' . $path . ' gesendet.');
    }
}
