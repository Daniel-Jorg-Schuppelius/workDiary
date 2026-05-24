<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebPushTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{Comment, DiaryEntry, EmergencyAssignment, PushSubscription, User};
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class WebPushTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        Config::set('webpush.public_key', 'BO-uHxBTpw50e_ZPfZDwBYFhNhEP38RiyWF7ppyCrrSoC6sYSO8ILUZ6_MX1c-iSgOJrTbcuhvMoA_fFnsZSnx0');
        Config::set('webpush.private_key', 'GCpBG_ivebc2Sm61xQtqkwBOWhzQrvJmBnaSoXE7PMs');
    }

    public function test_vapid_endpoint_returns_public_key(): void {
        $user = User::factory()->user()->create();
        $this->actingAs($user)->getJson(route('push.vapid'))
            ->assertOk()
            ->assertJsonStructure(['publicKey']);
    }

    public function test_subscribe_persists_subscription(): void {
        $user = User::factory()->user()->create();
        $this->actingAs($user)->postJson(route('push.subscribe'), [
            'endpoint' => 'https://push.example.com/abc',
            'keys' => ['p256dh' => 'pkey', 'auth' => 'akey'],
            'contentEncoding' => 'aesgcm',
        ])->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.com/abc',
        ]);
    }

    public function test_unsubscribe_removes_only_own_subscription(): void {
        $user = User::factory()->user()->create();
        $other = User::factory()->user()->create();
        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.com/x',
            'p256dh' => 'p',
            'auth' => 'a',
        ]);
        PushSubscription::create([
            'user_id' => $other->id,
            'endpoint' => 'https://push.example.com/y',
            'p256dh' => 'p',
            'auth' => 'a',
        ]);

        $this->actingAs($user)->deleteJson(route('push.unsubscribe'), [
            'endpoint' => 'https://push.example.com/x',
        ])->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://push.example.com/x']);
        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://push.example.com/y']);
    }

    public function test_new_comment_triggers_push_to_entry_owner(): void {
        $owner = User::factory()->user()->create();
        $commenter = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($owner)->create();

        $mock = Mockery::mock(WebPushService::class);
        $mock->shouldReceive('sendToUser')
            ->once()
            ->withArgs(fn($u, $payload) => $u->id === $owner->id && isset($payload['title']));
        $this->app->instance(WebPushService::class, $mock);

        $this->actingAs($commenter);
        Comment::factory()->for($commenter)->create(['commentable_type' => DiaryEntry::class, 'commentable_id' => $entry->id, 'body' => 'Hi']);
    }

    public function test_own_comment_does_not_push(): void {
        $owner = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($owner)->create();

        $mock = Mockery::mock(WebPushService::class);
        $mock->shouldNotReceive('sendToUser');
        $this->app->instance(WebPushService::class, $mock);

        $this->actingAs($owner);
        Comment::factory()->for($owner)->create(['commentable_type' => DiaryEntry::class, 'commentable_id' => $entry->id, 'body' => 'self']);
    }

    public function test_emergency_assignment_pushes_to_assignee(): void {
        $user = User::factory()->user()->create();

        $mock = Mockery::mock(WebPushService::class);
        $mock->shouldReceive('sendToUser')
            ->once()
            ->withArgs(fn($u, $payload) => $u->id === $user->id);
        $this->app->instance(WebPushService::class, $mock);

        EmergencyAssignment::factory()->for($user)->create();
    }

    public function test_problem_diary_entry_pushes_to_admins(): void {
        // Push geht an Admins derselben Organisation. Spatie-Rollen werden
        // pro team_id (= organization_id) ausgewertet, daher müssen Admin
        // und Autor zwingend in derselben Org liegen.
        $author = User::factory()->user()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $author->organization_id]);

        $mock = Mockery::mock(WebPushService::class);
        $mock->shouldReceive('sendToUser')
            ->atLeast()->once()
            ->withArgs(fn($u, $payload) => $u->id === $admin->id);
        $this->app->instance(WebPushService::class, $mock);

        // Spatie-Team-Kontext explizit auf die Author-Org setzen, damit
        // User::role(...) im PushNotifier den Admin findet.
        app(\Spatie\Permission\PermissionRegistrar::class)
            ->setPermissionsTeamId((int) $author->organization_id);

        $this->actingAs($author);
        DiaryEntry::factory()->for($author)->create(['status' => 3, 'content' => 'Notlage']);
    }
}
