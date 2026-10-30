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

use App\Enums\Reselling\{PeriodStatus, SubscriptionProvider};
use App\Models\Customer\Customer;
use App\Models\Domain\{DomainAccountingEntry, DomainProjection, DomainProviderConnection, DomainResellerAccount};
use App\Models\Finance\AccountingVoucher;
use App\Models\Reselling\{ResalePeriod, ResalePriceEntry, ResaleSubscription};
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

    private Customer $nordlicht;

    private DomainProviderConnection $connection;

    private DomainResellerAccount $reseller;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-10-29');
        $this->nordlicht = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Nordlicht Systems GmbH']);
        $this->connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $this->reseller = DomainResellerAccount::factory()->create([
            'organization_id' => $this->organization->id, 'connection_id' => $this->connection->id, 'external_user' => 'nordlicht-systems',
            'customer_id' => $this->nordlicht->id, 'provider_customer_number' => '90021',
        ]);
    }

    private function domain(string $name): DomainProjection {
        return DomainProjection::factory()->create([
            'organization_id' => $this->organization->id, 'connection_id' => $this->connection->id, 'external_domain' => $name,
            'domain_hash' => DomainProjection::hashFor($name), 'external_user' => 'nordlicht-systems', 'customer_id' => $this->nordlicht->id,
        ]);
    }

    private function subscription(string $domain, string $startsOn): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'domain', 'provider' => 'domainreselling', 'external_id' => $domain, 'label' => $domain,
            'customer_id' => $this->nordlicht->id, 'quantity' => 1, 'starts_on' => $startsOn, 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'status' => 'active', 'currency' => 'EUR',
        ]);
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    /** @param  array<string, mixed>  $attributes */
    private function entry(string $date, string $type, string $description, string $price, array $attributes = []): DomainAccountingEntry {
        return DomainAccountingEntry::query()->create($attributes + [
            'organization_id' => $this->organization->id, 'connection_id' => $this->connection->id, 'external_user' => 'nordlicht-systems',
            'reseller_account_id' => $this->reseller->id, 'customer_id' => $this->nordlicht->id, 'entry_date' => $date, 'type' => $type,
            'description' => $description, 'invoice_period' => '1Y', 'quantity' => '1', 'net_amount' => $price, 'currency' => 'EUR',
            'raw_hash' => hash('sha256', $date . $type . $description . json_encode($attributes)), 'synced_at' => now(),
        ]);
    }

    public function test_monthly_portal_invoices_from_the_journal_cover_the_domain_periods(): void {
        $domain = $this->domain('bewegung-beispiel.de');
        $subscription = $this->subscription('bewegung-beispiel.de', '2024-10-27');
        $this->entry('2025-10-27 03:00:00', 'RENEW_DOMAIN_ANNUAL', 'bewegung-beispiel.de', '-5.36', ['domain_projection_id' => $domain->id]);
        $this->entry('2026-10-27 03:00:00', 'RENEW_DOMAIN_ANNUAL', 'bewegung-beispiel.de', '-5.36', ['domain_projection_id' => $domain->id]);
        // Gebühr ohne Domain und Buchung eines Subusers ohne Kunden zählen nicht.
        $this->entry('2026-10-28 03:03:00', 'MONTHLY_FEE', 'monthly fee (4 zones)', '-0.18', ['domain_projection_id' => null]);
        $other = DomainResellerAccount::factory()->create(['organization_id' => $this->organization->id, 'connection_id' => $this->connection->id, 'external_user' => 'nebenhof', 'customer_id' => null]);
        $this->entry('2026-10-27 04:00:00', 'RENEW_DOMAIN_ANNUAL', 'bewegung-beispiel.de', '-5.36', ['domain_projection_id' => $domain->id, 'reseller_account_id' => $other->id, 'external_user' => 'nebenhof', 'customer_id' => null]);

        $result = (new LinkProposer)->propose($this->organization);

        $periods = $subscription->periods()->orderBy('starts_on')->get();
        $this->assertSame([PeriodStatus::Open, PeriodStatus::Billed, PeriodStatus::Billed], $periods->pluck('status')->all(), '2024 vor dem Journal, 2025 und 2026 aus den Monatsrechnungen');
        $this->assertSame(['90021-10-2025', '90021-10-2026'], $periods->slice(1)->map(static fn(ResalePeriod $p) => $p->links()->first()?->voucher_number)->values()->all());
        $this->assertSame('12.00', $periods->get(1)?->links()->first()?->months);
        $this->assertSame(2, $result['linked']);
        $this->assertSame(0, $result['lines_without_subscription']);
        $this->assertSame(2, $result['auto_confirmed'], 'Domainname passt, Buchung dicht am Periodenbeginn — sichere Treffer');

        // Beleg-Dialog aus der Periode: Domain als Position, Typ übersetzt, Quelle benannt, Artikelvergleich grün.
        $link = $periods->get(2)?->links()->first();
        $this->assertNotNull($link);
        $page = $this->actingAs($this->orgAdmin())->get(route('finance.resale.vouchers.show', ['source' => 'domain-accounting', 'voucher' => $this->reseller->id . ':2026-10', 'period' => $periods->get(2)?->sqid, 'line' => $link->linkable_id]))->assertOk();
        $page->assertSee('bewegung-beispiel.de')
            ->assertSee(__('domain.accounting.types.RENEW_DOMAIN_ANNUAL'))
            ->assertDontSee('RENEW_DOMAIN_ANNUAL')
            ->assertSee(__('resale.mirror.source_domain-accounting'))
            ->assertSee(__('resale.voucher.compare.article_ok'))
            ->assertSee('90021-10-2026');
    }

    public function test_source_groups_entries_to_monthly_vouchers_and_resolves_keys(): void {
        $domain = $this->domain('bewegung-beispiel.de');
        $second = $this->domain('wochenbuecher-beispiel.de');
        $renew = $this->entry('2026-10-27 03:00:00', 'RENEW', 'bewegung-beispiel.de', '-5.36', ['domain_projection_id' => $domain->id]);
        $this->entry('2026-10-25 08:30:00', 'RENEW', 'wochenbuecher-beispiel.de', '-5.36', ['domain_projection_id' => $second->id, 'invoice_period' => '2Y']);
        $this->entry('2026-09-02 08:30:00', 'RENEW', 'wochenbuecher-beispiel.de', '-5.36', ['domain_projection_id' => $second->id, 'raw_hash' => str_repeat('a', 64)]);

        $source = new DomainAccountingMirrorSource;
        $vouchers = $source->vouchersFor($this->organization, [$this->nordlicht->id], CarbonImmutable::parse('2026-01-01'));
        $this->assertSame(['90021-10-2026', '90021-09-2026'], $vouchers->map(static fn($v) => $v->voucherNumber)->all(), 'ein Beleg je Monat, neuester zuerst');
        $october = $vouchers->firstOrFail();
        $this->assertCount(2, $october->lines);
        $this->assertSame('2026-10-31', $october->voucherDate?->format('Y-m-d'));
        $this->assertSame('2028-10-24', $october->serviceTo?->format('Y-m-d'), 'zwei Jahre Laufzeit aus INVOICE_PERIOD');
        $this->assertSame($this->nordlicht->id, $october->recipientCustomerId);

        $byKey = $source->voucherByKey($this->organization, $this->reseller->id . ':2026-10');
        $this->assertNotNull($byKey);
        $this->assertSame('90021-10-2026', $byKey->voucherNumber);
        $line = $byKey->lines[1];
        $this->assertSame('bewegung-beispiel.de', $line->name);
        $this->assertSame($renew->id, $source->lineByKey($this->organization, $line->key)?->morphId);
        $this->assertSame('5.36', $line->totalNet->getAmount());
        $this->assertTrue(app(InvoiceMirror::class)->linesMentioningDomains($this->organization, [$this->nordlicht->id], ['bewegung-beispiel.de'])->isNotEmpty(), 'die Quelle ist registriert');
        $this->assertTrue($source->linesFor($this->organization, [$this->nordlicht->id])->isEmpty(), 'keine Lizenzpositionen');
    }

    public function test_registrar_invoice_numbers_do_not_split_the_monthly_voucher(): void {
        // „accounting invoice id“ ist die Registrar-Rechnung an den Inhaber (AR2026…), nicht die des Portals an den Subuser.
        $domain = $this->domain('bewegung-beispiel.de');
        $this->entry('2026-10-27 03:00:00', 'RENEW_DOMAIN_ANNUAL', 'bewegung-beispiel.de', '-5.36', ['domain_projection_id' => $domain->id, 'invoice_number' => 'AR20261031AD012500', 'invoice_period' => null]);
        $this->entry('2026-10-05 03:00:00', 'RENEW_DOMAIN_ANNUAL', 'bewegung-beispiel.de', '-5.36', ['domain_projection_id' => $domain->id, 'invoice_period' => null, 'raw_hash' => str_repeat('b', 64)]);

        $source = new DomainAccountingMirrorSource;
        $vouchers = $source->vouchersFor($this->organization, [$this->nordlicht->id], CarbonImmutable::parse('2026-01-01'));
        $this->assertSame(['90021-10-2026'], $vouchers->map(static fn($v) => $v->voucherNumber)->all(), 'ein Monatsbeleg, Nummer aus Kundennummer und Monat');
        $lines = $vouchers->firstOrFail()->lines;
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('AR20261031AD012500', (string) $lines[1]->description, 'die Registrar-Rechnung steht als Hinweis an der Zeile');
        $this->assertSame(1.0, $lines[0]->quantity, 'ohne Laufzeit zählt die Menge: ein Jahr');
    }

    public function test_provider_customer_number_is_saved_from_the_reseller_page(): void {
        $admin = $this->orgAdmin();
        $this->actingAs($admin)->get(route('domain-reseller.show', $this->reseller))->assertOk()->assertSee(__('domain.reseller.provider_customer_number'));
        $this->actingAs($admin)->post(route('domain-reseller.provider-number', $this->reseller), ['provider_customer_number' => '90055'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('90055', $this->reseller->refresh()->provider_customer_number);
        $this->actingAs($admin)->post(route('domain-reseller.provider-number', $this->reseller), ['provider_customer_number' => 'nicht erlaubt'])->assertSessionHasErrors('provider_customer_number');
        $this->actingAs($admin)->post(route('domain-reseller.provider-number', $this->reseller), ['provider_customer_number' => ''])->assertRedirect();
        $this->assertNull($this->reseller->refresh()->provider_customer_number);
    }

    public function test_lines_carry_the_class_price_and_the_booked_monthly_invoice_adds_a_rest_position(): void {
        // Entscheidung 2026-10-30: das Journal nennt den Einkauf (4,50), der Subuser zahlt den Preis seiner Klasse (8,10);
        // die gebuchte Monatsrechnung (8,94) enthält darüber hinaus Posten ohne Domain → Restposition nur in Belegansichten.
        $this->reseller->forceFill(['user_class' => 'STANDARD'])->save();
        ResalePriceEntry::query()->create([
            'organization_id' => $this->organization->id, 'provider' => SubscriptionProvider::DomainReselling, 'product' => '.de', 'price_class' => 'STANDARD', 'term_months' => 12, 'interval' => 'yearly',
            'valid_from' => '2025-01-01', 'purchase_unit_price' => '4.50', 'list_unit_price' => '8.10', 'currency' => 'EUR',
        ]);
        ResalePriceEntry::query()->create([
            'organization_id' => $this->organization->id, 'provider' => SubscriptionProvider::DomainReselling, 'product' => '.de', 'price_class' => 'STANDARD', 'term_months' => 12, 'interval' => 'yearly',
            'valid_from' => '2026-11-01', 'purchase_unit_price' => '4.50', 'list_unit_price' => '9.99', 'currency' => 'EUR',
        ]);
        $domain = $this->domain('bewegung-beispiel.de');
        $this->entry('2026-10-27 03:00:00', 'RENEW_DOMAIN_ANNUAL', 'bewegung-beispiel.de', '-4.50', ['domain_projection_id' => $domain->id]);
        AccountingVoucher::factory()->create([
            'organization_id' => $this->organization->id, 'customer_id' => $this->nordlicht->id, 'voucher_type' => 'salesinvoice', 'voucher_number' => '90021-10-2026',
            'voucher_date' => '2026-10-31', 'net_amount' => '8.94', 'total_amount' => '10.64', 'is_cancellation' => false,
        ]);

        $source = new DomainAccountingMirrorSource;
        $voucher = $source->vouchersFor($this->organization, [$this->nordlicht->id], CarbonImmutable::parse('2026-01-01'))->firstOrFail();
        $this->assertCount(2, $voucher->lines, 'Domainposition plus Restposition');
        [$line, $rest] = $voucher->lines;
        $this->assertSame('bewegung-beispiel.de', $line->name);
        $this->assertSame('8.10', $line->totalNet->getAmount(), 'Klassenpreis am Buchungstag, nicht der Einkauf und nicht die Zeile ab November');
        $this->assertStringContainsString(__('resale.mirror.domain_price_class', ['class' => 'STANDARD']), (string) $line->description);
        $this->assertSame(__('resale.mirror.domain_rest'), $rest->name);
        $this->assertSame('0.84', $rest->totalNet->getAmount(), 'gebuchte Rechnung minus Domainpositionen');
        $this->assertNull($rest->serviceFrom, 'die Restposition deckt keine Periode');
        $this->assertFalse($rest->articleIsLicence);

        $byKey = $source->voucherByKey($this->organization, $this->reseller->id . ':2026-10');
        $this->assertCount(2, $byKey?->lines ?? []);
        $this->assertCount(1, $source->linesFor($this->organization, [$this->nordlicht->id], licenceOnly: false), 'für Vorschläge und Bezüge nur die Domainpositionen');
        $this->assertSame('8.10', $source->linesByIds($this->organization, [$line->morphId])->first()?->totalNet->getAmount());

        // Ohne gebuchte Rechnung keine Restposition; ohne Klassenzeile der Journalbetrag.
        AccountingVoucher::query()->delete();
        $this->reseller->forceFill(['user_class' => 'RESELLER'])->save();
        $plain = $source->voucherByKey($this->organization, $this->reseller->id . ':2026-10');
        $this->assertCount(1, $plain?->lines ?? []);
        $this->assertSame('4.50', $plain?->lines[0]->totalNet->getAmount());
    }
}
