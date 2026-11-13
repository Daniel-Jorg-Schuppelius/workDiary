<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Auth\{LoginPolicyMode, LoginPolicyReason};
use App\Enums\Notification\NotificationEvent;
use App\Enums\Security\SecurityEventType;
use App\Enums\User\Permission;
use App\Models\Auth\{OrganizationLoginPolicy, SecurityEvent, UserKnownDevice};
use App\Models\Platform\{Organization, User, UserBadge};
use App\Models\Time\AttendanceTerminal;
use App\Notifications\GenericEventNotification;
use App\Services\Auth\LoginPolicy\{DeviceApprovalService, LoginDeviceCookie};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, Notification};
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 205 (MVP-1654/1655): Anmeldezeiten und Büronetze mit freigegebenen
 * Geräten — Akzeptanzkriterien 1–3, Modus „nur protokollieren“, Ausnahmen
 * (Org-Admin, Plattformbetrieb, Portal), persönliche API-Tokens,
 * Stempelterminals und die Trennung der Organisationen.
 */
final class LoginPolicyTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string PASSWORD = 'Sicher!2026Pass';

    private const string OFFICE_IP = '198.51.100.20';

    private const string HOME_IP = '203.0.113.9';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
    }

    /** @param array<string, mixed> $attributes */
    private function policy(array $attributes, ?Organization $organization = null): OrganizationLoginPolicy {
        $policy = new OrganizationLoginPolicy;
        $policy->forceFill(array_merge(['organization_id' => ($organization ?? $this->organization)->id], $attributes))->save();

        return $policy;
    }

    private function officeOnly(LoginPolicyMode $mode = LoginPolicyMode::Enforce): OrganizationLoginPolicy {
        return $this->policy(['network_mode' => $mode, 'networks' => ['198.51.100.0/24', '2001:db8:10::/48']]);
    }

    private function employee(string $email = 'mitarbeiterin@example.test'): User {
        return $this->orgUser(['email' => $email, 'is_new_system' => true, 'password' => Hash::make(self::PASSWORD)]);
    }

    private function grant(User $user, Permission $permission): void {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->organization->id);
        $user->givePermissionTo($permission->value);
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('permissions');
    }

    /** @return \Illuminate\Testing\TestResponse<\Illuminate\Http\Response> */
    private function login(User $user, string $ip): \Illuminate\Testing\TestResponse {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/login', ['username' => $user->email, 'password' => self::PASSWORD]);
    }

    // ── Akzeptanzkriterium 1: Netz ohne auth.remote ───────────────────────

    public function test_ohne_auth_remote_nur_aus_dem_bueronetz(): void {
        $this->officeOnly();
        $user = $this->employee();

        $this->login($user, self::HOME_IP)->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->assertTrue(SecurityEvent::query()
            ->where('event', SecurityEventType::LoginPolicyDenied->value)
            ->where('user_id', $user->id)
            ->where('organization_id', $this->organization->id)
            ->exists());

        $this->login($user, self::OFFICE_IP)->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_ipv6_und_ipv4_in_ipv6_huelle_werden_erkannt(): void {
        $this->officeOnly();
        $user = $this->employee();

        $this->login($user, '2001:db8:10::abcd')->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_laufende_sitzung_endet_beim_verlassen_des_bueronetzes(): void {
        $this->officeOnly();
        $user = $this->employee();

        $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => self::OFFICE_IP])
            ->get(route('account.profile.edit'))->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => self::HOME_IP])
            ->get(route('account.profile.edit'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // ── Akzeptanzkriterium 2: auth.remote mit Gerätefreigabe ──────────────

    public function test_auth_remote_mit_neuem_geraet_stellt_freigabeanfrage_und_gelingt_nach_freigabe(): void {
        Notification::fake();
        $this->officeOnly();
        $admin = $this->orgAdmin();
        $user = $this->employee();
        $this->grant($user, Permission::AuthRemote);

        $response = $this->login($user, self::HOME_IP)->assertSessionHasErrors('username');
        $this->assertGuest();

        $device = UserKnownDevice::query()->where('user_id', $user->id)->whereNotNull('device_hash')->sole();
        $this->assertNotNull($device->approval_requested_at);
        $this->assertNull($device->approved_at);
        Notification::assertSentTo($admin, GenericEventNotification::class, static fn (GenericEventNotification $n): bool => $n->event === NotificationEvent::AuthDeviceApprovalRequested);

        $cookie = $response->getCookie(LoginDeviceCookie::NAME);
        $this->assertNotNull($cookie);

        // Erneuter Versuch vom selben Gerät: keine zweite Anfrage, keine zweite Nachricht.
        $this->withCookie(LoginDeviceCookie::NAME, (string) $cookie->getValue())->login($user, self::HOME_IP)->assertSessionHasErrors('username');
        $this->assertSame(1, UserKnownDevice::query()->where('user_id', $user->id)->whereNotNull('device_hash')->count());
        Notification::assertSentToTimes($admin, GenericEventNotification::class, 1);

        app(DeviceApprovalService::class)->approve($device, $admin);

        $this->withCookie(LoginDeviceCookie::NAME, (string) $cookie->getValue())->login($user, self::HOME_IP)->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_gefaelschtes_oder_fremdes_geraetecookie_zaehlt_nicht(): void {
        $this->officeOnly();
        $admin = $this->orgAdmin();
        $user = $this->employee();
        $this->grant($user, Permission::AuthRemote);

        $cookie = $this->login($user, self::HOME_IP)->getCookie(LoginDeviceCookie::NAME);
        $this->assertNotNull($cookie);
        $device = UserKnownDevice::query()->where('user_id', $user->id)->whereNotNull('device_hash')->sole();
        app(DeviceApprovalService::class)->approve($device, $admin);

        // Signatur verändert: das Gerät ist unbekannt.
        $tampered = substr((string) $cookie->getValue(), 0, -2) . 'xx';
        $this->withCookie(LoginDeviceCookie::NAME, $tampered)->login($user, self::HOME_IP)->assertSessionHasErrors('username');
        $this->assertGuest();

        // Dasselbe Cookie gilt nicht für eine andere Person.
        $other = $this->employee('kollege@example.test');
        $this->grant($other, Permission::AuthRemote);
        $this->withCookie(LoginDeviceCookie::NAME, (string) $cookie->getValue())->login($other, self::HOME_IP)->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_freigabeanfrage_entsteht_erst_nach_dem_zweiten_faktor(): void {
        $this->officeOnly();
        $user = $this->employee();
        $this->grant($user, Permission::AuthRemote);
        $user->twoFactorCredentials()->create(['type' => 'email', 'label' => $user->email, 'confirmed_at' => now()]);

        // Nur das Passwort: weiter zum zweiten Faktor, noch keine Anfrage an die Admins.
        $this->login($user, self::HOME_IP)->assertRedirect(route('two-factor.login'));
        $this->assertSame(0, UserKnownDevice::query()->where('user_id', $user->id)->whereNotNull('device_hash')->count());
    }

    // ── Akzeptanzkriterium 3: Zeitfenster ─────────────────────────────────

    public function test_sitzung_endet_mit_dem_fensterende_org_admin_bleibt(): void {
        $windows = [];
        for ($day = 1; $day <= 7; $day++) {
            $windows[$day] = ['from' => '07:00', 'until' => '22:00'];
        }
        $this->policy(['hours_mode' => LoginPolicyMode::Enforce, 'hours' => $windows]);
        $user = $this->employee();
        $admin = $this->orgAdmin();

        // Montag, 16.11.2026, 21:59 Ortszeit Berlin.
        $this->travelTo(CarbonImmutable::parse('2026-11-16 21:59:00', 'Europe/Berlin'));
        $this->actingAs($user)->get(route('account.profile.edit'))->assertOk();

        $this->travelTo(CarbonImmutable::parse('2026-11-16 22:00:00', 'Europe/Berlin'));
        $this->get(route('account.profile.edit'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->assertStringContainsString('07:00', (string) session('errors')?->first('username'));

        $this->actingAs($admin)->get(route('account.profile.edit'))->assertOk();
    }

    public function test_anmeldung_ausserhalb_des_fensters_nennt_die_naechste_oeffnung(): void {
        $this->policy(['hours_mode' => LoginPolicyMode::Enforce, 'hours' => [1 => ['from' => '08:00', 'until' => '17:00']]]);
        $user = $this->employee();

        // Samstag: nächste Öffnung Montag 08:00.
        $this->travelTo(CarbonImmutable::parse('2026-11-14 10:00:00', 'Europe/Berlin'));
        $this->login($user, self::OFFICE_IP)->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->assertStringContainsString('16.11.2026 08:00', (string) session('errors')?->first('username'));
    }

    public function test_zeitfenster_gilt_nur_fuer_ausgewaehlte_rollen(): void {
        $this->policy(['hours_mode' => LoginPolicyMode::Enforce, 'hours' => [], 'hours_roles' => ['aussendienst']]);
        $user = $this->employee();

        $this->login($user, self::OFFICE_IP)->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    // ── Nur protokollieren ────────────────────────────────────────────────

    public function test_nur_protokollieren_laesst_durch_und_zeichnet_auf(): void {
        $this->officeOnly(LoginPolicyMode::Log);
        $user = $this->employee();

        $this->login($user, self::HOME_IP)->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $event = SecurityEvent::query()->where('event', SecurityEventType::LoginPolicyLogged->value)->where('user_id', $user->id)->sole();
        $this->assertSame(LoginPolicyReason::Network->value, $event->meta['reason'] ?? null);

        // Folgeanfragen derselben Sitzung protokollieren nicht erneut.
        $this->withServerVariables(['REMOTE_ADDR' => self::HOME_IP])->get(route('account.profile.edit'))->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => self::HOME_IP])->get(route('account.profile.edit'))->assertOk();
        $this->assertSame(1, SecurityEvent::query()->where('event', SecurityEventType::LoginPolicyLogged->value)->where('user_id', $user->id)->count());
    }

    // ── Ausnahmen ─────────────────────────────────────────────────────────

    public function test_org_admin_und_plattformbetrieb_sind_ausgenommen(): void {
        // Nie geöffnet und nur das Büronetz — trifft beide trotzdem nicht.
        $this->policy(['hours_mode' => LoginPolicyMode::Enforce, 'hours' => [], 'network_mode' => LoginPolicyMode::Enforce, 'networks' => ['198.51.100.0/24']]);

        $admin = $this->orgAdmin(['email' => 'admin@example.test', 'is_new_system' => true, 'password' => Hash::make(self::PASSWORD)]);
        $this->login($admin, self::HOME_IP)->assertRedirect();
        $this->assertAuthenticatedAs($admin);

        $operator = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        // Frische Sitzung: die vorige trägt den Passwort-Hash des Admins (AuthenticateSession).
        $this->flushSession();
        $this->actingAs($operator)->withServerVariables(['REMOTE_ADDR' => self::HOME_IP])->get(route('account.profile.edit'))->assertOk();
    }

    // ── Persönliche API-Tokens und Stempelterminals ───────────────────────

    public function test_persoenliches_token_folgt_der_richtlinie(): void {
        $this->officeOnly();
        $user = $this->employee();
        $token = $user->createToken('Skript', ['*'])->plainTextToken;

        $this->withServerVariables(['REMOTE_ADDR' => self::HOME_IP])->withToken($token)->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('reason', LoginPolicyReason::Network->value);

        $this->app['auth']->forgetGuards();
        $this->withServerVariables(['REMOTE_ADDR' => self::OFFICE_IP])->withToken($token)->getJson('/api/v1/me')->assertOk();
    }

    public function test_stempelterminal_laeuft_ausserhalb_des_fensters_weiter(): void {
        $this->policy(['hours_mode' => LoginPolicyMode::Enforce, 'hours' => [], 'network_mode' => LoginPolicyMode::Enforce, 'networks' => ['198.51.100.0/24']]);
        $user = $this->employee();
        [, $token] = AttendanceTerminal::issue($this->organization->id, 'Halle Nord');
        UserBadge::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'label' => 'Test',
            'badge_hash' => UserBadge::hashBadge('AB12-CD34'),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => self::HOME_IP])
            ->postJson('/api/terminal/ingest/' . $token, ['badge_uid' => 'AB12-CD34'])
            ->assertOk()
            ->assertJsonPath('status', 'clocked_in');
    }

    // ── Andere Organisationen ─────────────────────────────────────────────

    public function test_richtlinie_gilt_nur_fuer_die_eigene_organisation(): void {
        $this->officeOnly();
        $other = Organization::factory()->create(['timezone' => 'Europe/Berlin']);
        $stranger = User::factory()->user()->create([
            'organization_id' => $other->id,
            'email' => 'fremd@example.test',
            'is_new_system' => true,
            'password' => Hash::make(self::PASSWORD),
        ]);

        $this->login($stranger, self::HOME_IP)->assertRedirect();
        $this->assertAuthenticatedAs($stranger);
    }
}
