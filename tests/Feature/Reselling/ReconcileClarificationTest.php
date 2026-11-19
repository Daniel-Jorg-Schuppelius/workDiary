<?php
/*
 * Created on   : Thu Nov 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconcileClarificationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Reselling\Register\{PeriodPlanner, ProductRelink, RecipientReconciler};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Klärfälle in der Arbeitsmappe (Phase 184, MVP-1749–1751): Prüffälle an Bezügen,
 * fremde Position mit gleichem Leistungszeitraum, Produkt neu ordnen.
 */
class ReconcileClarificationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LexofficeArticle $exchange;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-07');
        $this->exchange = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-exo', 'name' => 'Exchange Online (Plan 1)', 'article_number' => 'ART-EXO',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '3.95', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $this->customer = $this->customer('Grünweg Technik GmbH', 'c-gw');
    }

    private function customer(string $name, string $contact): Customer {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => $name]);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => $contact, 'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->getKey(),
        ]);

        return $customer;
    }

    private function invoice(string $number, string $from, string $to, string $contact = 'c-gw'): LexofficeVoucherLine {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => $contact, 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => $number, 'voucher_date' => $from, 'total_amount' => 56.41, 'currency' => 'EUR', 'archived' => false,
            'service_starts_on' => $from, 'service_ends_on' => $to, 'lines_synced_at' => now(),
        ]);

        return LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service',
            'external_article_id' => 'art-exo', 'lexoffice_article_id' => $this->exchange->id, 'name' => 'Exchange Online (Plan 1)',
            'quantity' => 12, 'unit_name' => 'Monat', 'unit_net' => '3.95', 'total_net' => '47.40', 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
    }

    /** @return list<ResalePeriod> */
    private function subscription(Customer $holder, string $startsOn, string $endsOn): array {
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'label' => 'Exchange Online (Plan 1)', 'quantity' => 1,
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'cancel', 'status' => 'ended', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
            'customer_id' => $holder->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => $startsOn, 'ends_on' => $endsOn,
        ]);
        (new PeriodPlanner)->sync($subscription);

        return $subscription->periods()->get()->all();
    }

    private function link(ResalePeriod $period, LexofficeVoucherLine $line, float $months): ResalePeriodLink {
        $link = ResalePeriodLink::query()->create([
            'organization_id' => $this->organization->id, 'period_id' => $period->id, 'subscription_id' => $period->subscription_id,
            'linkable_type' => $line->getMorphClass(), 'linkable_id' => $line->id, 'voucher_number' => $line->voucher->voucher_number,
            'voucher_date' => $line->voucher->voucher_date, 'quantity' => $months / 12, 'months' => $months, 'amount' => '47.40', 'currency' => 'EUR',
            'origin' => LinkOrigin::Manual, 'confirmed_at' => now(),
        ]);
        $period->forceFill(['status' => PeriodStatus::Billed, 'decided_at' => now()])->save();

        return $link;
    }

    public function test_workspace_lists_overbooked_and_misplaced_links_and_reorder_redistributes_them(): void {
        $admin = $this->orgAdmin();
        [$p2022, $p2023, $p2024] = $this->subscription($this->customer, '2022-10-18', '2025-10-17');
        $line2022 = $this->invoice('RE/2022/0100', '2022-10-18', '2023-10-17');
        $line2023 = $this->invoice('RE/2023/0100', '2023-10-18', '2024-10-17');
        $line2024 = $this->invoice('RE/2024/0100', '2024-10-18', '2025-10-17');
        // Altzuordnung um ein Jahr verschoben, die letzte Position überbucht; die erste Rechnung blieb frei.
        $this->link($p2022, $line2023, 12);
        $this->link($p2023, $line2024, 12);
        $this->link($p2024, $line2024, 12);
        $workspace = route('finance.resale.reconcile.show', $this->customer);

        $this->actingAs($admin)->get($workspace)->assertOk()
            ->assertSee(__('resale.account.finding.overbooked'))
            ->assertSee(__('resale.account.finding.mismatch'))
            ->assertSee(__('resale.reconcile.findings_hint'))
            ->assertSee(route('finance.resale.reconcile.reorder', $this->customer));

        $product = app(RecipientReconciler::class)->forCustomer($this->organization, $this->customer)['products'][0]['key'];
        $this->actingAs($admin)->from($workspace)->post(route('finance.resale.reconcile.reorder', $this->customer), ['product' => $product])
            ->assertRedirect($workspace . '#board')->assertSessionHasNoErrors();

        $numbers = static fn(ResalePeriod $period): array => $period->links()->pluck('voucher_number')->all();
        $this->assertSame(['RE/2022/0100'], $numbers($p2022));
        $this->assertSame(['RE/2023/0100'], $numbers($p2023));
        $this->assertSame(['RE/2024/0100'], $numbers($p2024));
        $this->assertSame([PeriodStatus::Billed, PeriodStatus::Billed, PeriodStatus::Billed], [$p2022->fresh()?->status, $p2023->fresh()?->status, $p2024->fresh()?->status]);
        $this->assertSame(3, AuditLog::query()->where('event', ProductRelink::AUDIT)->count());
        $this->actingAs($admin)->get($workspace)->assertOk()->assertDontSee(__('resale.reconcile.findings_hint'));

        // Fremdes Produkt: abgewiesen.
        $this->actingAs($admin)->from($workspace)->post(route('finance.resale.reconcile.reorder', $this->customer), ['product' => 'art:unbekannt'])->assertSessionHasErrors('product');
    }

    public function test_reorder_keeps_waived_periods(): void {
        [$p2022, $p2023] = $this->subscription($this->customer, '2022-10-18', '2024-10-17');
        $this->link($p2023, $this->invoice('RE/2023/0100', '2023-10-18', '2024-10-17'), 12);
        $p2022->forceFill(['status' => PeriodStatus::Waived, 'waived_reason' => 'Kulanz', 'decided_at' => now()])->save();

        $result = app(ProductRelink::class)->reset(ResaleSubscription::query()->with('periods.links')->get());

        $this->assertSame(['periods' => 1, 'links' => 1], $result);
        $this->assertSame(PeriodStatus::Waived, $p2022->fresh()?->status);
        $this->assertSame(PeriodStatus::Open, $p2023->fresh()?->status);
    }

    public function test_free_line_of_another_recipient_with_the_same_service_period_is_offered_for_a_single_licence(): void {
        [$period] = $this->subscription($this->customer, '2023-04-25', '2024-04-24');
        $this->customer('Seeblick Dienste', 'c-sb');
        $this->invoice('RE/2023/0545', '2023-04-25', '2024-04-24', 'c-sb');
        // Anderer Zeitraum beim selben fremden Empfänger: kein Angebot („12 Monat“ passt sonst überall).
        $this->invoice('RE/2023/0700', '2023-06-10', '2024-06-09', 'c-sb');

        $rows = app(RecipientReconciler::class)->forCustomer($this->organization, $this->customer)['periods'];

        $this->assertCount(1, $rows);
        $this->assertSame($period->id, $rows[0]['period']->id);
        $this->assertSame(['RE/2023/0545'], array_map(static fn(array $c): ?string => $c['row']['line']->voucherNumber, $rows[0]['foreign']));
    }
}
