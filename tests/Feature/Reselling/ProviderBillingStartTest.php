<?php
/*
 * Created on   : Wed Nov 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProviderBillingStartTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\PeriodStatus;
use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Domain\{DomainProjection, DomainProviderConnection, DomainResellerAccount};
use App\Models\Platform\User;
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Services\Reselling\Register\{PeriodPlanner, ProviderBillingStart};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Abrechnungsbeginn des Portalkontos (MVP-1746): offene Lizenzjahre, die vor
 * dem Beginn des Unterkontos begannen, gesammelt abschließen.
 */
class ProviderBillingStartTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $partner;

    private DomainProviderConnection $connection;

    private DomainResellerAccount $account;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2018-03-01');
        $this->partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Nordlicht Systems GmbH']);
        $this->connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $this->account = DomainResellerAccount::factory()->create([
            'organization_id' => $this->organization->id, 'connection_id' => $this->connection->id, 'external_user' => 'nordlicht-systems',
            'customer_id' => $this->partner->id, 'depth' => 1, 'provider_created_at' => '2016-08-10 09:00:00',
        ]);
    }

    private function domain(string $name, string $startsOn, ?DomainResellerAccount $account = null): ResaleSubscription {
        $projection = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id, 'connection_id' => $this->connection->id, 'external_domain' => $name,
            'domain_hash' => DomainProjection::hashFor($name), 'external_user' => 'nordlicht-systems', 'customer_id' => $this->partner->id,
            'reseller_account_id' => ($account ?? $this->account)->id,
        ]);
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'domain', 'provider' => 'domainreselling', 'external_id' => $name, 'label' => $name,
            'customer_id' => $this->partner->id, 'quantity' => 1, 'starts_on' => $startsOn, 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '9.00', 'domain_projection_id' => $projection->id,
        ]);
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    /** @return list<string> */
    private function statuses(ResaleSubscription $subscription): array {
        return $subscription->periods()->orderBy('starts_on')->get()->map(static fn(ResalePeriod $p): string => $p->starts_on->toDateString() . ':' . $p->status->value)->all();
    }

    public function test_open_license_years_before_the_account_start_are_closed_in_one_step(): void {
        $admin = $this->orgAdmin();
        $early = $this->domain('fruehstart-beispiel.de', '2015-01-10');
        $late = $this->domain('spaetstart-beispiel.de', '2016-09-01');
        // Eigenes Konto (kein Subuser): der Reseller berechnet selbst, kein Kontobeginn.
        $own = DomainResellerAccount::factory()->create(['organization_id' => $this->organization->id, 'connection_id' => $this->connection->id, 'external_user' => 'haupt', 'depth' => 0, 'customer_id' => null, 'provider_created_at' => '2016-08-10 09:00:00']);
        $direct = $this->domain('direkt-beispiel.de', '2015-02-01', $own);

        $groups = app(ProviderBillingStart::class)->candidates($this->partner);
        $this->assertCount(1, $groups);
        $this->assertSame('nordlicht-systems', $groups[0]['account']);
        $this->assertSame('2016-08-10', $groups[0]['since']->toDateString());
        $this->assertSame(['2015-01-10', '2016-01-10'], array_map(static fn(ResalePeriod $p): string => $p->starts_on->toDateString(), $groups[0]['periods']));

        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $this->partner))->assertOk()
            ->assertSee(__('resale.billing_start.title', ['account' => 'nordlicht-systems', 'date' => '10.08.2016']))
            ->assertSee(route('finance.resale.reconcile.billing-start.create', $this->partner), false);
        $this->actingAs($admin)->get(route('finance.resale.reconcile.billing-start.create', $this->partner))->assertOk()
            ->assertSee('fruehstart-beispiel.de')->assertDontSee('spaetstart-beispiel.de')->assertDontSee('direkt-beispiel.de');

        $this->actingAs($admin)->post(route('finance.resale.reconcile.billing-start.store', $this->partner))
            ->assertRedirect(route('finance.resale.reconcile.show', $this->partner))
            ->assertSessionHas('success', trans_choice('resale.billing_start.flash', 2, ['count' => 2]));

        $this->assertSame(['2015-01-10:waived', '2016-01-10:waived', '2017-01-10:open', '2018-01-10:open'], $this->statuses($early));
        $this->assertSame(['2016-09-01:open', '2017-09-01:open'], $this->statuses($late), 'nach dem Kontobeginn begonnen: bleibt offen');
        $this->assertNotContains('waived', array_map(static fn(string $s): string => explode(':', $s)[1], $this->statuses($direct)));
        $closed = $early->periods()->orderBy('starts_on')->firstOrFail();
        $this->assertTrue($closed->isBeforeBillingStart());
        $this->assertSame(__('resale.billing_start.label'), $closed->statusLabel());
        $this->assertSame('info', $closed->statusTone());
        $this->assertSame($admin->id, $closed->decided_by_user_id);
        $this->assertStringContainsString('10.08.2016', (string) $closed->waived_reason);
        $this->assertSame(2, AuditLog::query()->where('event', 'resale_period.waived')->count());

        // Danach nichts mehr zu tun; der Hinweis verschwindet, ein zweiter Aufruf ändert nichts.
        $this->assertSame([], app(ProviderBillingStart::class)->candidates($this->partner));
        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $this->partner))->assertOk()->assertDontSee(__('resale.billing_start.action'));
        $this->actingAs($admin)->post(route('finance.resale.reconcile.billing-start.store', $this->partner))->assertSessionHas('warning');

        // Einzeln wieder öffnen bleibt möglich.
        $this->actingAs($admin)->post(route('finance.resale.periods.reopen', $closed->sqid))->assertRedirect();
        $this->assertSame(PeriodStatus::Open, $closed->fresh()?->status);
    }

    public function test_action_needs_the_manage_permission(): void {
        $this->domain('fruehstart-beispiel.de', '2015-01-10');
        $viewer = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($viewer)->post(route('finance.resale.reconcile.billing-start.store', $this->partner))->assertForbidden();
        $this->assertSame(0, ResalePeriod::query()->where('status', PeriodStatus::Waived->value)->count());
    }
}
