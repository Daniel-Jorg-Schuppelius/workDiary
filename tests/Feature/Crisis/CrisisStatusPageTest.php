<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisStatusPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Crisis;

use App\Models\Crisis\{CrisisCase, CrisisCommunication};
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Services\Crisis\CrisisStatusPageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/** MVP-915: öffentliche Statusseite und Hinweise im Kundenportal. */
final class CrisisStatusPageTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $case = CrisisCase::query()->create(['organization_id' => $this->organization->id, 'title' => 'Interner Aktentitel', 'category' => 'it_outage', 'severity' => 'major', 'status' => 'activated', 'created_by' => $this->admin->id]);
        $old = CrisisCase::query()->create(['organization_id' => $this->organization->id, 'title' => 'Alt', 'category' => 'it_outage', 'severity' => 'minor', 'status' => 'closed', 'all_clear_at' => now()->subDays(30), 'created_by' => $this->admin->id]);
        foreach ([
            [$case, 'public', 'sent', 'Störung der Hotline'],
            [$case, 'customers', 'sent', 'Hinweis an Kunden'],
            [$case, 'public', 'approved', 'Noch nicht versandt'],
            [$case, 'internal', 'sent', 'Nur intern'],
            [$old, 'public', 'sent', 'Längst erledigt'],
        ] as [$c, $audience, $status, $subject]) {
            CrisisCommunication::query()->create(['organization_id' => $this->organization->id, 'crisis_case_id' => $c->id, 'audience' => $audience, 'subject' => $subject, 'body' => 'Text zu ' . $subject, 'status' => $status, 'sent_at' => $status === 'sent' ? now() : null, 'created_by' => $this->admin->id]);
        }
    }

    public function test_public_page_needs_token_and_release_and_shows_only_sent_public_messages(): void {
        $this->actingAs($this->admin)->post(route('crisis.status-page.rotate'))->assertRedirect(route('crisis.status-page.edit'));
        $token = session('crisis_status_token');
        $this->assertIsString($token);
        $this->actingAs($this->admin)->get(route('crisis.status-page.edit'))->assertOk()->assertSee(route('crisis-status.public', $token));
        $this->actingAs($this->admin)->get(route('crisis.index'))->assertOk()->assertSee(route('crisis.status-page.edit'));
        auth()->logout();
        app()->forgetInstance('currentOrganization');

        $this->get(route('crisis-status.public', $token))->assertNotFound();

        $this->actingAs($this->admin)->patch(route('crisis.status-page.toggle'), ['enabled' => 1])->assertRedirect();
        auth()->logout();
        app()->forgetInstance('currentOrganization');

        $this->get(route('crisis-status.public', $token))->assertOk()
            ->assertSee('Störung der Hotline')
            ->assertDontSee('Hinweis an Kunden')
            ->assertDontSee('Noch nicht versandt')
            ->assertDontSee('Nur intern')
            ->assertDontSee('Längst erledigt')
            ->assertDontSee('Interner Aktentitel');
        $this->get(route('crisis-status.public', 'falsch'))->assertNotFound();
    }

    public function test_revoke_closes_the_page_and_stores_only_a_fingerprint(): void {
        $service = app(CrisisStatusPageService::class);
        $token = $service->issue($this->organization);
        $service->setEnabled($this->organization, true);
        $settings = $this->organization->fresh()->settings;
        $this->assertSame(CrisisStatusPageService::fingerprint($token), $settings[CrisisStatusPageService::HASH_KEY]);
        $this->assertNotContains($token, $settings);

        $this->actingAs($this->admin)->delete(route('crisis.status-page.revoke'))->assertRedirect();
        $this->assertNull($service->resolve($token));
        $this->assertFalse($service->status($this->organization->fresh())['enabled']);
    }

    public function test_managing_the_page_needs_the_organization_permission(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($user)->post(route('crisis.status-page.rotate'))->assertForbidden();
    }

    public function test_portal_dashboard_shows_public_and_customer_messages(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($customer);
        $portalUser = User::factory()->kunde((int) $customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);

        $this->actingAs($portalUser, 'customer')->get(route('customer.dashboard'))->assertOk()
            ->assertSee('Störung der Hotline')
            ->assertSee('Hinweis an Kunden')
            ->assertDontSee('Nur intern')
            ->assertDontSee('Interner Aktentitel');
    }
}
