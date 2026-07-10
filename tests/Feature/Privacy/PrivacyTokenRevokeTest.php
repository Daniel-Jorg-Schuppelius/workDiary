<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyTokenRevokeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrivacyTokenRevokeTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    public function test_revoke_token_requires_permission(): void {
        $user = User::factory()->user()->create();
        $tokenId = $this->seedToken($user->id);

        $this->actingAs($user)
            ->from(route('admin.privacy.index'))
            ->delete(route('admin.privacy.tokens.destroy', ['id' => $tokenId]))
            ->assertForbidden();
    }

    public function test_admin_can_revoke_token_of_org_member_and_audit_is_written(): void {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->user()->create(['organization_id' => $admin->organization_id]);
        $tokenId = $this->seedToken($member->id, 'CI Deploy Token');

        $this->actingAs($admin)
            ->from(route('admin.privacy.index'))
            ->delete(route('admin.privacy.tokens.destroy', ['id' => $tokenId]))
            ->assertRedirect(route('admin.privacy.index'));

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'token.revoked',
        ]);
    }

    public function test_admin_cannot_revoke_token_of_other_organization(): void {
        $admin = User::factory()->admin()->create();
        $foreign = User::factory()->user()->create();
        $tokenId = $this->seedToken($foreign->id);

        $this->actingAs($admin)
            ->from(route('admin.privacy.index'))
            ->delete(route('admin.privacy.tokens.destroy', ['id' => $tokenId]))
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);
    }

    private function seedToken(int $userId, string $name = 'Demo Token'): int {
        return (int) DB::table('personal_access_tokens')->insertGetId([
            'tokenable_type' => User::class,
            'tokenable_id' => $userId,
            'name' => $name,
            'token' => Str::random(64),
            'abilities' => '["*"]',
            'created_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);
    }
}
