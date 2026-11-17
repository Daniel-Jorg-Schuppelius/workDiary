<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginPolicyAdminTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Auth\{LoginPolicyMode, LoginPolicyReason};
use App\Enums\Security\SecurityEventType;
use App\Models\Audit\AuditLog;
use App\Models\Auth\{OrganizationLoginPolicy, UserKnownDevice};
use App\Models\Platform\{Organization, User};
use App\Services\Auth\LoginPolicy\LoginNetworks;
use App\Services\Security\SecurityEventLogger;
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 205: Verwaltungsseite der Anmelderichtlinie — Speichern mit
 * Prüfung der Netze und Zeiten, DNS-Auflösung beim Speichern, Gerätefreigaben
 * (vier Augen, Mandantengrenze), Protokoll „nur protokollieren“, Rechte.
 */
final class LoginPolicyAdminTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
        // DNS nie im Test: feste Auflösung.
        $this->app->instance(LoginNetworks::class, new class extends LoginNetworks {
            protected function lookup(string $host): array {
                return $host === 'buero.example.org' ? ['198.51.100.77'] : [];
            }
        });
    }

    private function device(User $user, string $hash, bool $approved = false): UserKnownDevice {
        return UserKnownDevice::query()->create([
            'user_id' => $user->id,
            'fingerprint' => $hash,
            'device_hash' => $hash,
            'label' => 'Firefox · Linux',
            'last_seen_at' => now(),
            'approval_requested_at' => now(),
            'approved_at' => $approved ? now() : null,
        ]);
    }

    public function test_admin_speichert_richtlinie_und_dns_namen_werden_aufgeloest(): void {
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->get(route('admin.login-policy.index'))->assertOk()->assertSee(__('login_policy.title.index'));
        $this->actingAs($admin)->get(route('admin.login-policy.edit'))->assertOk()->assertSee('name="networks"', false);

        $this->actingAs($admin)->put(route('admin.login-policy.update'), [
            'hours_mode' => LoginPolicyMode::Log->value,
            'hours' => [1 => ['from' => '07:00', 'until' => '19:00'], 2 => ['from' => '', 'until' => ''], 5 => ['from' => '22:00', 'until' => '06:00']],
            'hours_roles' => ['user', 'aussendienst'],
            'network_mode' => LoginPolicyMode::Enforce->value,
            'networks' => "198.51.100.0/24\n2001:DB8::/32, Buero.Example.org.\n198.51.100.0/24",
            'passkey_roles' => ['buchhaltung'],
        ])->assertRedirect(route('admin.login-policy.index'))->assertSessionHasNoErrors();

        $policy = OrganizationLoginPolicy::query()->withoutGlobalScopes()->where('organization_id', $this->organization->id)->sole();
        $this->assertSame(LoginPolicyMode::Log, $policy->hours_mode);
        $this->assertSame([1 => ['from' => '07:00', 'until' => '19:00'], 5 => ['from' => '22:00', 'until' => '06:00']], $policy->hoursByWeekday());
        $this->assertSame(['198.51.100.0/24', '2001:db8::/32', 'buero.example.org'], $policy->networkEntries());
        $this->assertSame(['buchhaltung'], $policy->passkeyRoleNames());
        $this->assertSame((int) $admin->id, $policy->updated_by);
        $this->assertSame(['buero.example.org' => ['198.51.100.77']], app(LoginNetworks::class)->resolvedHosts($this->organization->id));
        $this->assertTrue(app(LoginNetworks::class)->contains($this->organization->id, $policy->networkEntries(), '198.51.100.77'));
        $this->assertTrue(AuditLog::query()->where('auditable_id', $policy->id)->where('event', 'created')->exists());

        $this->actingAs($admin)->get(route('admin.login-policy.index'))->assertOk()->assertSee('198.51.100.77');
    }

    public function test_ungueltige_netze_und_zeiten_werden_abgewiesen(): void {
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->put(route('admin.login-policy.update'), [
            'hours_mode' => 'enforce',
            'hours' => [3 => ['from' => '25:00', 'until' => '18:00'], 4 => ['from' => '08:00', 'until' => '08:00'], 9 => ['from' => '08:00', 'until' => '09:00']],
            'network_mode' => 'enforce',
            'networks' => "10.0.0.0/33\nnicht gültig!\n2001:db8::/129",
            'hours_roles' => ['admin'],
            'passkey_roles' => ['kunde'],
        ])->assertSessionHasErrors(['hours.3', 'hours.4', 'hours', 'networks', 'hours_roles.0', 'passkey_roles.0']);

        $this->assertFalse(OrganizationLoginPolicy::query()->withoutGlobalScopes()->exists());
    }

    public function test_geraete_freigeben_ablehnen_entziehen(): void {
        $admin = $this->orgAdmin();
        $employee = $this->orgUser();
        $pending = $this->device($employee, str_repeat('a', 64));
        $approved = $this->device($employee, str_repeat('b', 64), true);

        $this->actingAs($admin)->get(route('admin.login-policy.index'))->assertOk()->assertSee('Firefox · Linux');

        $this->actingAs($admin)->post(route('admin.login-policy.devices.approve', ['deviceSqid' => Sqid::encode(UserKnownDevice::class, (int) $pending->id)]))
            ->assertRedirect(route('admin.login-policy.index'));
        $this->assertNotNull($pending->fresh()?->approved_at);
        $this->assertSame((int) $admin->id, $pending->refresh()->approved_by);
        $this->assertTrue(AuditLog::query()->where('event', 'login_policy.device_approved')->exists());

        // Entziehen: das Gerät bleibt als abgelehnt stehen (Sicherheitsprüfung N5).
        $approvedSqid = Sqid::encode(UserKnownDevice::class, (int) $approved->id);
        $this->actingAs($admin)->delete(route('admin.login-policy.devices.destroy', ['deviceSqid' => $approvedSqid]))
            ->assertSessionHasErrors('device');
        $this->actingAs($admin)->post(route('admin.login-policy.devices.reject', ['deviceSqid' => $approvedSqid]))
            ->assertRedirect(route('admin.login-policy.index'));
        $approved->refresh();
        $this->assertNull($approved->approved_at);
        $this->assertNotNull($approved->rejected_at);
        $this->assertSame((int) $admin->id, $approved->rejected_by);
        $this->assertTrue(AuditLog::query()->where('event', 'login_policy.device_revoked')->exists());

        // Vergessen: nur abgelehnte Geräte; danach darf das Gerät wieder anfragen.
        $this->actingAs($admin)->delete(route('admin.login-policy.devices.destroy', ['deviceSqid' => $approvedSqid]))
            ->assertRedirect(route('admin.login-policy.index'));
        $this->assertNull(UserKnownDevice::query()->find($approved->id));
        $this->assertTrue(AuditLog::query()->where('event', 'login_policy.device_forgotten')->exists());
    }

    public function test_eigenes_geraet_gibt_jemand_anderes_frei_und_fremde_geraete_gibt_es_nicht(): void {
        $admin = $this->orgAdmin();
        $own = $this->device($admin, str_repeat('c', 64));
        $this->actingAs($admin)->post(route('admin.login-policy.devices.approve', ['deviceSqid' => Sqid::encode(UserKnownDevice::class, (int) $own->id)]))
            ->assertSessionHasErrors('device');
        $this->assertNull($own->fresh()?->approved_at);

        $other = Organization::factory()->create();
        $stranger = User::factory()->user()->create(['organization_id' => $other->id]);
        $foreign = $this->device($stranger, str_repeat('d', 64));
        $this->actingAs($admin)->post(route('admin.login-policy.devices.approve', ['deviceSqid' => Sqid::encode(UserKnownDevice::class, (int) $foreign->id)]))
            ->assertNotFound();
        $this->actingAs($admin)->post(route('admin.login-policy.devices.reject', ['deviceSqid' => Sqid::encode(UserKnownDevice::class, (int) $foreign->id)]))
            ->assertNotFound();
        $this->actingAs($admin)->delete(route('admin.login-policy.devices.destroy', ['deviceSqid' => Sqid::encode(UserKnownDevice::class, (int) $foreign->id)]))
            ->assertNotFound();
        $this->assertNotNull($foreign->fresh());
    }

    public function test_protokoll_zeigt_wen_die_regel_ausgesperrt_haette_nur_der_eigenen_organisation(): void {
        $admin = $this->orgAdmin();
        $employee = $this->orgUser(['name' => 'Erika Beispiel']);
        $other = Organization::factory()->create();
        $stranger = User::factory()->user()->create(['organization_id' => $other->id, 'name' => 'Fremde Person']);
        $logger = app(SecurityEventLogger::class);
        $logger->log(SecurityEventType::LoginPolicyLogged, ['user' => $employee->email, 'user_id' => $employee->id, 'organization_id' => $this->organization->id, 'reason' => LoginPolicyReason::Network->value]);
        $logger->log(SecurityEventType::LoginPolicyLogged, ['user' => $stranger->email, 'user_id' => $stranger->id, 'organization_id' => $other->id, 'reason' => LoginPolicyReason::Network->value]);

        $this->actingAs($admin)->get(route('admin.login-policy.index'))
            ->assertOk()
            ->assertSee('Erika Beispiel')
            ->assertSee(LoginPolicyReason::Network->label())
            ->assertDontSee('Fremde Person');
    }

    public function test_rechte(): void {
        $employee = $this->orgUser();
        $this->actingAs($employee)->get(route('admin.login-policy.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('admin.login-policy.edit'))->assertForbidden();
        $this->actingAs($employee)->put(route('admin.login-policy.update'), ['hours_mode' => 'off', 'network_mode' => 'off'])->assertForbidden();

        // Geschäftsführung: Notaus ja, Richtlinie und Geräte nein.
        $managing = User::factory()->geschaeftsfuehrung()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($managing)->get(route('admin.login-policy.index'))->assertOk()->assertDontSee(__('login_policy.section.devices'));
        $this->actingAs($managing)->get(route('admin.login-policy.lockdown.create'))->assertOk();
        $this->actingAs($managing)->get(route('admin.login-policy.edit'))->assertForbidden();
    }

    public function test_passkey_pflicht_fuer_die_eigene_rolle_nur_mit_eigenem_passkey(): void {
        $admin = $this->orgAdmin();
        $payload = ['hours_mode' => 'off', 'network_mode' => 'off', 'passkey_roles' => ['admin']];

        $this->actingAs($admin)->put(route('admin.login-policy.update'), $payload)->assertSessionHasErrors('passkey_roles');

        $admin->twoFactorCredentials()->create(['type' => 'webauthn', 'label' => 'Laptop', 'credential_id' => 'cred-admin', 'data' => ['x' => 1], 'confirmed_at' => now()]);
        $this->actingAs($admin)->put(route('admin.login-policy.update'), $payload)->assertSessionHasNoErrors();
    }

    public function test_scheduler_loest_dns_namen_aktiver_netzregeln_auf(): void {
        $guard = new \App\Scheduling\Guards\LoginNetworksConfigured;
        $this->assertFalse($guard->shouldRun());

        $active = new OrganizationLoginPolicy;
        $active->forceFill(['organization_id' => $this->organization->id, 'network_mode' => LoginPolicyMode::Log, 'networks' => ['buero.example.org']])->save();
        $other = Organization::factory()->create();
        $inactive = new OrganizationLoginPolicy;
        $inactive->forceFill(['organization_id' => $other->id, 'network_mode' => LoginPolicyMode::Off, 'networks' => ['buero.example.org']])->save();
        $this->assertTrue($guard->shouldRun());

        $this->artisan('security:resolve-login-networks')->assertSuccessful();

        $this->assertSame(['buero.example.org' => ['198.51.100.77']], app(LoginNetworks::class)->resolvedHosts($this->organization->id));
        $this->assertSame([], app(LoginNetworks::class)->resolvedHosts($other->id));
    }
}
