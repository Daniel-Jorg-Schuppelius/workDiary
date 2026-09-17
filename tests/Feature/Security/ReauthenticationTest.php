<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReauthenticationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\{Customer, User};
use App\Notifications\GenericEventNotification;
use App\Support\Auth\RecentAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\{Hash, Notification};
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sicherheitsaudit 2026-09-17 (authflow-1): Aus einer gekaperten, offenen
 * Sitzung darf kein dauerhaftes Anmeldemittel entstehen. Passkey-Registrierung,
 * API-Token und E-Mail-Wechsel verlangen eine frische Anmeldung bzw. das
 * Passwort — ein Passkey überlebt sonst Passwort-Reset und Sitzungswiderruf.
 */
final class ReauthenticationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    private function user(): User {
        return User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'is_new_system' => true,
            'password' => Hash::make('Geheim!2026'),
        ]);
    }

    public function test_passkey_registration_is_locked_without_fresh_login(): void {
        $user = $this->user();

        $response = $this->actingAs($user)->postJson(route('account.2fa.webauthn.options'));

        $response->assertStatus(423);
        $this->assertSame(route('password.confirm'), $response->json('redirect'));
        $this->assertNull(session('webauthn.register'));
    }

    public function test_password_confirmation_unlocks_passkey_registration(): void {
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('password.confirm.store'), ['password' => 'Geheim!2026'])
            ->assertRedirect();

        $this->actingAs($user)->postJson(route('account.2fa.webauthn.options'))->assertOk();
    }

    public function test_wrong_password_does_not_unlock(): void {
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('password.confirm.store'), ['password' => 'falsch'])
            ->assertSessionHasErrors('password');

        $this->assertNull(session(RecentAuthentication::SESSION_KEY));
        $this->actingAs($user)->postJson(route('account.2fa.webauthn.options'))->assertStatus(423);
    }

    public function test_api_token_creation_is_locked_without_fresh_login(): void {
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('profile.api-tokens.store'), ['name' => 'CLI'])
            ->assertRedirect(route('password.confirm'));

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_email_change_requires_password_without_fresh_login(): void {
        $user = $this->user();

        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => $user->name,
            'email' => 'angreifer@example.test',
        ])->assertSessionHasErrors('current_password');

        $this->assertNotSame('angreifer@example.test', (string) $user->refresh()->email);
    }

    public function test_email_change_with_current_password_informs_old_address(): void {
        Notification::fake();
        $user = $this->user();
        $old = (string) $user->email;

        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => $user->name,
            'email' => 'neu@example.test',
            'current_password' => 'Geheim!2026',
        ])->assertRedirect();

        $this->assertSame('neu@example.test', (string) $user->refresh()->email);
        Notification::assertSentOnDemand(
            GenericEventNotification::class,
            static fn (GenericEventNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routeNotificationFor('mail') === $old,
        );
    }

    public function test_portal_passkey_registration_is_locked_without_fresh_login(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $portalUser = User::factory()
            ->kunde((int) $customer->id, (int) $this->organization->id)
            ->create([
                'organization_id' => $this->organization->id,
                'password' => Hash::make('Geheim!2026'),
            ]);

        $response = $this->actingAs($portalUser, 'customer')->postJson(route('customer.2fa.webauthn.options'));

        $response->assertStatus(423);
        $this->assertSame(route('customer.password.confirm'), $response->json('redirect'));
    }

    public function test_fresh_login_counts_as_confirmation(): void {
        $user = $this->user();

        $this->post(route('login'), ['username' => $user->email, 'password' => 'Geheim!2026']);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(session(RecentAuthentication::SESSION_KEY));
        $this->postJson(route('account.2fa.webauthn.options'))->assertOk();
    }
}
