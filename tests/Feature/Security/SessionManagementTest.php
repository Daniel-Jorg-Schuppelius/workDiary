<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SessionManagementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\{AttendanceTerminal, LocationDeviceToken, RemotePendingSession, User};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin-Sitzungsverwaltung / Fernabmeldung (Feature 085): Zugriffsschutz,
 * org-gescoptes Auflisten, Widerruf einzelner Sitzungen, aller Geräte eines
 * Nutzers und von API-Tokens — inkl. Cross-Tenant-Isolation und Audit.
 */
class SessionManagementTest extends TestCase {
    use RefreshDatabase;

    public function test_index_requires_authentication(): void {
        $this->get(route('admin.sessions.index'))->assertRedirect(route('login'));
    }

    public function test_index_forbidden_for_regular_user(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('admin.sessions.index'))->assertForbidden();
    }

    public function test_index_lists_member_session_for_admin(): void {
        config(['session.driver' => 'database']);

        $admin = User::factory()->admin()->create();
        $member = User::factory()->user()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Erika Musterfrau',
        ]);
        $this->seedSession($member->id);

        $this->actingAs($admin)
            ->get(route('admin.sessions.index'))
            ->assertOk()
            ->assertSee(__('sessions.title.index'))
            ->assertSee('Erika Musterfrau');
    }

    public function test_index_shows_readable_device_label_from_user_agent(): void {
        config(['session.driver' => 'database']);

        $admin = User::factory()->admin()->create();
        $member = User::factory()->user()->create(['organization_id' => $admin->organization_id]);
        $this->seedSession($member->id); // seedSession nutzt einen Chrome/Windows-UA

        $this->actingAs($admin)
            ->get(route('admin.sessions.index'))
            ->assertOk()
            ->assertSee('Chrome · Windows'); // via CommonToolkit\UserAgentHelper::shortLabel()
    }

    public function test_data_endpoint_returns_totals_for_admin_and_is_gated(): void {
        config(['session.driver' => 'database']);

        $admin = User::factory()->admin()->create();
        $member = User::factory()->user()->create(['organization_id' => $admin->organization_id]);
        $this->seedSession($member->id);

        $this->actingAs($admin)
            ->getJson(route('admin.sessions.data'))
            ->assertOk()
            ->assertJsonStructure(['totals' => ['users', 'online', 'sessions', 'tokens'], 'available']);

        $user = User::factory()->user()->create();
        $this->actingAs($user)->getJson(route('admin.sessions.data'))->assertForbidden();
    }

    public function test_admin_can_revoke_single_session_and_audit_is_written(): void {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->user()->create(['organization_id' => $admin->organization_id]);
        $sessionId = $this->seedSession($member->id);

        $this->actingAs($admin)
            ->from(route('admin.sessions.index'))
            ->delete(route('admin.sessions.destroy', ['id' => $sessionId]))
            ->assertRedirect(route('admin.sessions.index'));

        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'session.revoked',
        ]);
    }

    public function test_revoke_requires_permission(): void {
        $user = User::factory()->user()->create();
        $sessionId = $this->seedSession($user->id);

        $this->actingAs($user)
            ->from(route('admin.sessions.index'))
            ->delete(route('admin.sessions.destroy', ['id' => $sessionId]))
            ->assertForbidden();

        $this->assertDatabaseHas('sessions', ['id' => $sessionId]);
    }

    public function test_admin_cannot_revoke_session_of_other_organization(): void {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->user()->create(); // andere Org via Factory-Default
        $sessionId = $this->seedSession($other->id);

        $this->actingAs($admin)
            ->delete(route('admin.sessions.destroy', ['id' => $sessionId]))
            ->assertNotFound();

        $this->assertDatabaseHas('sessions', ['id' => $sessionId]);
    }

    public function test_revoke_all_devices_clears_sessions_and_rotates_remember_token(): void {
        // Serverseitige Session-Löschung greift nur beim database-Treiber.
        config(['session.driver' => 'database']);

        $admin = User::factory()->admin()->create();
        $member = User::factory()->user()->create([
            'organization_id' => $admin->organization_id,
            'remember_token' => 'original-token-value',
        ]);
        $this->seedSession($member->id);
        $this->seedSession($member->id);

        $this->actingAs($admin)
            ->from(route('admin.sessions.index'))
            ->delete(route('admin.sessions.user.destroy', ['userSqid' => Sqid::encode(User::class, $member->id)]))
            ->assertRedirect(route('admin.sessions.index'));

        $this->assertDatabaseMissing('sessions', ['user_id' => $member->id]);
        $this->assertNotSame('original-token-value', $member->fresh()->remember_token);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'user.sessions.revoked_all',
        ]);
    }

    public function test_admin_cannot_revoke_all_for_other_organization(): void {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->user()->create();
        $this->seedSession($other->id);

        $this->actingAs($admin)
            ->delete(route('admin.sessions.user.destroy', ['userSqid' => Sqid::encode(User::class, $other->id)]))
            ->assertNotFound();

        $this->assertDatabaseHas('sessions', ['user_id' => $other->id]);
    }

    public function test_admin_can_revoke_api_token_of_member(): void {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->user()->create(['organization_id' => $admin->organization_id]);
        $tokenId = $member->createToken('Mobile', ['diary:read'])->accessToken->getKey();

        $this->actingAs($admin)
            ->from(route('admin.sessions.index'))
            ->delete(route('admin.sessions.tokens.destroy', ['tokenSqid' => Sqid::encode(\Laravel\Sanctum\PersonalAccessToken::class, $tokenId)]))
            ->assertRedirect(route('admin.sessions.index'));

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'event' => 'token.revoked',
        ]);
    }

    public function test_admin_cannot_revoke_token_of_other_organization(): void {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->user()->create();
        $tokenId = $other->createToken('Foreign')->accessToken->getKey();

        $this->actingAs($admin)
            ->delete(route('admin.sessions.tokens.destroy', ['tokenSqid' => Sqid::encode(\Laravel\Sanctum\PersonalAccessToken::class, $tokenId)]))
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);
    }

    // ===== Phase 3: Standort-Geräte =====

    public function test_index_lists_location_device_and_admin_can_revoke(): void {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->user()->create(['organization_id' => $admin->organization_id]);
        $deviceId = $this->seedLocationDevice($admin->organization_id, $member->id, 'Firmenhandy');

        $this->actingAs($admin)->get(route('admin.sessions.index'))->assertOk()->assertSee('Firmenhandy');

        $this->actingAs($admin)
            ->from(route('admin.sessions.index'))
            ->delete(route('admin.sessions.devices.destroy', ['deviceSqid' => Sqid::encode(LocationDeviceToken::class, $deviceId)]))
            ->assertRedirect(route('admin.sessions.index'));

        $this->assertNotNull(DB::table('location_device_tokens')->where('id', $deviceId)->value('revoked_at'));
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'event' => 'device.revoked']);
    }

    public function test_admin_cannot_revoke_location_device_of_other_organization(): void {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->user()->create();
        $deviceId = $this->seedLocationDevice((int) $other->organization_id, $other->id, 'Fremdgerät');

        $this->actingAs($admin)
            ->delete(route('admin.sessions.devices.destroy', ['deviceSqid' => Sqid::encode(LocationDeviceToken::class, $deviceId)]))
            ->assertNotFound();

        $this->assertNull(DB::table('location_device_tokens')->where('id', $deviceId)->value('revoked_at'));
    }

    // ===== Phase 3: Terminals =====

    public function test_index_lists_terminal_and_admin_can_deactivate(): void {
        $admin = User::factory()->admin()->create();
        [$terminal] = AttendanceTerminal::issue((int) $admin->organization_id, 'Terminal Halle');

        $this->actingAs($admin)->get(route('admin.sessions.index'))->assertOk()->assertSee('Terminal Halle');

        $this->actingAs($admin)
            ->from(route('admin.sessions.index'))
            ->delete(route('admin.sessions.terminals.deactivate', ['terminalSqid' => Sqid::encode(AttendanceTerminal::class, $terminal->id)]))
            ->assertRedirect(route('admin.sessions.index'));

        $this->assertFalse((bool) $terminal->fresh()->active);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'event' => 'terminal.deactivated']);
    }

    public function test_admin_cannot_deactivate_terminal_of_other_organization(): void {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        [$terminal] = AttendanceTerminal::issue((int) $other->organization_id, 'Fremdterminal');

        $this->actingAs($admin)
            ->delete(route('admin.sessions.terminals.deactivate', ['terminalSqid' => Sqid::encode(AttendanceTerminal::class, $terminal->id)]))
            ->assertNotFound();

        $this->assertTrue((bool) $terminal->fresh()->active);
    }

    // ===== Phase 3: Remote-Support (read-only) =====

    public function test_index_lists_remote_support_history_readonly(): void {
        $admin = User::factory()->admin()->create();
        RemotePendingSession::create([
            'organization_id' => $admin->organization_id,
            'provider' => 'teamviewer',
            'remote_id' => '111222',
            'alias' => 'Kundenrechner-Nord',
            'session_id' => 'sess-1',
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.sessions.index'))
            ->assertOk()
            ->assertSee(__('sessions.section.remote_support'))
            ->assertSee('Kundenrechner-Nord');
    }

    private function seedLocationDevice(int $organizationId, int $userId, string $label): int {
        return (int) DB::table('location_device_tokens')->insertGetId([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'label' => $label,
            'token_hash' => hash('sha256', $label . $userId),
            'last_used_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedSession(int $userId): string {
        $id = Str::random(40);
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '203.0.113.5',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/125.0 Safari/537.36',
            'payload' => base64_encode('demo'),
            'last_activity' => CarbonImmutable::now()->getTimestamp(),
        ]);

        return $id;
    }
}
