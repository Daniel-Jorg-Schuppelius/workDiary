<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginPolicyHardeningTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Auth\{LoginPolicyMode, LoginPolicyReason, TwoFactorType};
use App\Enums\Notification\NotificationEvent;
use App\Enums\Security\SecurityEventType;
use App\Enums\User\Permission;
use App\Http\Controllers\Admin\SupportImpersonationController;
use App\Models\Auth\{OrganizationLoginPolicy, SecurityEvent, SupportAccessGrant, UserKnownDevice};
use App\Models\Customer\Customer;
use App\Models\Mcp\{McpOAuthClient, McpOAuthCode};
use App\Models\Platform\User;
use App\Notifications\GenericEventNotification;
use App\Services\Auth\LoginPolicy\{DeviceApprovalService, LoginDeviceCookie, LoginNetworks};
use App\Services\Auth\TwoFactorService;
use App\Services\Security\SecurityEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth, Hash, Notification};
use PragmaRX\Google2FAQRCode\Google2FA;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * Feature 205: Regressionstests zur unabhängigen Sicherheitsprüfung
 * (H1, M1, M2, M3-Validierung, N1, N2, N4, N5, N8, N9, N10, N11, N12).
 */
final class LoginPolicyHardeningTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private const string PASSWORD = 'Sicher!2026Pass';

    private const string OFFICE_IP = '198.51.100.20';

    private const string HOME_IP = '203.0.113.9';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
    }

    /** @param array<string, mixed> $attributes */
    private function policy(array $attributes): OrganizationLoginPolicy {
        $policy = new OrganizationLoginPolicy;
        $policy->forceFill(array_merge(['organization_id' => $this->organization->id], $attributes))->save();

        return $policy;
    }

    private function officeOnly(): OrganizationLoginPolicy {
        return $this->policy(['network_mode' => LoginPolicyMode::Enforce, 'networks' => ['198.51.100.0/24']]);
    }

    /** @param array<string, mixed> $attributes */
    private function employee(array $attributes = []): User {
        return $this->orgUser(array_merge(['email' => 'mitarbeiterin@example.test', 'is_new_system' => true, 'password' => Hash::make(self::PASSWORD)], $attributes));
    }

    private function grant(User $user, Permission ...$permissions): void {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->organization->id);
        foreach ($permissions as $permission) {
            SpatiePermission::findOrCreate($permission->value, 'web');
            $user->givePermissionTo($permission->value);
        }
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }

    private function passkey(User $user, string $credentialId): void {
        $user->twoFactorCredentials()->create([
            'type' => TwoFactorType::Webauthn->value,
            'label' => 'Laptop',
            'credential_id' => $credentialId,
            'data' => ['irrelevant' => true],
            'confirmed_at' => now(),
        ]);
    }

    /** Person mit TOTP, einem Wiederherstellungscode und (optional) Passkey; liefert das TOTP-Geheimnis. */
    private function twoFactorUser(User $user, bool $withPasskey = true): string {
        $secret = app(Google2FA::class)->generateSecretKey();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [Hash::make('aaaaa-bbbbb')],
        ])->save();
        if ($withPasskey) {
            $this->passkey($user, 'cred-' . $user->id);
        }

        return $secret;
    }

    // ── H1 + N4: Passkey-Pflicht nur mit Wiederherstellungscode umgehbar ──

    public function test_passkey_pflicht_weist_app_code_auch_neben_wiederherstellungscode_ab(): void {
        Notification::fake();
        $this->policy(['passkey_roles' => ['user']]);
        $admin = $this->orgAdmin();
        $user = $this->employee();
        $secret = $this->twoFactorUser($user);

        $this->post('/login', ['username' => $user->email, 'password' => self::PASSWORD])->assertRedirect(route('two-factor.login'));

        $this->post(route('two-factor.login.attempt'), ['code' => app(Google2FA::class)->getCurrentOtp($secret), 'recovery_code' => 'falsch'])
            ->assertSessionHasErrors('code');
        $this->post(route('two-factor.login.attempt'), ['email_code' => '123456', 'recovery_code' => 'aaaaa-bbbbb'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
        $denied = SecurityEvent::query()->where('event', SecurityEventType::LoginPolicyDenied->value)->where('user_id', $user->id)->orderBy('id')->get();
        $this->assertSame(['totp+recovery', 'email+recovery'], $denied->map(fn (SecurityEvent $e): mixed => $e->meta['method'] ?? null)->all());
        $this->assertSame(LoginPolicyReason::Passkey->value, $denied->first()?->meta['reason'] ?? null);
        $this->assertTrue(app(TwoFactorService::class)->matchesRecoveryCode($user->fresh() ?? $user, 'aaaaa-bbbbb'), 'Der Code wurde nicht verbraucht.');

        // Notweg: nur der Wiederherstellungscode — mit Ereignis und Meldung an Person und Org-Admins.
        $this->post(route('two-factor.login.attempt'), ['recovery_code' => 'aaaaa-bbbbb'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(SecurityEvent::query()->where('event', SecurityEventType::PasskeyRecoveryUsed->value)->where('user_id', $user->id)->exists());
        foreach ([$user, $admin] as $recipient) {
            Notification::assertSentTo($recipient, GenericEventNotification::class, static fn (GenericEventNotification $n): bool => $n->event === NotificationEvent::SecurityPasskeyRecovery);
        }
    }

    public function test_letzter_passkey_bleibt_unter_pflicht(): void {
        $this->policy(['passkey_roles' => ['user']]);
        $user = $this->employee();
        $this->passkey($user, 'cred-1');
        $first = $user->twoFactorCredentials()->sole();

        $this->actingAs($user)->withRecentAuthentication()->delete(route('account.2fa.credential.destroy', $first))->assertSessionHasErrors('credential');
        $this->assertNotNull($first->fresh());

        $this->passkey($user, 'cred-2');
        $this->actingAs($user)->withRecentAuthentication()->delete(route('account.2fa.credential.destroy', $first))->assertSessionHasNoErrors();
        $this->assertNull($first->fresh());
    }

    // ── N12: abgewiesenes Gerät verbraucht keinen Wiederherstellungscode ──

    public function test_geraeteabweisung_verbraucht_den_wiederherstellungscode_nicht(): void {
        $this->officeOnly();
        $user = $this->employee();
        $this->grant($user, Permission::AuthRemote);
        $this->twoFactorUser($user, withPasskey: false);

        $this->withServerVariables(['REMOTE_ADDR' => self::HOME_IP]);
        $this->post('/login', ['username' => $user->email, 'password' => self::PASSWORD])->assertRedirect(route('two-factor.login'));
        $this->post(route('two-factor.login.attempt'), ['recovery_code' => 'aaaaa-bbbbb'])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertTrue(app(TwoFactorService::class)->matchesRecoveryCode($user->fresh() ?? $user, 'aaaaa-bbbbb'));
        $this->assertSame(1, UserKnownDevice::query()->where('user_id', $user->id)->whereNotNull('device_hash')->count());
    }

    // ── M1: das „Angemeldet bleiben“-Cookie ist keine frische Anmeldung ──

    public function test_remember_cookie_allein_loest_keinen_notaus_aus_und_entfernt_keinen_passkey(): void {
        $admin = $this->orgAdmin(['is_new_system' => true, 'password' => Hash::make(self::PASSWORD)]);
        $admin->forceFill(['remember_token' => 'merk-token-1234567890'])->save();
        $this->passkey($admin, 'cred-a');
        $this->passkey($admin, 'cred-b');
        $recaller = $admin->id . '|merk-token-1234567890|' . $admin->getAuthPassword();
        $cookie = Auth::guard('web')->getRecallerName();

        $this->withCookie($cookie, $recaller)->post(route('admin.login-policy.lockdown.store'), ['reason' => 'Cookie gestohlen'])
            ->assertSessionHasErrors('current_password');
        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(OrganizationLoginPolicy::query()->withoutGlobalScopes()->whereNotNull('lockdown_at')->exists());

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $credential = $admin->twoFactorCredentials()->firstOrFail();
        $this->withCookie($cookie, $recaller)->delete(route('account.2fa.credential.destroy', $credential))
            ->assertSessionHasErrors('credential');
        $this->assertNotNull($credential->fresh());
    }

    // ── M2: persönliche Tokens außerhalb nur mit auth.remote.token ────────

    public function test_token_ausserhalb_braucht_eigenes_recht(): void {
        $this->officeOnly();
        $user = $this->employee();
        $this->grant($user, Permission::AuthRemote);
        $token = $user->createToken('Skript', ['*'])->plainTextToken;

        $this->withServerVariables(['REMOTE_ADDR' => self::HOME_IP])->withToken($token)->getJson('/api/v1/me')
            ->assertForbidden()->assertJsonPath('reason', LoginPolicyReason::Network->value);

        $this->grant($user, Permission::AuthRemoteToken);
        \Illuminate\Support\Facades\Cache::flush();
        $this->app['auth']->forgetGuards();
        $this->withServerVariables(['REMOTE_ADDR' => self::HOME_IP])->withToken($token)->getJson('/api/v1/me')->assertOk();
    }

    // ── N1: Vorschau als Kunde läuft nicht an der Richtlinie vorbei ───────

    public function test_vorschau_als_kunde_folgt_der_richtlinie_der_mitarbeiterin(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $viewer = $this->employee();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($customer);
        $portalUser = User::factory()->kunde((int) $customer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id, 'email' => 'portal@example.test']);
        $this->grant($viewer, Permission::CustomerView, Permission::CustomerPortalAccessManage, Permission::CustomerPortalPreview);

        $this->actingAs($viewer)->withServerVariables(['REMOTE_ADDR' => self::OFFICE_IP])
            ->post(route('customers.portal-preview.start', [$customer, $portalUser]))
            ->assertRedirect(route('customer.dashboard'));
        $this->get(route('customer.dashboard'))->assertOk();

        $this->officeOnly();
        $this->withServerVariables(['REMOTE_ADDR' => self::HOME_IP])->get(route('customer.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    // ── N2: Support-Sitzung nur für den Plattformbetrieb ausgenommen ──────

    public function test_support_sitzung_gilt_als_person_die_impersoniert(): void {
        $this->officeOnly();
        $target = $this->employee();
        $support = $this->orgUser(['email' => 'support@example.test']);
        $grant = SupportAccessGrant::query()->create([
            'organization_id' => $this->organization->id,
            'granted_by_user_id' => $this->orgAdmin()->id,
            'granted_to_user_id' => null,
            'scope' => SupportAccessGrant::SCOPE_READ_ONLY,
            'purpose' => 'Testfall',
            'expires_at' => now()->addHour(),
        ]);
        $state = ['impersonator_id' => $support->id, 'grant_id' => $grant->id, 'started_at' => time()];

        // Impersonierende Person ohne auth.remote von außerhalb: Sitzung endet.
        $this->withSession([SupportImpersonationController::SESSION_KEY => $state])
            ->actingAs($target)->withServerVariables(['REMOTE_ADDR' => self::HOME_IP])
            ->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest('web');

        // Plattformbetrieb als impersonierende Person: ausgenommen.
        $operator = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        $this->flushSession();
        $this->withSession([SupportImpersonationController::SESSION_KEY => ['impersonator_id' => $operator->id, 'grant_id' => $grant->id, 'started_at' => time()]])
            ->actingAs($target)->withServerVariables(['REMOTE_ADDR' => self::HOME_IP])
            ->get(route('dashboard'))->assertOk();
    }

    // ── N5: Ablehnung bleibt, höchstens drei offene Anfragen ─────────────

    public function test_abgelehntes_geraet_fragt_nicht_erneut_an(): void {
        Notification::fake();
        $this->officeOnly();
        $admin = $this->orgAdmin();
        $user = $this->employee();
        $this->grant($user, Permission::AuthRemote);

        $cookie = $this->withServerVariables(['REMOTE_ADDR' => self::HOME_IP])
            ->post('/login', ['username' => $user->email, 'password' => self::PASSWORD])->getCookie(LoginDeviceCookie::NAME);
        $this->assertNotNull($cookie);
        $device = UserKnownDevice::query()->where('user_id', $user->id)->whereNotNull('device_hash')->sole();
        app(DeviceApprovalService::class)->reject($device, $admin);

        $this->withCookie(LoginDeviceCookie::NAME, (string) $cookie->getValue())->withServerVariables(['REMOTE_ADDR' => self::HOME_IP])
            ->post('/login', ['username' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors(['username' => __('login_policy.denied.device_rejected')]);
        $this->assertGuest();
        $this->assertNotNull($device->fresh()?->rejected_at);
        $this->assertSame(1, UserKnownDevice::query()->where('user_id', $user->id)->whereNotNull('device_hash')->count());
        Notification::assertSentToTimes($admin, GenericEventNotification::class, 1);
    }

    public function test_hoechstens_drei_offene_anfragen_je_person(): void {
        $user = $this->employee();
        $service = app(DeviceApprovalService::class);
        foreach (['a', 'b', 'c'] as $letter) {
            $this->assertNotNull($service->request($user, str_repeat($letter, 64), 'Firefox'));
        }

        $this->assertNull($service->request($user, str_repeat('d', 64), 'Firefox'));
        $this->assertSame(3, UserKnownDevice::query()->where('user_id', $user->id)->count());
        // Ein bekanntes Gerät bleibt erreichbar.
        $this->assertNotNull($service->request($user, str_repeat('a', 64), 'Firefox'));
    }

    // ── N11: SSO-Sitzungen fallen nicht unter die Passkey-Pflicht ────────

    public function test_passkey_pflicht_zwingt_sso_sitzungen_nicht_zur_einrichtung(): void {
        $this->policy(['passkey_roles' => ['user']]);
        $user = $this->employee();

        $this->actingAs($user)->get(route('account.profile.edit'))->assertRedirect(route('account.2fa.show'));
        $this->withSession(['login_policy.method' => 'sso'])->actingAs($user)->get(route('account.profile.edit'))->assertOk();
    }

    // ── M3/N8/Fehlerfall: Validierung der Richtlinie ─────────────────────

    public function test_validierung_weist_leeres_durchsetzen_alle_netze_und_zu_viele_namen_ab(): void {
        $admin = $this->orgAdmin();
        $names = implode("\n", array_map(static fn (int $i): string => "buero{$i}.example.org", range(1, 11)));

        $this->actingAs($admin)->put(route('admin.login-policy.update'), ['hours_mode' => 'enforce', 'hours' => [], 'network_mode' => 'enforce', 'networks' => ''])
            ->assertSessionHasErrors(['hours', 'networks']);
        $this->actingAs($admin)->put(route('admin.login-policy.update'), ['hours_mode' => 'off', 'network_mode' => 'log', 'networks' => "0.0.0.0/0\n::/0"])
            ->assertSessionHasErrors('networks');
        $this->actingAs($admin)->put(route('admin.login-policy.update'), ['hours_mode' => 'off', 'network_mode' => 'log', 'networks' => $names])
            ->assertSessionHasErrors('networks');
        $this->assertFalse(OrganizationLoginPolicy::query()->withoutGlobalScopes()->exists());
    }

    // ── N8/N9: Seite warnt bei interner eigener Adresse, kürzt Protokoll ─

    public function test_seite_warnt_bei_interner_adresse_und_kuerzt_ip_im_protokoll(): void {
        $admin = $this->orgAdmin();
        $employee = $this->employee();
        $this->officeOnly();
        app(SecurityEventLogger::class)->log(SecurityEventType::LoginPolicyDenied, [
            'ip' => '203.0.113.77', 'user' => $employee->email, 'user_id' => $employee->id,
            'organization_id' => $this->organization->id, 'reason' => LoginPolicyReason::Network->value,
        ]);

        $this->actingAs($admin)->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])->get(route('admin.login-policy.index'))
            ->assertOk()
            ->assertSee(__('login_policy.networks.client_internal'))
            ->assertSee('203.0.113.0/24')
            ->assertDontSee('203.0.113.77');

        $this->actingAs($admin)->withServerVariables(['REMOTE_ADDR' => self::OFFICE_IP])->get(route('admin.login-policy.index'))
            ->assertOk()->assertDontSee(__('login_policy.networks.client_internal'));
    }

    // ── M3: Speichern löst DNS nicht in der Anfrage, interne Adressen nicht angezeigt ──

    public function test_speichern_loest_dns_im_job_und_zeigt_keine_internen_adressen(): void {
        config(['security.login_policy.dns_allow_private' => true]);
        $this->app->instance(LoginNetworks::class, new class extends LoginNetworks {
            protected function lookup(string $host): array {
                return ['198.51.100.88', '10.20.30.40'];
            }
        });
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->put(route('admin.login-policy.update'), ['hours_mode' => 'off', 'network_mode' => 'log', 'networks' => 'buero.example.org'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['buero.example.org' => ['198.51.100.88', '10.20.30.40']], app(LoginNetworks::class)->resolvedHosts($this->organization->id));
        $this->actingAs($admin)->get(route('admin.login-policy.index'))
            ->assertOk()->assertSee('198.51.100.88')->assertDontSee('10.20.30.40');
    }

    // ── N10: offene MCP-Autorisierungscodes verfallen mit dem Notaus ─────

    public function test_notaus_verwirft_offene_mcp_codes(): void {
        $actor = $this->orgAdmin();
        $employee = $this->employee();
        $client = McpOAuthClient::query()->create(['client_key' => 'client-test', 'name' => 'Test', 'redirect_uris' => ['https://client.example.test/cb']]);
        McpOAuthCode::query()->create([
            'mcp_oauth_client_id' => $client->id, 'user_id' => $employee->id, 'code_hash' => str_repeat('e', 64),
            'redirect_uri' => 'https://client.example.test/cb', 'code_challenge' => str_repeat('x', 43), 'scopes' => ['mcp:read'], 'expires_at' => now()->addMinutes(10),
        ]);

        $this->actingAs($actor)->withRecentAuthentication()->post(route('admin.login-policy.lockdown.store'), ['reason' => 'Test'])->assertSessionHasNoErrors();

        $this->assertSame(0, McpOAuthCode::query()->where('user_id', $employee->id)->count());
    }
}
