<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalHelpTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\CustomerPortal;

use App\Enums\CustomerPortal\PortalCapability;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Services\Help\HelpTopicReindexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/** MVP-959: Hilfe im Kundenportal nur zu freigegebenen Bereichen. */
final class PortalHelpTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    public function test_help_lists_only_topics_of_released_areas(): void {
        $this->setUpOrganization();
        app(HelpTopicReindexer::class)->reindex();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $portalUser = User::factory()->kunde((int) $customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($customer, [PortalCapability::Invoices->value]);

        $this->actingAs($portalUser, 'customer')->get(route('customer.help.index'))
            ->assertOk()
            ->assertSeeText('Kundenportal – Übersicht')
            ->assertSeeText('Meine Rechnungen')
            ->assertDontSeeText('Kundenportal – Reklamationen und Rücksendungen');
        $this->actingAs($portalUser, 'customer')->get(route('customer.help.show', 'customer-portal.invoices'))->assertOk();
        $this->actingAs($portalUser, 'customer')->get(route('customer.help.show', 'customer-portal.claims'))->assertNotFound();

        $this->allowPortal($customer, [PortalCapability::Invoices->value, PortalCapability::Claims->value]);
        $portalUser->refresh();
        $this->actingAs($portalUser, 'customer')->get(route('customer.help.show', 'customer-portal.claims'))->assertOk()->assertSeeText('Rücksendung anmelden');
        $this->actingAs($portalUser, 'customer')->get(route('customer.invoices.index'))->assertOk()->assertSee(route('customer.help.show', 'customer-portal.invoices'), false);
    }
}
