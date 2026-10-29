<?php
/*
 * Created on   : Thu Oct 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainAccountingMirrorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\PeriodStatus;
use App\Models\Customer\Customer;
use App\Models\Domain\{DomainAccountingEntry, DomainProjection, DomainProviderConnection, DomainResellerAccount};
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Services\Reselling\Mirror\{DomainAccountingMirrorSource, InvoiceMirror};
use App\Services\Reselling\Register\{LinkProposer, PeriodPlanner};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Buchungsjournal des Domainanbieters als Belegquelle (Feature 152, Befund 2026-10-29):
 * Monatsrechnungen des Portals an Subreseller decken deren Domain-Perioden.
 */
class DomainAccountingMirrorTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $lds;

    private DomainProviderConnection $connection;

    private DomainResellerAccount $reseller;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-10-29');
        $this->lds = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'LDS Systems GmbH']);
        $this->connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $this->reseller = DomainResellerAccount::factory()->create([
            'organization_id' => $this->organization->id, 'connection_id' => $this->connection->id, 'external_user' => 'lds-systems',
            'customer_id' => $this->lds->id, 'provider_customer_number' => '10021',
        ]);
    }

    private function domain(string $name): DomainProjection {
        return DomainProjection::factory()->create([
            'organization_id' => $this->organization->id, 'connection_id' => $this->connection->id, 'external_domain' => $name,
            'domain_hash' => DomainProjection::hashFor($name), 'external_user' => 'lds-systems', 'customer_id' => $this->lds->id,
        ]);
    }

    private function subscription(string $domain, string $startsOn): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'domain', 'provider' => 'domainreselling', 'external_id' => $domain, 'label' => $domain,
            'customer_id' => $this->lds->id, 'quantity' => 1, 'starts_on' => $startsOn, 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'status' => 'active', 'currency' => 'EUR',
        ]);
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    /** @param  array<string, mixed>  $attributes */
    private function entry(string $date, string $type, string $description, string $price, array $attributes = []): DomainAccountingEntry {
        return DomainAccountingEntry::query()->create($attributes + [
            'organization_id' => $this->organization->id, 'connection_id' => $this->connection->id, 'external_user' => 'lds-systems',
            'reseller_account_id' => $this->reseller->id, 'customer_id' => $this->lds->id, 'entry_date' => $date, 'type' => $type,
            'description' => $description, 'invoice_period' => '1Y', 'quantity' => '1', 'net_amount' => $price, 'currency' => 'EUR',
            'raw_hash' => hash('sha256', $date . $type . $description . json_encode($attributes)), 'synced_at' => now(),
        ]);
    }

    public function test_monthly_portal_invoices_from_the_journal_cover_the_domain_periods(): void {
        $domain = $this->domain('antjebewegt.de');
        $subscription = $this->subscription('antjebewegt.de', '2024-10-27');
        $this->entry('2025-10-27 03:00:00', 'RENEW', 'antjebewegt.de', '-5.36', ['domain_projection_id' => $domain->id]);
        $this->entry('2026-10-27 03:00:00', 'RENEW', 'antjebewegt.de', '-5.36', ['domain_projection_id' => $domain->id]);
        // Gebühr ohne Domain und Buchung eines Subusers ohne Kunden zählen nicht.
        $this->entry('2026-10-28 03:03:00', 'MONTHLY_FEE', 'monthly fee (4 zones)', '-0.18', ['domain_projection_id' => null]);
        $other = DomainResellerAccount::factory()->create(['organization_id' => $this->organization->id, 'connection_id' => $this->connection->id, 'external_user' => 'klimmg', 'customer_id' => null]);
        $this->entry('2026-10-27 04:00:00', 'RENEW', 'antjebewegt.de', '-5.36', ['domain_projection_id' => $domain->id, 'reseller_account_id' => $other->id, 'external_user' => 'klimmg', 'customer_id' => null]);

        $result = (new LinkProposer)->propose($this->organization);

        $periods = $subscription->periods()->orderBy('starts_on')->get();
        $this->assertSame([PeriodStatus::Open, PeriodStatus::Billed, PeriodStatus::Billed], $periods->pluck('status')->all(), '2024 vor dem Journal, 2025 und 2026 aus den Monatsrechnungen');
        $this->assertSame(['10021-10-2025', '10021-10-2026'], $periods->slice(1)->map(static fn(ResalePeriod $p) => $p->links()->first()?->voucher_number)->values()->all());
        $this->assertSame('12.00', $periods->get(1)?->links()->first()?->months);
        $this->assertSame(2, $result['linked']);
        $this->assertSame(0, $result['lines_without_subscription']);
    }

    public function test_source_groups_entries_to_monthly_vouchers_and_resolves_keys(): void {
        $domain = $this->domain('antjebewegt.de');
        $second = $this->domain('sonntagsbuecher.de');
        $renew = $this->entry('2026-10-27 03:00:00', 'RENEW', 'antjebewegt.de', '-5.36', ['domain_projection_id' => $domain->id]);
        $this->entry('2026-10-25 08:30:00', 'RENEW', 'sonntagsbuecher.de', '-5.36', ['domain_projection_id' => $second->id, 'invoice_period' => '2Y']);
        $this->entry('2026-09-02 08:30:00', 'RENEW', 'sonntagsbuecher.de', '-5.36', ['domain_projection_id' => $second->id, 'raw_hash' => str_repeat('a', 64)]);

        $source = new DomainAccountingMirrorSource;
        $vouchers = $source->vouchersFor($this->organization, [$this->lds->id], CarbonImmutable::parse('2026-01-01'));
        $this->assertSame(['10021-10-2026', '10021-09-2026'], $vouchers->map(static fn($v) => $v->voucherNumber)->all(), 'ein Beleg je Monat, neuester zuerst');
        $october = $vouchers->firstOrFail();
        $this->assertCount(2, $october->lines);
        $this->assertSame('2026-10-31', $october->voucherDate?->format('Y-m-d'));
        $this->assertSame('2028-10-24', $october->serviceTo?->format('Y-m-d'), 'zwei Jahre Laufzeit aus INVOICE_PERIOD');
        $this->assertSame($this->lds->id, $october->recipientCustomerId);

        $byKey = $source->voucherByKey($this->organization, $this->reseller->id . ':2026-10');
        $this->assertNotNull($byKey);
        $this->assertSame('10021-10-2026', $byKey->voucherNumber);
        $line = $byKey->lines[1];
        $this->assertSame('antjebewegt.de', $line->name);
        $this->assertSame($renew->id, $source->lineByKey($this->organization, $line->key)?->morphId);
        $this->assertSame('5.36', $line->totalNet->getAmount());
        $this->assertTrue(app(InvoiceMirror::class)->linesMentioningDomains($this->organization, [$this->lds->id], ['antjebewegt.de'])->isNotEmpty(), 'die Quelle ist registriert');
        $this->assertTrue($source->linesFor($this->organization, [$this->lds->id])->isEmpty(), 'keine Lizenzpositionen');
    }

    public function test_provider_customer_number_is_saved_from_the_reseller_page(): void {
        $admin = $this->orgAdmin();
        $this->actingAs($admin)->get(route('domain-reseller.show', $this->reseller))->assertOk()->assertSee(__('domain.reseller.provider_customer_number'));
        $this->actingAs($admin)->post(route('domain-reseller.provider-number', $this->reseller), ['provider_customer_number' => '10055'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('10055', $this->reseller->refresh()->provider_customer_number);
        $this->actingAs($admin)->post(route('domain-reseller.provider-number', $this->reseller), ['provider_customer_number' => 'nicht erlaubt'])->assertSessionHasErrors('provider_customer_number');
        $this->actingAs($admin)->post(route('domain-reseller.provider-number', $this->reseller), ['provider_customer_number' => ''])->assertRedirect();
        $this->assertNull($this->reseller->refresh()->provider_customer_number);
    }
}
