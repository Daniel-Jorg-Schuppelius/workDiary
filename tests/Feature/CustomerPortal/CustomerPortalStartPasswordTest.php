<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalStartPasswordTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CustomerPortal;

use App\Enums\User\Permission as P;
use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Services\CustomerPortal\PortalAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, Mail};
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Zugang mit Startpasswort (Phase 153, MVP-1232): einmal angezeigt, nie
 * versendet, Pflichtwechsel bei der ersten Anmeldung.
 */
class CustomerPortalStartPasswordTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $manager;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->manager = $this->orgUser();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        foreach ([P::CustomerPortalAccessManage, P::CustomerView] as $p) {
            SpatiePermission::findOrCreate($p->value, 'web');
            $this->manager->givePermissionTo($p->value);
        }

        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Startpasswort-Kunde',
        ]);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function createDirect(array $data = []) {
        return $this->actingAs($this->manager)->withRecentAuthentication()
            ->post(route('customers.portal-access.store-direct', $this->customer), $data + [
                'name' => 'Erika Direkt',
                'email' => 'erika@example.test',
            ]);
    }

    public function test_creation_requires_recent_authentication(): void {
        $this->actingAs($this->manager)
            ->post(route('customers.portal-access.store-direct', $this->customer), ['name' => 'X', 'email' => 'x@example.test'])
            ->assertRedirect(route('password.confirm'));
        $this->assertDatabaseMissing('users', ['email' => 'x@example.test']);

        // Der Dialog bietet ohne frische Anmeldung zuerst die Bestätigung an, danach das Formular.
        $this->actingAs($this->manager)->get(route('customers.portal-access.create-direct', $this->customer))
            ->assertOk()
            ->assertSee(route('password.confirm'))
            ->assertDontSee('name="email"', false);
        $this->withRecentAuthentication()->get(route('customers.portal-access.create-direct', $this->customer))
            ->assertOk()
            ->assertSee('name="email"', false)
            ->assertSee(route('customers.portal-access.store-direct', $this->customer));
    }

    public function test_start_password_is_shown_once_never_mailed_and_forces_a_change_at_first_login(): void {
        Mail::fake();

        $this->createDirect()->assertRedirect(route('customers.show', ['customer' => $this->customer, '#' => 'portal-access']));

        $user = User::query()->where('email', 'erika@example.test')->firstOrFail();
        $this->assertTrue((bool) $user->must_change_password);
        $this->assertNull($user->portal_invite_token_hash);
        $this->assertNull($user->email_verified_at);
        $this->assertSame(PortalAccessService::STATE_PROVISIONAL, app(PortalAccessService::class)->state($user));

        $password = session('portal_start_password');
        $this->assertIsString($password);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Za-km-z2-9]{4}-[A-HJ-NP-Za-km-z2-9]{4}-[A-HJ-NP-Za-km-z2-9]{4}$/', $password);
        $this->assertTrue(Hash::check($password, $user->password));
        Mail::assertNothingSent();

        $audit = AuditLog::query()->where('event', 'portal.access.created_with_start_password')->firstOrFail();
        $this->assertSame($user->id, (int) $audit->auditable_id);
        $this->assertStringNotContainsString($password, (string) json_encode($audit->changes));

        // Einmal sichtbar in der Kundenakte, danach nicht mehr.
        $this->get(route('customers.show', $this->customer))->assertOk()->assertSee($password)
            ->assertSee(__('customer_portal.direct.state_provisional'));
        $this->get(route('customers.show', $this->customer))->assertOk()->assertDontSee($password);

        // Erste Anmeldung führt auf den Pflichtwechsel, nicht ins Portal und nicht auf die interne Seite.
        $this->post(route('customer.login.attempt'), ['email' => 'erika@example.test', 'password' => $password])
            ->assertRedirect(route('customer.dashboard'));
        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.password.change'));
    }

    public function test_collective_customer_and_known_address_are_rejected_neutrally(): void {
        $this->customer->forceFill(['is_collective' => true])->save();
        $this->createDirect()->assertSessionHasErrors('email');
        $this->customer->forceFill(['is_collective' => false])->save();

        $this->orgUser(['email' => 'intern@example.test']);
        $this->createDirect(['email' => 'intern@example.test'])
            ->assertSessionHasErrors(['email' => __('Für diese E-Mail-Adresse kann kein Portalzugang erstellt werden.')]);
        $this->assertSame(1, User::query()->where('email', 'intern@example.test')->count());
    }

    public function test_reissue_replaces_password_ends_sessions_and_needs_recent_authentication(): void {
        $portalUser = User::factory()->kunde((int) $this->customer->id, (int) $this->organization->id)->create([
            'organization_id' => $this->organization->id,
            'email' => 'aktiv@example.test',
            'password' => Hash::make('altes-passwort'),
            'remember_token' => 'alt',
        ]);

        $this->actingAs($this->manager)
            ->post(route('customers.portal-access.start-password', [$this->customer, $portalUser]))
            ->assertRedirect(route('password.confirm'));
        $this->assertTrue(Hash::check('altes-passwort', $portalUser->refresh()->password));

        $this->withRecentAuthentication()
            ->post(route('customers.portal-access.start-password', [$this->customer, $portalUser]))
            ->assertRedirect(route('customers.show', ['customer' => $this->customer, '#' => 'portal-access']));

        $portalUser->refresh();
        $password = session('portal_start_password');
        $this->assertTrue(Hash::check($password, $portalUser->password));
        $this->assertFalse(Hash::check('altes-passwort', $portalUser->password));
        $this->assertTrue((bool) $portalUser->must_change_password);
        $this->assertNotSame('alt', $portalUser->remember_token);
        $this->assertSame(1, AuditLog::query()->where('event', 'portal.access.start_password_issued')->count());
    }

    public function test_reissue_for_deactivated_account_is_404(): void {
        $portalUser = User::factory()->kunde((int) $this->customer->id, (int) $this->organization->id)->create([
            'organization_id' => $this->organization->id,
            'email' => 'aus@example.test',
            'deactivated_at' => now(),
        ]);

        $this->actingAs($this->manager)->withRecentAuthentication()
            ->post(route('customers.portal-access.start-password', [$this->customer, $portalUser]))
            ->assertNotFound();
    }
}
