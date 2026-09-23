<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderAnalysisLinksTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Models\{Asset, DiaryEntry};
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vom Auftrag in die Auswertung (MVP-807, Entscheid P2-06 aus dem Vollscan
 * 2026-09-15): Bis dahin liefen Drilldowns nur Report → Auftrag.
 */
final class OrderAnalysisLinksTest extends TestCase {
    use RefreshDatabase;

    public function test_customer_and_asset_tiles_link_into_their_analysis(): void {
        $organization = Organization::factory()->enterprise()->create();
        [$user, $entry, $customer] = $this->entry($organization);

        $response = $this->actingAs($user)->get(route('diary.show', $entry))->assertOk();

        $response->assertSee(route('reports.customers', ['customer' => $customer->sqid]), false)
            ->assertSee(e(route('reports.assets', ['category_code' => 'HLK', 'manufacturer' => 'Viessmann'])), false);
    }

    public function test_links_are_hidden_when_the_plan_lacks_the_reports(): void {
        $organization = Organization::factory()->free()->create();
        [$user, $entry] = $this->entry($organization);

        $this->actingAs($user)->get(route('diary.show', $entry))
            ->assertOk()
            ->assertDontSee(route('reports.customers'), false)
            ->assertDontSee(route('reports.assets'), false);
    }

    /** @return array{0: User, 1: DiaryEntry, 2: Customer} */
    private function entry(Organization $organization): array {
        $user = User::factory()->admin()->create(['organization_id' => $organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);
        $asset = Asset::factory()->create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'category_code' => 'HLK',
            'manufacturer' => 'Viessmann',
        ]);
        $entry = DiaryEntry::factory()->for($user)->create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'asset_id' => $asset->id,
        ]);

        return [$user, $entry, $customer];
    }
}
