<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalAccessTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\User\Permission as P;
use App\Mail\CustomerPortalInvitationMail;
use App\Models\{Customer, Organization, User};
use App\Services\CustomerPortal\PortalAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail};
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-510: Kundenportal-Zugänge einladen, verwalten und widerrufen.
 * Einmaliger gehashter Token, keine Klartext-Passwörter, strikte
 * Organisations-/Kundenbindung, sofortige Fernabmeldung beim Widerruf.
 */
class CustomerPortalAccessTest extends TestCase {
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
            'name' => 'Portal-Kunde',
        ]);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    /** Fängt den Klartext-Token aus der versendeten Einladung ab. */
    private function inviteAndCaptureToken(string $name = 'Erika Kundin', string $email = 'erika@kunde.example'): array {
        Mail::fake();

        $this->actingAs($this->manager)
            ->post(route('customers.portal-access.store', $this->customer), [
                'name' => $name,
                'email' => $email,
            ])
            ->assertRedirect();

        $token = null;
        Mail::assertSent(CustomerPortalInvitationMail::class, function (CustomerPortalInvitationMail $mail) use (&$token, $email): bool {
            $token = \Illuminate\Support\Str::afterLast($mail->acceptUrl, '/');

            return $mail->hasTo($email);
        });

        /** @var User $portalUser */
        $portalUser = User::query()->where('email', $email)->firstOrFail();

        return [$portalUser, (string) $token];
    }

    public function test_invite_creates_portal_account_and_sends_one_time_link(): void {
        [$portalUser, $token] = $this->inviteAndCaptureToken();

        $this->assertSame($this->customer->id, $portalUser->customer_id);
        $this->assertSame($this->organization->id, $portalUser->organization_id);
        $this->assertNotNull($portalUser->portal_invite_token_hash);
        $this->assertNotSame($token, $portalUser->portal_invite_token_hash, 'Nur der Hash liegt in der DB.');
        $this->assertSame(PortalAccessService::STATE_INVITED, app(PortalAccessService::class)->state($portalUser));
    }

    public function test_accept_sets_password_and_allows_portal_login_only(): void {
        [$portalUser, $token] = $this->inviteAndCaptureToken();

        $this->get(route('customer.invitation.show', ['token' => $token]))->assertOk();

        $this->post(route('customer.invitation.accept', ['token' => $token]), [
            'password' => 'Sicher!Portal123',
            'password_confirmation' => 'Sicher!Portal123',
        ])->assertRedirect(route('customer.login'));

        $fresh = $portalUser->fresh();
        $this->assertNull($fresh->portal_invite_token_hash, 'Token ist nach Verwendung entwertet.');
        $this->assertSame(PortalAccessService::STATE_ACTIVE, app(PortalAccessService::class)->state($fresh));

        $this->post(route('customer.login.attempt'), [
            'email' => $portalUser->email,
            'password' => 'Sicher!Portal123',
        ])->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($fresh, 'customer');
    }

    public function test_used_expired_and_random_tokens_are_rejected(): void {
        [$portalUser, $token] = $this->inviteAndCaptureToken();

        // Verwendet → ungültig.
        $this->post(route('customer.invitation.accept', ['token' => $token]), [
            'password' => 'Sicher!Portal123',
            'password_confirmation' => 'Sicher!Portal123',
        ])->assertRedirect();
        $this->get(route('customer.invitation.show', ['token' => $token]))->assertNotFound();

        // Abgelaufen → ungültig (neuer Zugang, Ablauf zurückdatiert).
        [$second, $secondToken] = $this->inviteAndCaptureToken('Zweiter Kontakt', 'zweiter@kunde.example');
        $second->forceFill(['portal_invite_expires_at' => now()->subDay()])->save();
        $this->get(route('customer.invitation.show', ['token' => $secondToken]))->assertNotFound();
        $this->assertSame(PortalAccessService::STATE_EXPIRED, app(PortalAccessService::class)->state($second->fresh()));

        // Geraten → ungültig, neutrale Antwort.
        $this->get(route('customer.invitation.show', ['token' => str_repeat('x', 48)]))->assertNotFound();
    }

    public function test_deactivate_terminates_sessions_and_blocks_login(): void {
        [$portalUser, $token] = $this->inviteAndCaptureToken();
        $this->post(route('customer.invitation.accept', ['token' => $token]), [
            'password' => 'Sicher!Portal123',
            'password_confirmation' => 'Sicher!Portal123',
        ]);

        DB::table('sessions')->insert([
            'id' => 'portal-session-1',
            'user_id' => $portalUser->id,
            'payload' => base64_encode(''),
            'last_activity' => now()->getTimestamp(),
        ]);

        // Produktion fährt database-Sessions; der Invalidator purged nur dort.
        config(['session.driver' => 'database']);

        $this->actingAs($this->manager)
            ->post(route('customers.portal-access.deactivate', [$this->customer, $portalUser]))
            ->assertRedirect();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $portalUser->id)->count(), 'Deaktivierung beendet bestehende Portal-Sessions.');
        $this->assertNotNull($portalUser->fresh()->deactivated_at);

        $this->post(route('customer.login.attempt'), [
            'email' => $portalUser->email,
            'password' => 'Sicher!Portal123',
        ])->assertRedirect();
        $this->assertGuest('customer');

        // Reaktivierung stellt den Login wieder her.
        $this->actingAs($this->manager)
            ->post(route('customers.portal-access.reactivate', [$this->customer, $portalUser]))
            ->assertRedirect();
        $this->post(route('customer.login.attempt'), [
            'email' => $portalUser->email,
            'password' => 'Sicher!Portal123',
        ])->assertRedirect(route('customer.dashboard'));
    }

    public function test_without_permission_all_actions_are_forbidden(): void {
        Mail::fake();
        $plain = $this->orgUser();
        $portalUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->actingAs($plain)
            ->post(route('customers.portal-access.store', $this->customer), ['name' => 'X', 'email' => 'x@y.example'])
            ->assertForbidden();
        $this->actingAs($plain)
            ->post(route('customers.portal-access.deactivate', [$this->customer, $portalUser]))
            ->assertForbidden();
        $this->actingAs($plain)
            ->get(route('customers.portal-access.create', $this->customer))
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_cross_tenant_and_foreign_customer_ids_are_rejected(): void {
        Mail::fake();
        $otherOrg = Organization::factory()->create();
        $otherCustomer = Customer::factory()->create(['organization_id' => $otherOrg->id]);
        $foreignPortalUser = User::factory()->create([
            'organization_id' => $otherOrg->id,
            'customer_id' => $otherCustomer->id,
        ]);

        // Fremder Kunde: der OrganizationScope lässt die Route-Bindung ins
        // Leere laufen — kundensicher 404, ohne Existenz zu verraten.
        $this->actingAs($this->manager)
            ->post(route('customers.portal-access.store', $otherCustomer), ['name' => 'X', 'email' => 'x@y.example'])
            ->assertNotFound();

        // Portalkonto eines fremden Kunden am eigenen Kunden: kundensicher 404.
        $this->actingAs($this->manager)
            ->post(route('customers.portal-access.deactivate', [$this->customer, $foreignPortalUser]))
            ->assertNotFound();

        // Internes Konto der eigenen Org ist kein Portalkonto: 404.
        $internal = $this->orgUser();
        $this->actingAs($this->manager)
            ->post(route('customers.portal-access.deactivate', [$this->customer, $internal]))
            ->assertNotFound();
    }

    public function test_invite_with_taken_email_answers_neutrally(): void {
        Mail::fake();
        $existing = $this->orgUser(['email' => 'schon-da@example.com']);

        $this->actingAs($this->manager)
            ->from(route('customers.show', $this->customer))
            ->post(route('customers.portal-access.store', $this->customer), [
                'name' => 'Jemand',
                'email' => 'schon-da@example.com',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::query()->where('email', 'schon-da@example.com')->count());
        $this->assertNull($existing->fresh()->customer_id, 'Bestehendes Konto bleibt unangetastet.');
        Mail::assertNothingSent();
    }

    public function test_existing_portal_users_are_listed_in_panel(): void {
        $legacyPortalUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Bestandszugang',
            'email' => 'bestand@kunde.example',
        ]);

        $this->actingAs($this->manager)
            ->get(route('customers.show', $this->customer))
            ->assertOk()
            ->assertSee('Portalzugänge')
            ->assertSee('bestand@kunde.example');

        $this->assertSame(PortalAccessService::STATE_ACTIVE, app(PortalAccessService::class)->state($legacyPortalUser), 'Bestandskonten gelten als aktive Zugänge.');
    }

    public function test_resend_rotates_token(): void {
        [$portalUser, $firstToken] = $this->inviteAndCaptureToken();
        $firstHash = $portalUser->portal_invite_token_hash;

        Mail::fake();
        $this->actingAs($this->manager)
            ->post(route('customers.portal-access.resend', [$this->customer, $portalUser]))
            ->assertRedirect();

        Mail::assertSent(CustomerPortalInvitationMail::class, 1);
        $this->assertNotSame($firstHash, $portalUser->fresh()->portal_invite_token_hash, 'Erneuter Versand rotiert den Token.');
        $this->get(route('customer.invitation.show', ['token' => $firstToken]))->assertNotFound();
    }
}
