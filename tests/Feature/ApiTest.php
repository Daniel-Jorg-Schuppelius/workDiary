<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\User\UserRole;
use App\Models\{DiaryEntry, Tag, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    public function test_unauthenticated_returns_401(): void {
        $this->getJson('/api/diary')->assertStatus(401);
    }

    public function test_me_returns_user(): void {
        $user = User::factory()->user()->create();
        Sanctum::actingAs($user, ['*']);
        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->sqid)
            ->assertJsonPath('meta.roles.0', UserRole::User->value);
    }

    public function test_diary_index_paginates(): void {
        $user = User::factory()->user()->create();
        DiaryEntry::factory()->count(3)->for($user)->create();
        Sanctum::actingAs($user, ['*']);
        $this->getJson('/api/diary')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'content', 'status']], 'meta', 'links']);
    }

    public function test_diary_store_creates_entry(): void {
        $user = User::factory()->user()->create();
        Sanctum::actingAs($user, ['*']);
        $this->postJson('/api/diary', [
            'content' => 'API erstellt',
            'status' => 2,
        ])->assertCreated()
            ->assertJsonPath('data.content', 'API erstellt');

        $this->assertDatabaseHas('diary_entries', ['content' => 'API erstellt', 'user_id' => $user->id]);
    }

    public function test_diary_update_requires_owner(): void {
        $owner = User::factory()->user()->create();
        // $other muss zur selben Organisation gehören, sonst greift bereits der
        // OrganizationScope und liefert 404. Wir wollen hier explizit den
        // Policy-Owner-Check absichern (Org-Cross-Boundary deckt ApiTenantTest ab).
        $other = User::factory()->user()->create(['organization_id' => $owner->organization_id]);
        $entry = DiaryEntry::factory()->for($owner)->create();
        Sanctum::actingAs($other, ['*']);
        $this->putJson('/api/diary/' . $entry->getRouteKey(), [
            'content' => 'hack',
            'status' => 2,
        ])->assertForbidden();
    }

    public function test_diary_archive_and_restore(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create(['is_archived' => false]);
        Sanctum::actingAs($user, ['*']);
        $this->postJson('/api/diary/' . $entry->getRouteKey() . '/archive')->assertOk();
        $this->assertTrue($entry->fresh()->is_archived);
        $this->postJson('/api/diary/' . $entry->getRouteKey() . '/restore')->assertOk();
        $this->assertFalse($entry->fresh()->is_archived);
    }

    public function test_comment_store_via_api(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);
        $this->postJson('/api/diary/' . $entry->getRouteKey() . '/comments', ['body' => 'Hi'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Hi');
    }

    public function test_tags_crud(): void {
        $user = User::factory()->user()->create();
        // Admin muss zur selben Org wie der erstellende User gehören, sonst
        // greift der OrganizationScope (Tag in Org A, Admin in Org B → 404).
        $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);

        // Anlegen darf jeder
        Sanctum::actingAs($user, ['*']);
        $this->postJson('/api/tags', ['name' => 'X'])->assertCreated();

        // Update/Delete nur Admin
        $tag = Tag::where('name', 'X')->first();
        $this->putJson('/api/tags/' . $tag->getRouteKey(), ['name' => 'Y'])->assertForbidden();

        Sanctum::actingAs($admin, ['*']);
        $this->putJson('/api/tags/' . $tag->getRouteKey(), ['name' => 'Y'])->assertOk();
        $this->deleteJson('/api/tags/' . $tag->getRouteKey())->assertOk();
    }

    public function test_dashboard_endpoint(): void {
        $user = User::factory()->user()->create();
        Sanctum::actingAs($user, ['*']);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonStructure(['data']);
    }

    public function test_push_subscribe_via_api(): void {
        $user = User::factory()->user()->create();
        Sanctum::actingAs($user, ['*']);
        $this->postJson('/api/push/subscribe', [
            'endpoint' => 'https://push.example.com/api',
            'keys' => ['p256dh' => 'p', 'auth' => 'a'],
        ])->assertCreated();
        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://push.example.com/api']);
    }
}
