<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportAccessGrantTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Support;

use App\Models\{AuditLog, SupportAccessGrant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Rang 64: Temporäre Supportfreigabe + Impersonation-Lifecycle — Freigabe
 * nur durch Admin, Impersonation nur bei aktiver Freigabe, harte Sperrliste
 * (Passwort/2FA/Tokens/Export), read_only blockt Schreibaktionen, Ablauf/
 * Widerruf beendet die Sitzung sofort, alles auditiert (support.%).
 */
final class SupportAccessGrantTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $target;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->target = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    private function grant(string $scope = SupportAccessGrant::SCOPE_FULL): SupportAccessGrant {
        return SupportAccessGrant::query()->create([
            'organization_id' => $this->organization->id,
            'granted_by_user_id' => $this->admin->id,
            'scope' => $scope,
            'purpose' => 'Ticket #4711',
            'expires_at' => now()->addDay(),
        ]);
    }

    public function test_grant_management_requires_permission(): void {
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($plain)->get(route('admin.support.grants.index'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('admin.support.grants.index'))->assertOk();
    }

    public function test_grant_and_revoke_are_audited(): void {
        $this->actingAs($this->admin)->post(route('admin.support.grants.store'), [
            'scope' => 'read_only',
            'purpose' => 'Ticket #99 — Analyse',
            'duration_hours' => 24,
        ])->assertRedirect(route('admin.support.grants.index'));

        $grant = SupportAccessGrant::query()->firstOrFail();
        $this->assertTrue($grant->isActive());
        $this->assertSame(1, AuditLog::query()->where('event', 'support.access.granted')->count());

        $this->actingAs($this->admin)->post(route('admin.support.grants.revoke', $grant));
        $this->assertFalse($grant->refresh()->isActive());
        $this->assertSame('manual', $grant->revoked_reason);
        $this->assertSame(1, AuditLog::query()->where('event', 'support.access.revoked')->count());
    }

    public function test_impersonation_requires_active_grant(): void {
        $this->actingAs($this->admin)
            ->post(route('admin.support.impersonate.start', $this->target))
            ->assertForbidden();

        $this->grant();

        $this->actingAs($this->admin)
            ->post(route('admin.support.impersonate.start', $this->target))
            ->assertRedirect(route('today.show'));

        $this->assertAuthenticatedAs($this->target);
        $this->assertSame(1, AuditLog::query()->where('event', 'support.impersonation.start')->count());

        // Banner sichtbar.
        $this->get(route('today.show'))->assertOk()->assertSee('data-support-banner', false);
    }

    public function test_admin_target_is_rejected(): void {
        $this->grant();
        $otherAdmin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.support.impersonate.start', $otherAdmin))
            ->assertForbidden();
    }

    public function test_blocked_routes_and_write_audit_during_impersonation(): void {
        $this->grant();
        $this->actingAs($this->admin)->post(route('admin.support.impersonate.start', $this->target));

        // Harte Sperrliste: Passwort-Änderung + API-Tokens.
        $this->get(route('account.password.edit'))->assertForbidden();
        $this->get(route('profile.api-tokens.index'))->assertForbidden();

        // Erlaubte Schreibaktion wird als support.session.action auditiert.
        $this->post(route('today.quick-book'), []);
        $this->assertGreaterThanOrEqual(1, AuditLog::query()->where('event', 'support.session.action')->count());
    }

    public function test_read_only_scope_blocks_writes(): void {
        $this->grant(SupportAccessGrant::SCOPE_READ_ONLY);
        $this->actingAs($this->admin)->post(route('admin.support.impersonate.start', $this->target));

        $this->get(route('today.show'))->assertOk();
        $this->post(route('today.quick-book'), [])->assertForbidden();
    }

    public function test_revocation_ends_session_immediately(): void {
        $grant = $this->grant();
        $this->actingAs($this->admin)->post(route('admin.support.impersonate.start', $this->target));
        $this->assertAuthenticatedAs($this->target);

        $grant->update(['revoked_at' => now(), 'revoked_reason' => 'manual']);

        $this->get(route('today.show'))->assertRedirect(route('today.show'));
        $this->assertAuthenticatedAs($this->admin);
        $this->assertFalse(session()->has(\App\Http\Controllers\Admin\SupportImpersonationController::SESSION_KEY));
        $this->assertSame(1, AuditLog::query()->where('event', 'support.impersonation.stop')->count());
    }

    public function test_manual_stop_returns_to_support_account(): void {
        $this->grant();
        $this->actingAs($this->admin)->post(route('admin.support.impersonate.start', $this->target));

        $this->post(route('admin.support.impersonate.stop'))->assertRedirect(route('today.show'));

        $this->assertAuthenticatedAs($this->admin);
        $this->assertSame(1, AuditLog::query()->where('event', 'support.impersonation.stop')->count());
    }
}
