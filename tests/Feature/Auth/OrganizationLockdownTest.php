<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationLockdownTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Security\SecurityEventType;
use App\Models\Audit\AuditLog;
use App\Models\Auth\{OrganizationLoginPolicy, SecurityEvent};
use App\Models\Platform\{Organization, User, UserBadge};
use App\Models\Time\AttendanceTerminal;
use App\Notifications\GenericEventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash, Notification};
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 205 (MVP-1657): Notaus je Organisation — Akzeptanzkriterium 5,
 * erneute Anmeldung, Ausnahmen für Org-Admins und Inhaber des Rechts,
 * Aufheben durch die Organisation und den Plattformbetrieb, andere
 * Organisationen und Stempelterminals unberührt.
 */
final class OrganizationLockdownTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string PASSWORD = 'Sicher!2026Pass';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
    }

    /** @param array<string, mixed> $attributes */
    private function member(array $attributes = []): User {
        return $this->orgUser(array_merge(['is_new_system' => true, 'password' => Hash::make(self::PASSWORD)], $attributes));
    }

    private function storedSession(User $user, string $id): void {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'ip_address' => '198.51.100.1', 'user_agent' => 'Test', 'payload' => '', 'last_activity' => time()]);
    }

    /** @return \Illuminate\Testing\TestResponse<\Illuminate\Http\Response> */
    private function engage(User $actor, string $reason = 'Zugangsdaten abgeflossen'): \Illuminate\Testing\TestResponse {
        return $this->actingAs($actor)->withRecentAuthentication()->post(route('admin.login-policy.lockdown.store'), ['reason' => $reason]);
    }

    public function test_notaus_beendet_sitzungen_und_tokens_nur_der_eigenen_organisation(): void {
        Notification::fake();
        config(['session.driver' => 'database']);

        $actor = $this->orgAdmin(['is_new_system' => true, 'password' => Hash::make(self::PASSWORD)]);
        $secondAdmin = $this->orgAdmin();
        $employee = $this->member(['remember_token' => 'merken-1']);
        $other = Organization::factory()->create();
        $stranger = User::factory()->user()->create(['organization_id' => $other->id, 'remember_token' => 'merken-2']);
        $operator = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);

        $this->storedSession($employee, 'sitzung-mitarbeiterin');
        $this->storedSession($secondAdmin, 'sitzung-admin-2');
        $this->storedSession($stranger, 'sitzung-fremd');
        $this->storedSession($operator, 'sitzung-betrieb');
        $employee->createToken('Skript');
        $actor->createToken('Eigenes Skript');
        $stranger->createToken('Fremdes Skript');

        $this->engage($actor)->assertRedirect(route('admin.login-policy.index'))->assertSessionHasNoErrors();

        $this->assertFalse(DB::table('sessions')->whereIn('id', ['sitzung-mitarbeiterin', 'sitzung-admin-2'])->exists());
        $this->assertTrue(DB::table('sessions')->where('id', 'sitzung-fremd')->exists());
        $this->assertTrue(DB::table('sessions')->where('id', 'sitzung-betrieb')->exists());
        $this->assertSame(0, PersonalAccessToken::query()->whereIn('tokenable_id', [$employee->id, $actor->id])->count());
        $this->assertSame(1, PersonalAccessToken::query()->where('tokenable_id', $stranger->id)->count());
        $this->assertNull($employee->fresh()?->remember_token);
        $this->assertSame('merken-2', $stranger->fresh()?->remember_token);

        $policy = OrganizationLoginPolicy::query()->withoutGlobalScopes()->where('organization_id', $this->organization->id)->sole();
        $this->assertTrue($policy->isLockedDown());
        $this->assertSame('Zugangsdaten abgeflossen', $policy->lockdown_reason);
        $this->assertSame((int) $actor->id, $policy->lockdown_by);
        $this->assertTrue(AuditLog::query()->where('event', 'login_policy.lockdown_engaged')->exists());
        $this->assertTrue(SecurityEvent::query()->where('event', SecurityEventType::OrganizationLockdown->value)->where('organization_id', $this->organization->id)->exists());
        foreach ([$actor, $secondAdmin] as $admin) {
            Notification::assertSentTo($admin, GenericEventNotification::class, static fn (GenericEventNotification $n): bool => $n->event === NotificationEvent::SecurityOrganizationLockdown);
        }
        Notification::assertNotSentTo($employee, GenericEventNotification::class);

        // Die auslösende Sitzung läuft weiter.
        $this->get(route('admin.login-policy.index'))->assertOk();
    }

    public function test_nach_dem_notaus_nur_org_admins_und_inhaber_des_rechts(): void {
        $actor = $this->orgAdmin();
        $employee = $this->member(['email' => 'mitarbeiterin@example.test']);
        $secondAdmin = $this->orgAdmin(['email' => 'admin2@example.test', 'is_new_system' => true, 'password' => Hash::make(self::PASSWORD)]);
        $managing = User::factory()->geschaeftsfuehrung()->create(['organization_id' => $this->organization->id, 'email' => 'gf@example.test', 'is_new_system' => true, 'password' => Hash::make(self::PASSWORD)]);
        $this->engage($actor)->assertSessionHasNoErrors();

        // Laufende Sitzung der Mitarbeiterin (ohne Speicher-Löschung) endet bei der nächsten Anfrage.
        $this->flushSession();
        $this->actingAs($employee)->get(route('account.profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();

        // Neue Anmeldung: Mitarbeiterin nein, Org-Admin und Geschäftsführung (Recht „Notaus“) ja.
        $this->post('/login', ['username' => 'mitarbeiterin@example.test', 'password' => self::PASSWORD])->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->post('/login', ['username' => 'admin2@example.test', 'password' => self::PASSWORD])->assertRedirect();
        $this->assertAuthenticatedAs($secondAdmin);
        $this->get(route('account.profile.edit'))->assertOk();

        $this->post(route('logout'));
        $this->post('/login', ['username' => 'gf@example.test', 'password' => self::PASSWORD])->assertRedirect();
        $this->assertAuthenticatedAs($managing);
    }

    public function test_aeltere_sitzung_eines_org_admins_endet_ebenfalls(): void {
        $actor = $this->orgAdmin();
        $secondAdmin = $this->orgAdmin();
        $this->engage($actor)->assertSessionHasNoErrors();

        // Sitzung von vor dem Notaus (Anmeldestempel älter): endet, Neuanmeldung bleibt möglich.
        $this->flushSession();
        $this->withSession(['login_policy.authenticated_at' => time() - 3600])
            ->actingAs($secondAdmin)
            ->get(route('account.profile.edit'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_notaus_verlangt_erneute_anmeldung_und_grund(): void {
        $actor = $this->orgAdmin(['is_new_system' => true, 'password' => Hash::make(self::PASSWORD)]);

        $this->actingAs($actor)->post(route('admin.login-policy.lockdown.store'), ['reason' => 'Test'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($actor)->post(route('admin.login-policy.lockdown.store'), ['reason' => 'Test', 'current_password' => 'falsch'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($actor)->withRecentAuthentication()->post(route('admin.login-policy.lockdown.store'), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertFalse(OrganizationLoginPolicy::query()->withoutGlobalScopes()->whereNotNull('lockdown_at')->exists());

        $this->actingAs($actor)->post(route('admin.login-policy.lockdown.store'), ['reason' => 'Laptop gestohlen', 'current_password' => self::PASSWORD])
            ->assertSessionHasNoErrors();
        $this->assertTrue(OrganizationLoginPolicy::query()->withoutGlobalScopes()->whereNotNull('lockdown_at')->exists());
    }

    public function test_ohne_recht_kein_notaus(): void {
        $employee = $this->member();

        $this->actingAs($employee)->withRecentAuthentication()->post(route('admin.login-policy.lockdown.store'), ['reason' => 'Test'])->assertForbidden();
        $this->actingAs($employee)->get(route('admin.login-policy.index'))->assertForbidden();
    }

    public function test_aufheben_durch_org_admin_und_plattformbetrieb(): void {
        $actor = $this->orgAdmin();
        $employee = $this->member(['email' => 'mitarbeiterin@example.test']);
        $this->engage($actor)->assertSessionHasNoErrors();

        $this->actingAs($actor)->withRecentAuthentication()->delete(route('admin.login-policy.lockdown.destroy'))->assertSessionHasNoErrors();
        $this->assertFalse(OrganizationLoginPolicy::query()->withoutGlobalScopes()->where('organization_id', $this->organization->id)->sole()->isLockedDown());
        $this->assertTrue(AuditLog::query()->where('event', 'login_policy.lockdown_lifted')->exists());

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->post('/login', ['username' => 'mitarbeiterin@example.test', 'password' => self::PASSWORD])->assertRedirect();
        $this->assertAuthenticatedAs($employee);

        // Erneut auslösen, dann hebt der Plattformbetrieb über die Organisationsverwaltung auf.
        $this->post(route('logout'));
        $this->flushSession();
        $this->engage($actor)->assertSessionHasNoErrors();
        // Org-Admin mit frischer Anmeldung nach dem Notaus: der Plattformweg bleibt ihm verschlossen.
        $this->flushSession();
        $this->withSession(['login_policy.authenticated_at' => time() + 5])->actingAs($actor)->withRecentAuthentication()
            ->delete(route('admin.organizations.lockdown.destroy', $this->organization))->assertForbidden();

        $this->flushSession();
        $operator = User::factory()->platformAdmin()->create(['organization_id' => Organization::factory()->create()->id]);
        $this->actingAs($operator)->withRecentAuthentication()->delete(route('admin.organizations.lockdown.destroy', $this->organization))->assertRedirect();
        $this->assertFalse(OrganizationLoginPolicy::query()->withoutGlobalScopes()->where('organization_id', $this->organization->id)->sole()->isLockedDown());
    }

    public function test_stempelterminal_laeuft_im_notaus_weiter(): void {
        $actor = $this->orgAdmin();
        $employee = $this->member();
        [, $token] = AttendanceTerminal::issue($this->organization->id, 'Empfang');
        UserBadge::query()->create(['organization_id' => $this->organization->id, 'user_id' => $employee->id, 'label' => 'Test', 'badge_hash' => UserBadge::hashBadge('EF56-GH78')]);
        $this->engage($actor)->assertSessionHasNoErrors();

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/terminal/ingest/' . $token, ['badge_uid' => 'EF56-GH78'])->assertOk()->assertJsonPath('status', 'clocked_in');
    }
}
