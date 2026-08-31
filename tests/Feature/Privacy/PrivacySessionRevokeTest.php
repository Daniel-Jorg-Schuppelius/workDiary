<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacySessionRevokeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Models\User;
use App\Services\Security\SessionManagementService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrivacySessionRevokeTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    public function test_revoke_session_requires_permission(): void {
        $user = User::factory()->user()->create();
        $sessionId = $this->seedSession($user->id);

        $this->actingAs($user)
            ->from(route('admin.privacy.index'))
            ->delete(route('admin.privacy.sessions.destroy', ['id' => $sessionId]))
            ->assertForbidden();
    }

    public function test_admin_can_revoke_session_of_org_member_and_audit_is_written(): void {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->user()->create(['organization_id' => $admin->organization_id]);
        $sessionId = $this->seedSession($member->id);

        // Adressiert wird über das HMAC-Handle, nicht über die Session-ID
        // (Sicherheitsscan 2026-08-23, S-54).
        $this->actingAs($admin)
            ->from(route('admin.privacy.index'))
            ->delete(route('admin.privacy.sessions.destroy', ['id' => SessionManagementService::handleFor($sessionId)]))
            ->assertRedirect(route('admin.privacy.index'));

        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'session.revoked',
        ]);
    }

    /**
     * Seit S-54 wird das Handle **innerhalb der eigenen Organisation**
     * aufgelöst. Eine fremde Sitzung ist damit nicht mehr adressierbar, und
     * die Antwort unterscheidet nicht mehr zwischen „gibt es nicht" und „gibt
     * es, gehört aber jemand anderem" — das ist Absicht: die frühere
     * 404-Antwort war ein Existenz-Orakel über Mandantengrenzen hinweg.
     * Geprüft wird deshalb die Wirkung, nicht der Statuscode.
     */
    public function test_admin_cannot_revoke_session_of_other_organization(): void {
        $admin = User::factory()->admin()->create();
        $otherUser = User::factory()->user()->create(); // andere Org via Factory-Default
        $sessionId = $this->seedSession($otherUser->id);

        $this->actingAs($admin)
            ->from(route('admin.privacy.index'))
            ->delete(route('admin.privacy.sessions.destroy', ['id' => SessionManagementService::handleFor($sessionId)]))
            ->assertSessionHasErrors('session');

        // Session bleibt erhalten.
        $this->assertDatabaseHas('sessions', ['id' => $sessionId]);
    }

    private function seedSession(int $userId): string {
        $id = Str::random(40);
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('demo'),
            'last_activity' => CarbonImmutable::now()->getTimestamp(),
        ]);

        return $id;
    }
}
