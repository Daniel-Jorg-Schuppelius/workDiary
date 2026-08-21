<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SepaMandateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{MandateKind, MandateStatus, PaymentRunKind};
use App\Models\{Customer, Organization, User};
use App\Models\Finance\{BankAccount, SepaMandate};
use App\Services\Finance\FinancialFormatsSupport;
use App\Services\Finance\Sepa\PaymentRunService;
use App\Settings\SettingScope;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Mandatsregister und Lastschrifteinzug (Feature 120, MVP-609).
 *
 * Ein Mandat ist die Erlaubnis des Kunden — abgelaufen oder widerrufen ist es
 * keine mehr, und das muss vor der Datei auffallen, nicht danach.
 */
class SepaMandateTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private BankAccount $account;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->account = BankAccount::factory()->create([
            'organization_id' => $this->org->id,
            'iban' => 'DE02120300000000202051',
            'bic' => 'BYLADEM1001',
            'account_holder' => 'Muster GmbH',
        ]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id, 'name' => 'Kunde AG']);
    }

    /** @param array<string, mixed> $attributes */
    private function mandate(array $attributes = []): SepaMandate {
        return SepaMandate::query()->create(array_merge([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'reference' => 'MND-' . fake()->unique()->numberBetween(1000, 9999),
            'kind' => MandateKind::Recurring->value,
            'status' => MandateStatus::Active->value,
            'signed_on' => CarbonImmutable::today()->subYear()->toDateString(),
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'Kunde AG',
        ], $attributes));
    }

    private function service(): PaymentRunService {
        return app(PaymentRunService::class);
    }

    public function test_active_mandate_is_usable(): void {
        $this->assertTrue($this->mandate()->isUsable());
    }

    public function test_revoked_mandate_is_not_usable(): void {
        $mandate = $this->mandate(['status' => MandateStatus::Revoked->value]);

        $this->assertFalse($mandate->isUsable());
    }

    public function test_mandate_unused_for_36_months_is_not_usable(): void {
        $mandate = $this->mandate(['last_collected_on' => CarbonImmutable::today()->subMonths(37)->toDateString()]);

        $this->assertFalse($mandate->isUsable());
    }

    public function test_first_collection_has_the_longer_lead_time(): void {
        $first = $this->mandate();
        $recurring = $this->mandate(['last_collected_on' => CarbonImmutable::today()->subMonth()->toDateString()]);

        $today = CarbonImmutable::today();
        $this->assertSame($today->addWeekdays(5)->toDateString(), $this->service()->earliestCollection($first, $today)->toDateString());
        $this->assertSame($today->addWeekdays(2)->toDateString(), $this->service()->earliestCollection($recurring, $today)->toDateString());
    }

    public function test_direct_debit_run_uses_the_mandate(): void {
        $mandate = $this->mandate();

        $run = $this->service()->createDirectDebit($this->account, $this->admin, $mandate, 250.00, 'Retainer August');

        $this->assertSame(PaymentRunKind::DirectDebit, $run->kind);
        $this->assertSame('250.00', (string) $run->total);
        $this->assertSame($mandate->id, $run->items()->firstOrFail()->sepa_mandate_id);
    }

    public function test_unusable_mandate_is_rejected(): void {
        $mandate = $this->mandate(['status' => MandateStatus::Revoked->value]);

        $this->expectException(RuntimeException::class);
        $this->service()->createDirectDebit($this->account, $this->admin, $mandate, 100.00, 'Test');
    }

    public function test_direct_debit_export_needs_the_creditor_identifier(): void {
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht installiert.');
        }
        $run = $this->service()->createDirectDebit($this->account, $this->admin, $this->mandate(), 100.00, 'Test');
        $this->service()->release($run, $this->admin);

        $this->expectException(RuntimeException::class);
        $this->service()->export($run->refresh(), $this->admin);
    }

    public function test_direct_debit_export_creates_pain008(): void {
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht installiert.');
        }
        Setting::set('finance.sepa_creditor_id', 'DE98ZZZ09999999999', SettingScope::Organization, $this->org);

        $mandate = $this->mandate();
        $run = $this->service()->createDirectDebit($this->account, $this->admin, $mandate, 250.00, 'Retainer August');
        $this->service()->release($run, $this->admin);

        $xml = $this->service()->export($run->refresh(), $this->admin);

        $this->assertStringContainsString('CstmrDrctDbtInitn', $xml);
        $this->assertStringContainsString('DE98ZZZ09999999999', $xml);
        $this->assertStringContainsString($mandate->reference, $xml);
    }

    public function test_revoke_keeps_the_mandate_as_evidence(): void {
        $mandate = $this->mandate();

        $this->actingAs($this->admin)->post(route('finance.mandates.revoke', $mandate))->assertRedirect();

        $fresh = $mandate->fresh();
        $this->assertSame(MandateStatus::Revoked, $fresh?->status);
        $this->assertNotNull($fresh?->revoked_on);
    }

    public function test_index_and_creation_via_http(): void {
        $this->actingAs($this->admin)->get(route('finance.mandates.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.mandates.create'))->assertOk();

        $this->actingAs($this->admin)->post(route('finance.mandates.store'), [
            'customer_id' => $this->customer->sqid,
            'reference' => 'MND-2027-001',
            'kind' => MandateKind::Recurring->value,
            'signed_on' => CarbonImmutable::today()->subDay()->toDateString(),
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('sepa_mandates', ['reference' => 'MND-2027-001']);
    }

    public function test_mandate_reference_is_unique_per_organization(): void {
        $this->mandate(['reference' => 'MND-DOPPELT']);

        $this->actingAs($this->admin)->post(route('finance.mandates.store'), [
            'customer_id' => $this->customer->sqid,
            'reference' => 'MND-DOPPELT',
            'kind' => MandateKind::Recurring->value,
            'signed_on' => CarbonImmutable::today()->subDay()->toDateString(),
            'iban' => 'DE89370400440532013000',
        ])->assertSessionHasErrors('reference');
    }
}
