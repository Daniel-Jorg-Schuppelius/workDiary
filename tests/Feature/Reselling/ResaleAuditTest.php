<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleAuditTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\{AuditLog, Customer, ExternalReference, LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine, User};
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Reselling\Register\{LinkProposer, PeriodPlanner};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Audit-Trail des Reselling-Registers (Review 2026-09-10, A2): Nutzer-
 * entscheidungen — Bestätigen, Verzichten, Zurücknehmen, manueller Bezug,
 * Bezug lösen, Halterwechsel — stehen mit Event und Nutzer in `audit_logs`;
 * der Vorschlagslauf (Massenlauf) schreibt keine Zeile je Bezug.
 */
class ResaleAuditTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private LexofficeArticle $premium;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
        $this->admin = $this->orgAdmin();
        $this->premium = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-bp', 'name' => 'Microsoft 365 Business Premium', 'article_number' => 'ART-BP',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '20.60', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH']);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-kl', 'referenceable_type' => $this->customer->getMorphClass(), 'referenceable_id' => $this->customer->getKey(),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium',
            'customer_id' => $this->customer->id, 'lexoffice_article_id' => $this->premium->id, 'quantity' => 1, 'starts_on' => '2025-08-05',
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '247.20',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    private function voucherLine(string $number, string $date, float $quantity = 12): LexofficeVoucherLine {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => 'c-kl', 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => $number, 'voucher_date' => $date, 'total_amount' => 100, 'currency' => 'EUR', 'archived' => false,
            'voucher_text' => '', 'lines_synced_at' => now(),
        ]);

        return LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service',
            'external_article_id' => $this->premium->external_id, 'lexoffice_article_id' => $this->premium->id,
            'name' => 'Microsoft 365 Business Premium', 'quantity' => $quantity, 'unit_name' => 'Monat',
            'unit_net' => '20.60', 'total_net' => (string) round(20.60 * $quantity, 2), 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<AuditLog> */
    private function logs(string $type): \Illuminate\Database\Eloquent\Builder {
        return AuditLog::query()->where('auditable_type', $type);
    }

    public function test_models_write_created_audits_and_the_proposal_run_stays_silent(): void {
        $subscription = $this->subscription();
        $this->assertSame(1, $this->logs(ResaleSubscription::class)->where('event', 'created')->where('auditable_id', $subscription->id)->count());
        $this->assertSame($subscription->periods()->count(), $this->logs(ResalePeriod::class)->where('event', 'created')->count(), 'geplante Perioden protokolliert');

        $this->voucherLine('RE/2025/0820', '2025-10-14');
        $this->voucherLine('RE/2026/1022', '2026-08-22');
        $before = AuditLog::query()->count();

        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(2, $result['links']);
        $this->assertSame(2, ResalePeriodLink::query()->where('origin', LinkOrigin::Proposed->value)->count());
        $this->assertSame($before, AuditLog::query()->count(), 'Vorschlagslauf schreibt keine Audit-Zeilen (weder Bezug noch Periodenstatus)');
        $this->assertSame(0, $this->logs(ResalePeriodLink::class)->count());
    }

    public function test_confirm_waive_reopen_and_link_removal_are_audited_with_user(): void {
        $subscription = $this->subscription();
        $this->voucherLine('RE/2025/0820', '2025-10-14');
        (new LinkProposer)->propose($this->organization);
        [$p2025, $p2026] = $subscription->periods()->get();

        // Bestätigen: eigenes Event am Nutzer, dazu das automatische `updated` der Periode.
        $this->actingAs($this->admin)->post(route('finance.resale.periods.confirm', $p2025->sqid))->assertRedirect();
        $confirmed = $this->logs(ResalePeriod::class)->where('auditable_id', $p2025->id)->where('event', 'resale_period.confirmed')->first();
        $this->assertNotNull($confirmed);
        $this->assertSame($this->admin->id, $confirmed->user_id);
        $this->assertSame($this->organization->id, $confirmed->organization_id);
        $this->assertSame(PeriodStatus::Billed->value, $confirmed->changes['status'] ?? null);
        $this->assertSame(['RE/2025/0820'], $confirmed->changes['vouchers'] ?? null);
        $this->assertSame(1, $this->logs(ResalePeriod::class)->where('auditable_id', $p2025->id)->where('event', 'updated')->count());

        // Verzichten und zurücknehmen.
        $this->actingAs($this->admin)->post(route('finance.resale.periods.waive', $p2026->sqid), ['decision' => 'waived', 'reason' => 'Kulanz'])->assertRedirect();
        $waived = $this->logs(ResalePeriod::class)->where('auditable_id', $p2026->id)->where('event', 'resale_period.waived')->first();
        $this->assertNotNull($waived);
        $this->assertSame('Kulanz', $waived->changes['reason'] ?? null);
        $this->assertSame($this->admin->id, $waived->user_id);

        $this->actingAs($this->admin)->post(route('finance.resale.periods.reopen', $p2026->sqid))->assertRedirect();
        $reopened = $this->logs(ResalePeriod::class)->where('auditable_id', $p2026->id)->where('event', 'resale_period.reopened')->first();
        $this->assertNotNull($reopened);
        $this->assertSame(PeriodStatus::Waived->value, $reopened->changes['status'] ?? null, 'vorheriger Zustand im Payload');
        $this->assertSame(PeriodStatus::Open->value, $reopened->changes['status_now'] ?? null);

        // Strittig ist ein eigenes Event.
        $this->actingAs($this->admin)->post(route('finance.resale.periods.waive', $p2026->sqid), ['decision' => 'disputed', 'reason' => 'Preis offen'])->assertRedirect();
        $this->assertSame(1, $this->logs(ResalePeriod::class)->where('auditable_id', $p2026->id)->where('event', 'resale_period.disputed')->count());
        $this->actingAs($this->admin)->post(route('finance.resale.periods.reopen', $p2026->sqid))->assertRedirect();

        // Manueller Bezug: Event an der Periode, `created` am Bezug; Lösen: Event an der Periode, `deleted` am Bezug.
        $line = $this->voucherLine('RE/2026/0001', '2026-08-30');
        $this->actingAs($this->admin)->post(route('finance.resale.periods.link.store', $p2026->sqid), ['line_id' => Sqid::encode(LexofficeVoucherLine::class, $line->id), 'months' => 12])
            ->assertRedirect(route('finance.resale.show', $subscription->sqid));
        $link = $p2026->links()->firstOrFail();
        $linked = $this->logs(ResalePeriod::class)->where('auditable_id', $p2026->id)->where('event', 'resale_period.linked')->first();
        $this->assertNotNull($linked);
        $this->assertSame('RE/2026/0001', $linked->changes['voucher_number'] ?? null);
        $this->assertSame($this->admin->id, $linked->user_id);
        $this->assertSame(1, $this->logs(ResalePeriodLink::class)->where('auditable_id', $link->id)->where('event', 'created')->count());

        $this->actingAs($this->admin)->delete(route('finance.resale.links.destroy', $link->sqid))->assertRedirect();
        $removed = $this->logs(ResalePeriod::class)->where('auditable_id', $p2026->id)->where('event', 'resale_period.link_removed')->first();
        $this->assertNotNull($removed);
        $this->assertSame('RE/2026/0001', $removed->changes['voucher_number'] ?? null);
        $this->assertSame(LinkOrigin::Manual->value, $removed->changes['origin'] ?? null);
        $this->assertSame(1, $this->logs(ResalePeriodLink::class)->where('auditable_id', $link->id)->where('event', 'deleted')->count());
    }

    public function test_rehome_is_audited_on_the_subscription(): void {
        $subscription = $this->subscription();
        $sister = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Sanitär GmbH']);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-ks', 'referenceable_type' => $sister->getMorphClass(), 'referenceable_id' => $sister->getKey(),
        ]);
        $period = $subscription->periods()->firstOrFail();

        $this->actingAs($this->admin)->post(route('finance.resale.reconcile.rehome', $this->customer), ['period_id' => $period->sqid, 'target_id' => $sister->sqid])
            ->assertRedirect(route('finance.resale.reconcile.show', $sister));

        $rehomed = $this->logs(ResaleSubscription::class)->where('auditable_id', $subscription->id)->where('event', 'resale_subscription.rehomed')->first();
        $this->assertNotNull($rehomed);
        $this->assertSame($this->admin->id, $rehomed->user_id);
        $this->assertSame($this->customer->id, $rehomed->changes['from']['customer_id'] ?? null);
        $this->assertSame($sister->id, $rehomed->changes['to']['customer_id'] ?? null);
        // Der anschließende Vorschlagslauf bleibt stumm.
        $this->assertSame(0, $this->logs(ResalePeriodLink::class)->count());
    }
}
