<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChatTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\Chat\Channel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase {
    use RefreshDatabase;

    private function member(): User {
        return User::factory()->user()->create();
    }

    public function test_chat_index_requires_auth(): void {
        $this->get(route('chat.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_open_chat_and_create_channel(): void {
        $user = $this->member();

        $this->actingAs($user)->get(route('chat.index'))->assertOk()->assertSee('Kanäle');

        $this->actingAs($user)->post(route('chat.channels.store'), [
            'name' => 'Buchhaltung',
            'type' => 'channel',
            'visibility' => 'private',
        ])->assertRedirect();

        $channel = Channel::where('name', 'Buchhaltung')->firstOrFail();
        $this->assertTrue($channel->isOwner($user));
    }

    public function test_member_can_post_and_fetch_messages(): void {
        $user = $this->member();
        $channel = Channel::create(['organization_id' => $user->organization_id, 'name' => 'Allgemein', 'slug' => 'allg', 'type' => 'channel', 'visibility' => 'public', 'created_by' => $user->id]);
        $channel->members()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

        $this->actingAs($user)->postJson(route('chat.messages.store', $channel), ['body' => 'Hallo Team'])
            ->assertCreated()->assertJsonStructure(['id', 'html']);

        $this->actingAs($user)->getJson(route('chat.messages.index', $channel))
            ->assertOk()->assertJsonStructure(['messages', 'oldest_id', 'has_more']);

        $this->assertDatabaseHas('chat_messages', ['channel_id' => $channel->id, 'body' => 'Hallo Team']);
    }

    public function test_non_member_cannot_post(): void {
        $owner = $this->member();
        $outsider = User::factory()->user()->create(['organization_id' => $owner->organization_id]);
        $channel = Channel::create(['organization_id' => $owner->organization_id, 'name' => 'Privat', 'slug' => 'priv', 'type' => 'group', 'visibility' => 'private', 'created_by' => $owner->id]);
        $channel->members()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($outsider)->postJson(route('chat.messages.store', $channel), ['body' => 'x'])->assertForbidden();
    }

    public function test_reactions_pins_threads_and_polls(): void {
        $user = $this->member();
        $channel = Channel::create(['organization_id' => $user->organization_id, 'name' => 'C', 'slug' => 'c', 'type' => 'channel', 'visibility' => 'public', 'created_by' => $user->id]);
        $channel->members()->attach($user->id, ['role' => 'owner']);
        $msg = $channel->messages()->create(['user_id' => $user->id, 'body' => 'Post', 'type' => 'text']);

        // Reaktion (toggle on/off)
        $this->actingAs($user)->postJson(route('chat.messages.react', $msg), ['emoji' => '👍'])->assertOk();
        $this->assertDatabaseHas('chat_message_reactions', ['message_id' => $msg->id, 'emoji' => '👍']);
        $this->actingAs($user)->postJson(route('chat.messages.react', $msg), ['emoji' => '👍'])->assertOk();
        $this->assertDatabaseMissing('chat_message_reactions', ['message_id' => $msg->id, 'emoji' => '👍']);

        // Pin
        $this->actingAs($user)->postJson(route('chat.messages.pin', $msg))->assertOk();
        $this->assertNotNull($msg->fresh()->pinned_at);

        // Thread-Antwort
        $this->actingAs($user)->postJson(route('chat.messages.store', $channel), ['body' => 'Antwort', 'parent_id' => $msg->id])->assertCreated();
        $this->assertSame(1, $msg->replies()->count());

        // Umfrage + Abstimmung
        $this->actingAs($user)->postJson(route('chat.polls.store', $channel), [
            'question' => 'Pizza?', 'options' => ['Ja', 'Nein'],
        ])->assertCreated();
        $poll = \App\Models\Chat\Poll::firstOrFail();
        $option = $poll->options()->first();
        $this->actingAs($user)->postJson(route('chat.polls.vote', $poll), ['options' => [$option->id]])->assertOk();
        $this->assertDatabaseHas('chat_poll_votes', ['poll_option_id' => $option->id, 'user_id' => $user->id]);
    }

    public function test_tenant_isolation_blocks_cross_org_channel(): void {
        $a = $this->member();
        $b = $this->member(); // andere Organisation (Factory legt eigene an)
        $channel = Channel::create(['organization_id' => $a->organization_id, 'name' => 'A', 'slug' => 'a', 'type' => 'channel', 'visibility' => 'public', 'created_by' => $a->id]);
        $channel->members()->attach($a->id, ['role' => 'owner']);

        // b (fremde Org) darf den Kanal nicht sehen → 404 via OrganizationScope-Binding.
        $this->actingAs($b)->get(route('chat.show', $channel))->assertNotFound();
    }
}
