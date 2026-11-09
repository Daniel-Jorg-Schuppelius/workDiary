<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleHolderDeletionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Exceptions\PartyDeletionBlockedException;
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Platform\User;
use App\Models\Reselling\ResaleSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Ein Halter mit Abos bleibt: Löschen nähme den Abos still die Zuordnung (Phase 175). */
class ResaleHolderDeletionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $holder */
    private function subscription(array $holder): ResaleSubscription {
        return ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'manual', 'label' => 'Exchange Online (Plan 1)',
            'quantity' => 1, 'starts_on' => '2025-08-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'currency' => 'EUR', 'status' => 'ended',
        ], $holder));
    }

    public function test_customer_with_a_subscription_is_not_deleted(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $subscription = $this->subscription(['customer_id' => $customer->id]);

        $this->actingAs($this->admin)->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.show', $customer))
            ->assertSessionHas('error');
        $this->assertSame($customer->id, $subscription->fresh()->customer_id);

        $this->expectException(PartyDeletionBlockedException::class);
        $customer->delete();
    }

    public function test_foreign_customer_with_a_subscription_is_not_deleted(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);
        $subscription = $this->subscription(['foreign_customer_id' => $foreign->id]);

        $this->actingAs($this->admin)->delete(route('foreign-customers.destroy', $foreign))
            ->assertRedirect(route('foreign-customers.show', $foreign))
            ->assertSessionHas('error');
        $this->assertSame($foreign->id, $subscription->fresh()->foreign_customer_id);
    }

    public function test_foreign_customer_without_subscriptions_is_still_deleted(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);

        $this->actingAs($this->admin)->delete(route('foreign-customers.destroy', $foreign))->assertRedirect(route('customers.show', $customer));
        $this->assertDatabaseMissing('foreign_customers', ['id' => $foreign->id]);
    }
}
