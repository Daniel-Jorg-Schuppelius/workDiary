<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleSubscriptionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Reselling\{PeriodStatus, SubscriptionStatus};
use App\Enums\User\Permission;
use App\Models\{Article, Customer, ForeignCustomer, Organization};
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Reselling-Register in der Oberfläche (Feature 152, MVP-758): Rechte,
 * Halterwahl, Periodenplanung beim Speichern, Org-Grenze, Kundenakte.
 */
class ResaleSubscriptionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array {
        return array_merge([
            'label' => 'Microsoft 365 Business Premium',
            'kind' => 'license',
            'provider' => 'qualityhosting',
            'external_id' => 'QH-4711',
            'holder' => 'none',
            'quantity' => 2,
            'starts_on' => '2025-08-05',
            'term_months' => 12,
            'interval' => 'yearly',
            'renewal' => 'auto',
            'purchase_unit_price' => '187.92',
            'sale_unit_price' => '247.20',
            'status' => 'active',
        ], $overrides);
    }

    public function test_index_requires_view_permission_and_manage_for_dialog(): void {
        $this->actingAs($this->orgUser())->get(route('finance.resale.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('buchhaltung'))->get(route('finance.resale.index'))
            ->assertOk()
            ->assertSee(__('resale.title.index'))
            ->assertSee(__('resale.empty.subscriptions'));
        $this->actingAs($this->orgAdmin())->get(route('finance.resale.create'))->assertOk()->assertSee(__('resale.dialog.title_new'));
    }

    public function test_store_creates_subscription_with_customer_and_plans_periods(): void {
        $admin = $this->orgAdmin();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH']);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Microsoft 365 Business Premium', 'sellable' => true]);

        $this->travelTo('2026-09-04');
        $response = $this->actingAs($admin)->post(route('finance.resale.store'), $this->payload([
            'holder' => 'customer',
            'customer_id' => $customer->sqid,
            'article_id' => $article->sqid,
        ]));

        $subscription = ResaleSubscription::query()->firstOrFail();
        $response->assertRedirect(route('finance.resale.show', $subscription->sqid));
        $this->assertSame($customer->id, $subscription->customer_id);
        $this->assertNull($subscription->foreign_customer_id);
        $this->assertSame($article->id, $subscription->article_id);
        $this->assertSame('247.2000', $subscription->sale_unit_price?->getAmount());
        $this->assertSame(['2025-08-05', '2026-08-05'], $subscription->periods->map(static fn(ResalePeriod $p): string => $p->starts_on->toDateString())->all());
        $this->assertSame('494.40', $subscription->periods->first()?->expected_sale?->getAmount());
        $this->assertSame($customer->id, $subscription->billedTo()?->id);

        $this->actingAs($admin)->get(route('finance.resale.show', $subscription->sqid))
            ->assertOk()
            ->assertSee('Klimpel Bäder GmbH')
            ->assertSee('05.08.2025 – 04.08.2026');
        $this->actingAs($admin)->get(route('finance.resale.index', ['open' => 1]))->assertOk()->assertSee('Microsoft 365 Business Premium');
    }

    public function test_foreign_customer_holder_bills_the_partner(): void {
        $admin = $this->orgAdmin();
        $partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'LDS Systems GmbH']);
        $end = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Steuerbüro Kaik']);

        $this->actingAs($admin)->post(route('finance.resale.store'), $this->payload([
            'holder' => 'foreign',
            'foreign_customer_id' => $end->sqid,
            'customer_id' => $partner->sqid, // wird bei Fremdkunden-Halter ignoriert
        ]));

        $subscription = ResaleSubscription::query()->firstOrFail();
        $this->assertSame($end->id, $subscription->foreign_customer_id);
        $this->assertNull($subscription->customer_id, 'genau ein Halter');
        $this->assertSame($partner->id, $subscription->billedTo()?->id);
        $this->assertSame('Steuerbüro Kaik', $subscription->holderLabel());

        // Kundenakte des Partners zeigt das Abo des Endkunden.
        $this->actingAs($admin)->get(route('customers.show', $partner))->assertOk()->assertSee('Steuerbüro Kaik')->assertSee(__('resale.title.menu'));
        $this->actingAs($admin)->get(route('finance.resale.index', ['customer' => $partner->sqid]))->assertOk()->assertSee('Steuerbüro Kaik');
    }

    public function test_holder_choice_requires_the_matching_id_and_cancel_needs_an_end(): void {
        $admin = $this->orgAdmin();
        $this->actingAs($admin)->from(route('finance.resale.index'))->post(route('finance.resale.store'), $this->payload(['holder' => 'customer']))
            ->assertSessionHasErrors('customer_id');
        $this->actingAs($admin)->from(route('finance.resale.index'))->post(route('finance.resale.store'), $this->payload(['holder' => 'foreign']))
            ->assertSessionHasErrors('foreign_customer_id');
        $this->actingAs($admin)->from(route('finance.resale.index'))->post(route('finance.resale.store'), $this->payload(['renewal' => 'cancel']))
            ->assertSessionHasErrors('ends_on');
        $this->assertSame(0, ResaleSubscription::query()->count());
    }

    public function test_update_replans_open_periods_and_delete_is_blocked_by_decisions(): void {
        $admin = $this->orgAdmin();
        $this->travelTo('2026-09-04');
        $this->actingAs($admin)->post(route('finance.resale.store'), $this->payload(['holder' => 'own']));
        $subscription = ResaleSubscription::query()->firstOrFail();
        $this->assertTrue($subscription->is_own_holding);
        $this->assertCount(2, $subscription->periods);

        $this->actingAs($admin)->put(route('finance.resale.update', $subscription->sqid), $this->payload(['holder' => 'own', 'ends_on' => '2026-08-04', 'renewal' => 'cancel', 'status' => 'cancelled']))
            ->assertRedirect(route('finance.resale.show', $subscription->sqid));
        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status);
        $this->assertCount(1, $subscription->periods, '2026er-Periode entfällt mit dem Ende');

        $subscription->periods()->update(['status' => PeriodStatus::Waived->value, 'waived_reason' => 'Kulanz']);
        $this->actingAs($admin)->delete(route('finance.resale.destroy', $subscription->sqid))
            ->assertRedirect(route('finance.resale.show', $subscription->sqid))
            ->assertSessionHas('error');
        $this->assertSame(1, ResaleSubscription::query()->count());

        $subscription->periods()->update(['status' => PeriodStatus::Open->value]);
        $this->actingAs($admin)->delete(route('finance.resale.destroy', $subscription->sqid))->assertRedirect(route('finance.resale.index'));
        $this->assertSame(0, ResaleSubscription::query()->count());
        $this->assertSame(0, ResalePeriod::query()->count());
    }

    public function test_subscriptions_are_organization_scoped(): void {
        $other = Organization::factory()->create();
        $foreign = ResaleSubscription::query()->create([
            'organization_id' => $other->id,
            'kind' => 'domain',
            'provider' => 'manual',
            'label' => 'fremde-domain.de',
            'quantity' => 1,
            'starts_on' => '2026-01-01',
            'term_months' => 12,
            'interval' => 'yearly',
            'renewal' => 'auto',
            'status' => 'active',
        ]);

        $admin = $this->orgAdmin();
        $this->actingAs($admin)->get(route('finance.resale.index'))->assertOk()->assertDontSee('fremde-domain.de');
        $this->actingAs($admin)->get(route('finance.resale.show', $foreign->sqid))->assertNotFound();
        // Fremder Kunde als Halter: org-gescopte Existenzregel lehnt ab.
        $otherCustomer = Customer::factory()->create(['organization_id' => $other->id]);
        $this->actingAs($admin)->from(route('finance.resale.index'))->post(route('finance.resale.store'), $this->payload(['holder' => 'customer', 'customer_id' => $otherCustomer->sqid]))
            ->assertSessionHasErrors('customer_id');
    }

    public function test_free_plan_is_gated_by_the_reselling_module(): void {
        $this->organization->forceFill(['plan' => Organization::PLAN_FREE])->save();
        $this->actingAs($this->orgAdmin())->get(route('finance.resale.index'))->assertStatus(423);
    }

    public function test_sync_command_rolls_periods_forward(): void {
        $this->travelTo('2026-09-04');
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id,
            'kind' => 'license',
            'provider' => 'manual',
            'label' => 'Exchange Online (Plan 1)',
            'quantity' => 9,
            'starts_on' => '2022-10-13',
            'term_months' => 12,
            'interval' => 'yearly',
            'renewal' => 'auto',
            'status' => 'active',
        ]);
        $this->assertSame(0, $subscription->periods()->count());

        $this->artisan('resale:sync-periods')->assertSuccessful();
        $this->assertSame(5, $subscription->periods()->count(), '2022–2026, der 13.10.2026 liegt innerhalb der 90 Tage');

        $this->travelTo('2027-09-04');
        $this->artisan('resale:sync-periods')->assertSuccessful();
        $this->assertSame(6, $subscription->periods()->count());
    }

    public function test_permission_labels_exist(): void {
        $this->assertNotSame('reselling.view', __('access.permission.' . Permission::ResellingView->value));
        $this->assertNotSame('reselling.manage', __('access.permission.' . Permission::ResellingManage->value));
    }
}
