<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeTimeVoucherPayloadTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Enums\Finance\BillingMode;
use App\Enums\Invoicing\{PlaceOfSupplyKind, SalesTaxCase};
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Exceptions\UnmappableTaxCaseException;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Plugins\Lexoffice\LexofficePlugin;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-1723: „Zeiten als Beleg“ sendet einen Buchhaltungsbeleg nach der
 * Lexware-API-Referenz „Vouchers Endpoint“ (Create a Voucher): Kopf mit
 * `type`, `voucherNumber`, `voucherDate`, `totalGrossAmount`,
 * `totalTaxAmount`, `taxType`, `contactId`; Positionen je Steuersatz mit
 * `amount`, `taxAmount`, `taxRatePercent`, `categoryId`. Vorher gingen
 * Rechnungspositionen unter `voucherItems`, die die SDK-Entität verwarf —
 * Lexware bekam leere Positionen ohne Summen und Belegnummer.
 */
final class LexofficeTimeVoucherPayloadTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private const string CONTACT = 'e5b7a6c4-1f2d-4c3b-9a8e-7d6c5b4a3f21';

    private const string SERVICES = '8f8664a0-fd86-11e1-a21f-0800200c9a66';

    private User $accountant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => ['country' => 'DE']]]);
        $this->accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->accountant);
        $this->pluginSecret(LexofficePlugin::ID, ['base_url' => 'https://api.lexware.io/v1', 'api_key' => 'test-key']);
    }

    public function test_voucher_carries_the_documented_fields_with_one_item_per_tax_rate(): void {
        $customer = $this->customer(['country' => 'DE']);
        $this->entry($this->project($customer), 120);
        $reduced = $this->project($customer);
        $reduced->billingRules()->create([
            'organization_id' => $this->organization->id,
            'applies_to_kind' => null,
            'item_type' => 'service',
            'vat_rate' => 7.0,
        ]);
        $this->entry($reduced, 60);
        $fake = FakePluginHttp::fake(['https://api.lexware.io/v1/vouchers*' => FakePluginHttp::response(['id' => 'voucher-1'], 201)]);

        app(LexofficePlugin::class)->exportCustomerTime($customer, CarbonImmutable::parse('2030-04-01'), CarbonImmutable::parse('2030-04-30')->endOfDay());

        $body = $this->sentVoucher($fake);
        $this->assertSame('salesinvoice', $body['type']);
        $this->assertSame(sprintf('ZE-%s-20300430-1', $customer->number), $body['voucherNumber']);
        $this->assertStringStartsWith('2030-04-30', (string) $body['voucherDate']);
        $this->assertStringStartsWith('2030-04-30', (string) $body['shippingDate']);
        $this->assertSame('net', $body['taxType']);
        $this->assertFalse($body['useCollectiveContact']);
        $this->assertSame(self::CONTACT, $body['contactId']);
        $this->assertEquals(40.5, $body['totalTaxAmount'], '19 % auf 180,00 und 7 % auf 90,00.');
        $this->assertEquals(310.5, $body['totalGrossAmount']);

        $byRate = [];
        foreach ($body['voucherItems'] as $item) {
            $this->assertSame(['amount', 'taxAmount', 'taxRatePercent', 'categoryId'], array_keys($item), 'Nur die Felder der Belegposition, keine Rechnungsposition.');
            $this->assertSame(self::SERVICES, $item['categoryId']);
            $byRate[(string) $item['taxRatePercent']] = [(float) $item['amount'], (float) $item['taxAmount']];
        }
        ksort($byRate);
        $this->assertEquals(['19' => [180.0, 34.2], '7' => [90.0, 6.3]], $byRate);
        $this->assertArrayNotHasKey('lineItems', $body);
        $this->assertSame(0, TimeEntry::query()->where('exported', false)->count());
    }

    /** Ein Beleg trägt einen Steuerfall: Katalogleistung an einen Verbraucher im Drittland neben einer allgemeinen Leistung sperrt. */
    public function test_mixed_tax_cases_are_refused_before_any_request(): void {
        $customer = $this->customer(['country' => 'CH', 'is_consumer' => true]);
        $this->entry($this->project($customer, PlaceOfSupplyKind::Catalog), 60);
        $this->entry($this->project($customer), 60);
        $fake = FakePluginHttp::fake([]);

        try {
            app(LexofficePlugin::class)->exportCustomerTime($customer, CarbonImmutable::parse('2030-04-01'), CarbonImmutable::parse('2030-04-30')->endOfDay());
            $this->fail('Zeitbeleg mit zwei Steuerfällen angelegt.');
        } catch (UnmappableTaxCaseException $e) {
            $this->assertStringContainsString(SalesTaxCase::NotTaxable->label(), $e->getMessage());
            $this->assertStringContainsString(SalesTaxCase::Standard->label(), $e->getMessage());
        }

        $fake->assertNothingSent();
        $this->assertSame(2, TimeEntry::query()->where('exported', false)->count());
    }

    /** Lexware-Belege führen Euro: ein Kunde in Fremdwährung sperrt, statt Franken als Euro zu buchen. */
    public function test_foreign_currency_is_refused_before_any_request(): void {
        $customer = $this->customer(['country' => 'DE', 'currency' => 'CHF']);
        $this->entry($this->project($customer), 60);
        $fake = FakePluginHttp::fake([]);

        $this->expectException(UnmappableTaxCaseException::class);
        $this->expectExceptionMessage((string) __('lexoffice::finance.tax_case.time_voucher_currency', ['currency' => 'CHF']));

        try {
            app(LexofficePlugin::class)->exportCustomerTime($customer, CarbonImmutable::parse('2030-04-01'), CarbonImmutable::parse('2030-04-30')->endOfDay());
        } finally {
            $fake->assertNothingSent();
        }
    }

    /** @return array<string, mixed> */
    private function sentVoucher(FakePluginHttp $fake): array {
        foreach ($fake->recorded() as $entry) {
            $request = $entry['request'];
            if ($request->getMethod() === 'POST' && str_contains((string) $request->getUri(), '/vouchers')) {
                return (array) json_decode((string) $request->getBody(), true);
            }
        }
        $this->fail('Kein Beleg an Lexoffice gesendet.');
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes): Customer {
        $customer = Customer::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'Kunde ' . fake()->unique()->numberBetween(1, 99999),
            'number' => 'K-' . fake()->unique()->numberBetween(1000, 9999),
            'email' => 'buchhaltung@kunde.example',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'billing_mode' => BillingMode::Lexoffice,
            'created_by' => $this->accountant->id,
        ]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->id,
            'external_id' => self::CONTACT,
            'synced_at' => now(),
        ]);

        return $customer;
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

    private function entry(Project $project, int $minutes): TimeEntry {
        return TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->accountant->id,
            'date' => '2030-04-01',
            'minutes' => $minutes,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'exported' => false,
            'hourly_rate' => '90.00',
        ]);
    }
}
