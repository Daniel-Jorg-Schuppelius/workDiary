<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalPasswordResetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CustomerPortal;

use App\Enums\Organization\TenantStatus;
use App\Enums\User\Permission as P;
use App\Mail\{CustomerPortalInvitationMail, CustomerPortalPasswordResetMail, PortalSecondFactorResetNoticeMail};
use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Services\CustomerPortal\PortalAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth, DB, Hash, Mail};
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1096: „Passwort vergessen“ im Kundenportal und „Zugang zurücksetzen“
 * an der Kundenakte.
 */
class PortalPasswordResetTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const PASSWORD = 'Altes!Passwort2026';

    private const NEW_PASSWORD = 'Neues!Passwort2026';

    private Customer $customer;

    private User $portalUser;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create(['email' => 'kundin@kunde.example', 'password' => Hash::make(self::PASSWORD)]);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function requestLink(string $email = 'kundin@kunde.example'): ?string {
        Mail::fake();
        $this->from(route('customer.password.request'))
            ->post(route('customer.password.email'), ['email' => $email])
            ->assertRedirect(route('customer.password.request'))
            ->assertSessionHas('status', __('Falls ein Konto mit dieser E-Mail existiert, wurde ein Link zum Zurücksetzen versendet.'));

        $url = null;
        Mail::assertSent(CustomerPortalPasswordResetMail::class, function (CustomerPortalPasswordResetMail $mail) use (&$url): bool {
            $url = $mail->resetUrl;

            return $mail->hasTo('kundin@kunde.example');
        });

        return $url;
    }

    private function assertNoLinkFor(string $email): void {
        Mail::fake();
        $this->from(route('customer.password.request'))
            ->post(route('customer.password.email'), ['email' => $email])
            ->assertRedirect(route('customer.password.request'))
            ->assertSessionHas('status', __('Falls ein Konto mit dieser E-Mail existiert, wurde ein Link zum Zurücksetzen versendet.'));
        Mail::assertNothingSent();
    }

    private function login(string $password): \Illuminate\Testing\TestResponse {
        return $this->post(route('customer.login.attempt'), ['email' => 'kundin@kunde.example', 'password' => $password]);
    }

    public function test_login_page_links_to_forgot_password(): void {
        $this->get(route('customer.login'))->assertOk()->assertSee(route('customer.password.request'));
        $this->get(route('customer.password.request'))->assertOk()->assertSee(__('Link senden'));
    }

    public function test_active_access_resets_password_and_ends_all_sessions(): void {
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            'id' => 'portal-session-1',
            'user_id' => $this->portalUser->id,
            'payload' => base64_encode(''),
            'last_activity' => now()->getTimestamp(),
        ]);

        $url = (string) $this->requestLink();
        $this->assertStringContainsString('signature=', $url);
        // Bis zum Setzen bleibt das alte Passwort gültig — eine Anfrage sperrt niemanden aus.
        $this->assertTrue(Hash::check(self::PASSWORD, $this->portalUser->fresh()->password));

        $this->get($url)->assertOk()->assertSee(__('Passwort festlegen'));
        $this->post($url, ['password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])
            ->assertRedirect(route('customer.login'))
            ->assertSessionHas('status', __('Passwort geändert. Bitte melden Sie sich an.'));

        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->portalUser->id)->count());
        $this->assertTrue(AuditLog::query()->where('event', 'portal.access.password_reset_requested')->exists());
        $this->assertTrue(AuditLog::query()->where('event', 'portal.access.password_reset')->exists());

        // Einmalig: nach dem Setzen ist der Link verbraucht.
        $this->get($url)->assertNotFound();

        $this->login(self::PASSWORD)->assertSessionHasErrors('email');
        $this->assertGuest('customer');
        $this->login(self::NEW_PASSWORD)->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($this->portalUser->fresh(), 'customer');
    }

    public function test_two_factor_still_applies_after_reset(): void {
        $this->portalUser->forceFill([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_recovery_codes' => ['rec-aaaaa'],
            'two_factor_confirmed_at' => now(),
        ])->save();

        $url = (string) $this->requestLink();
        $this->post($url, ['password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])
            ->assertRedirect(route('customer.login'));

        $this->assertNotNull($this->portalUser->fresh()->two_factor_confirmed_at);
        $this->login(self::NEW_PASSWORD)->assertRedirect(route('customer.two-factor.login'));
        $this->assertGuest('customer');
    }

    public function test_unknown_internal_deactivated_and_invited_accounts_get_the_same_answer(): void {
        $this->assertNoLinkFor('niemand@kunde.example');

        $this->orgUser(['email' => 'intern@firma.example']);
        $this->assertNoLinkFor('intern@firma.example');

        $this->portalUser->forceFill(['deactivated_at' => now()])->save();
        $this->assertNoLinkFor('kundin@kunde.example');

        $invited = User::factory()->kunde((int) $this->customer->id, (int) $this->organization->id)->create([
            'email' => 'eingeladen@kunde.example',
            'portal_invite_token_hash' => str_repeat('a', 64),
            'portal_invite_expires_at' => now()->addDay(),
        ]);
        $this->assertSame(PortalAccessService::STATE_INVITED, app(PortalAccessService::class)->state($invited));
        $this->assertNoLinkFor('eingeladen@kunde.example');
    }

    public function test_tampered_expired_and_revoked_links_are_rejected(): void {
        $url = (string) $this->requestLink();

        $this->get(str_replace('hash=', 'hash=0', $url))->assertNotFound();
        $this->get(Str::before($url, '&signature='))->assertNotFound();

        // Deaktiviert: Link endet sofort.
        $this->portalUser->forceFill(['deactivated_at' => now()])->save();
        $this->get($url)->assertNotFound();
        $this->portalUser->forceFill(['deactivated_at' => null])->save();
        $this->get($url)->assertOk();

        $this->travel(PortalAccessService::PASSWORD_RESET_TTL_MINUTES + 1)->minutes();
        $this->get($url)->assertNotFound();
        $this->post($url, ['password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])->assertNotFound();
        $this->assertTrue(Hash::check(self::PASSWORD, $this->portalUser->fresh()->password));
    }

    public function test_link_is_locked_for_a_suspended_tenant(): void {
        $url = (string) $this->requestLink();
        app()->forgetInstance('currentOrganization');
        $this->organization->forceFill(['tenant_status' => TenantStatus::Suspended])->save();

        $this->get($url)->assertStatus(423);
        $this->assertNoLinkFor('kundin@kunde.example');
    }

    public function test_requests_are_throttled_per_address(): void {
        Mail::fake();
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('customer.password.email'), ['email' => 'kundin@kunde.example'])->assertRedirect();
        }
        $this->post(route('customer.password.email'), ['email' => 'kundin@kunde.example'])->assertStatus(429);
    }

    public function test_manager_resets_active_access(): void {
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            'id' => 'portal-session-2',
            'user_id' => $this->portalUser->id,
            'payload' => base64_encode(''),
            'last_activity' => now()->getTimestamp(),
        ]);
        $manager = $this->manager();

        $this->actingAs($manager)
            ->get(route('customers.show', $this->customer))
            ->assertOk()
            ->assertSee(route('customers.portal-access.reset', [$this->customer, $this->portalUser]));

        Mail::fake();
        $this->actingAs($manager)
            ->post(route('customers.portal-access.reset', [$this->customer, $this->portalUser]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $token = null;
        Mail::assertSent(CustomerPortalInvitationMail::class, function (CustomerPortalInvitationMail $mail) use (&$token): bool {
            $token = Str::afterLast($mail->acceptUrl, '/');

            return $mail->reset && $mail->hasTo('kundin@kunde.example');
        });

        $fresh = $this->portalUser->fresh();
        $this->assertFalse(Hash::check(self::PASSWORD, $fresh->password), 'Das alte Passwort gilt nicht mehr.');
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->portalUser->id)->count());
        $this->assertSame(PortalAccessService::STATE_INVITED, app(PortalAccessService::class)->state($fresh));
        $audit = AuditLog::query()->where('event', 'portal.access.reset')->first();
        $this->assertNotNull($audit);
        $this->assertSame($manager->id, (int) $audit->getAttribute('changes')['by']);

        Auth::guard('web')->logout();
        $this->login(self::PASSWORD)->assertSessionHasErrors('email');
        $this->post(route('customer.invitation.accept', ['token' => (string) $token]), [
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect(route('customer.login'));
        $this->login(self::NEW_PASSWORD)->assertRedirect(route('customer.dashboard'));
    }

    public function test_reset_needs_permission_an_active_access_and_the_own_customer(): void {
        Mail::fake();
        $plain = $this->orgUser();
        $this->actingAs($plain)
            ->post(route('customers.portal-access.reset', [$this->customer, $this->portalUser]))
            ->assertForbidden();

        $manager = $this->manager();
        $this->portalUser->forceFill(['deactivated_at' => now()])->save();
        $this->actingAs($manager)
            ->post(route('customers.portal-access.reset', [$this->customer, $this->portalUser]))
            ->assertNotFound();

        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = User::factory()->kunde((int) $otherCustomer->id, (int) $this->organization->id)->create();
        $this->actingAs($manager)
            ->post(route('customers.portal-access.reset', [$this->customer, $foreign]))
            ->assertNotFound();

        Mail::assertNothingSent();
    }

    /** MVP-1100: zweiten Faktor entfernen — nur mit Passwortbestätigung, mit Audit und Hinweis an den Kunden. */
    public function test_manager_resets_second_factor_after_reauth(): void {
        $this->portalUser->forceFill([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_recovery_codes' => ['rec-aaaaa'],
            'two_factor_confirmed_at' => now(),
        ])->save();
        $manager = $this->manager();
        $route = route('customers.portal-access.reset-second-factor', [$this->customer, $this->portalUser]);
        Mail::fake();

        $this->actingAs($manager)->post($route)->assertRedirect(route('password.confirm'));
        $this->assertNotNull($this->portalUser->fresh()->two_factor_confirmed_at);

        $this->actingAs($manager)->withRecentAuthentication()->post($route)->assertSessionHas('success');

        $fresh = $this->portalUser->fresh();
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_confirmed_at);
        $this->assertFalse($fresh->hasTwoFactorEnabled());
        $this->assertNotNull(AuditLog::query()->where('event', 'portal.access.second_factor_reset')->first());
        Mail::assertSent(PortalSecondFactorResetNoticeMail::class, fn (PortalSecondFactorResetNoticeMail $mail): bool => $mail->hasTo('kundin@kunde.example'));

        // Manager und Kunde teilen im Test eine Sitzung; das Rücksprungziel der Passwortbestätigung gehört nicht zum Kunden.
        Auth::guard('web')->logout();
        $this->flushSession();
        $this->login(self::PASSWORD)->assertRedirect(route('customer.dashboard'));

        $this->actingAs($manager)->withRecentAuthentication()->post($route)->assertNotFound();
    }

    private function manager(): User {
        $manager = $this->orgUser();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        foreach ([P::CustomerPortalAccessManage, P::CustomerView] as $permission) {
            SpatiePermission::findOrCreate($permission->value, 'web');
            $manager->givePermissionTo($permission->value);
        }

        return $manager;
    }
}
