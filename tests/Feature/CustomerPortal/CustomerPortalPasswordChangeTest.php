<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalPasswordChangeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CustomerPortal;

use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Passwortwechsel im Kundenportal (Phase 153, MVP-1233): freiwillig und als
 * Pflicht nach einem Startpasswort.
 */
class CustomerPortalPasswordChangeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const CURRENT = 'Start-Pass-2026';

    private const FRESH = 'Neues-Passwort-2026!';

    private User $portalUser;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->portalUser = User::factory()
            ->kunde((int) $customer->id, (int) $this->organization->id)
            ->create([
                'organization_id' => $this->organization->id,
                'email' => 'portal@example.test',
                'password' => Hash::make(self::CURRENT),
            ]);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    public function test_forced_change_blocks_the_portal_until_an_own_password_is_set(): void {
        $this->portalUser->forceFill(['must_change_password' => true])->save();

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.dashboard'))
            ->assertRedirect(route('customer.password.change'));
        $this->get(route('customer.password.change'))->assertOk()
            ->assertSee(__('customer_portal.password_change.forced_hint'));

        $this->post(route('customer.password.change.update'), [
            'current_password' => 'falsch',
            'password' => self::FRESH,
            'password_confirmation' => self::FRESH,
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue((bool) $this->portalUser->refresh()->must_change_password);

        $this->post(route('customer.password.change.update'), [
            'current_password' => self::CURRENT,
            'password' => self::FRESH,
            'password_confirmation' => self::FRESH,
        ])->assertRedirect(route('customer.dashboard'));

        $this->portalUser->refresh();
        $this->assertFalse((bool) $this->portalUser->must_change_password);
        $this->assertTrue(Hash::check(self::FRESH, $this->portalUser->password));
        $this->assertSame(1, AuditLog::query()->where('event', 'portal.access.password_changed')->count());

        $this->get(route('customer.dashboard'))->assertOk();
    }

    public function test_new_password_must_differ_and_follow_the_rules(): void {
        $this->actingAs($this->portalUser, 'customer');

        $this->post(route('customer.password.change.update'), [
            'current_password' => self::CURRENT,
            'password' => self::CURRENT,
            'password_confirmation' => self::CURRENT,
        ])->assertSessionHasErrors('password');

        $this->post(route('customer.password.change.update'), [
            'current_password' => self::CURRENT,
            'password' => 'kurz',
            'password_confirmation' => 'kurz',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::CURRENT, $this->portalUser->refresh()->password));
    }

    public function test_profile_links_to_the_change_page(): void {
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.profile.show'))
            ->assertOk()
            ->assertSee(route('customer.password.change'));
    }

    public function test_forced_change_comes_before_the_two_factor_requirement(): void {
        $this->organization->forceFill(['two_factor_required' => true])->save();
        $this->portalUser->forceFill(['must_change_password' => true])->save();

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.password.change'))->assertOk();

        $this->post(route('customer.password.change.update'), [
            'current_password' => self::CURRENT,
            'password' => self::FRESH,
            'password_confirmation' => self::FRESH,
        ])->assertRedirect(route('customer.dashboard'));

        // Danach greift die 2FA-Pflicht wie gewohnt.
        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.2fa.show'));
    }
}
