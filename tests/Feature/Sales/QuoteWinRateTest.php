<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteWinRateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Enums\Sales\QuoteStatus;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Sales\Quote;
use App\Services\Sales\QuoteWinRateReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MVP-998: Trefferquote der Angebote nach Anzahl und Volumen. */
final class QuoteWinRateTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private User $seller;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo(CarbonImmutable::create(2026, 6, 15, 10, 0, 0, 'UTC'));
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->seller = User::factory()->user()->create(['organization_id' => $this->org->id, 'name' => 'Vera Vertrieb']);
    }

    /** @param array<string, mixed> $attributes */
    private function quote(Customer $customer, string $number, QuoteStatus $status, string $net, array $attributes = []): Quote {
        return Quote::query()->create($attributes + [
            'organization_id' => $this->org->id, 'customer_id' => $customer->id, 'number' => $number, 'version' => 1,
            'status' => $status, 'subtotal' => $net, 'tax_amount' => '0.00', 'total' => $net, 'created_by' => $this->admin->id,
        ]);
    }

    public function test_rate_counts_the_latest_version_by_decision_or_validity_date(): void {
        $alpha = Customer::factory()->create(['organization_id' => $this->org->id, 'name' => 'Alpha GmbH', 'company' => null]);
        $beta = Customer::factory()->create(['organization_id' => $this->org->id, 'name' => 'Beta KG', 'company' => null]);

        $first = $this->quote($alpha, 'AN-1', QuoteStatus::Rejected, '900.00', ['decided_at' => '2026-05-02 10:00:00']);
        $this->quote($alpha, 'AN-1', QuoteStatus::Accepted, '1000.00', ['version' => 2, 'previous_version_id' => $first->id, 'decided_at' => '2026-05-10 10:00:00', 'follow_up_user_id' => $this->seller->id]);
        $this->quote($beta, 'AN-2', QuoteStatus::Rejected, '500.00', ['decided_at' => '2026-05-20 10:00:00']);
        $this->quote($beta, 'AN-3', QuoteStatus::Sent, '300.00', ['valid_until' => '2026-06-01']);
        $this->quote($alpha, 'AN-4', QuoteStatus::Expired, '200.00', ['valid_until' => '2026-05-15']);
        $this->quote($alpha, 'AN-5', QuoteStatus::Sent, '700.00', ['valid_until' => '2026-07-31']);
        $this->quote($beta, 'AN-6', QuoteStatus::Accepted, '800.00', ['decided_at' => '2026-03-01 10:00:00']);

        $result = app(QuoteWinRateReport::class)->build($this->org->id, CarbonImmutable::create(2026, 5, 1), CarbonImmutable::create(2026, 6, 30));

        $this->assertSame([1, 1, 2], [$result['totals']['won'], $result['totals']['lost'], $result['totals']['expired']]);
        $this->assertSame('25.0', $result['totals']['rate']?->getNumericValue(), 'AN-1 zählt nur mit der jüngsten Fassung');
        $this->assertSame('2000.00', $result['totals']['decided_volume']->getAmount());
        $this->assertSame('50.0', $result['totals']['volume_rate']?->getNumericValue());
        $this->assertSame(1, $result['open']['count']);
        $this->assertSame('700.00', $result['open']['volume']->getAmount());

        $byCustomer = collect($result['groups'])->keyBy('label');
        $this->assertSame('50.0', $byCustomer['Alpha GmbH']['rate']?->getNumericValue());
        $this->assertSame(0, $byCustomer['Beta KG']['won']);

        $byOwner = collect(app(QuoteWinRateReport::class)->build($this->org->id, CarbonImmutable::create(2026, 5, 1), CarbonImmutable::create(2026, 6, 30), 'owner')['groups'])->keyBy('label');
        $this->assertSame(1, $byOwner['Vera Vertrieb']['won']);

        $this->actingAs($this->admin)->get(route('quotes.win-rate', ['from' => '2026-05-01', 'to' => '2026-06-30']))
            ->assertOk()->assertSee('25,0 %')->assertSee('Alpha GmbH');
        $this->actingAs($this->admin)->get(route('quotes.follow-ups.index'))->assertOk()->assertSee(route('quotes.win-rate'), false);
    }
}
