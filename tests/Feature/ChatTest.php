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
        $this->actingAs($user)->postJson(route('chat.messages.store', $channel), ['body' => 'Antwort', 'parent_id' => $msg->sqid])->assertCreated();
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

    public function test_quote_reply_and_forward(): void {
        $user = $this->member();
        $src = Channel::create(['organization_id' => $user->organization_id, 'name' => 'Quelle', 'slug' => 'q', 'type' => 'channel', 'visibility' => 'public', 'created_by' => $user->id]);
        $src->members()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);
        $dst = Channel::create(['organization_id' => $user->organization_id, 'name' => 'Ziel', 'slug' => 'z', 'type' => 'channel', 'visibility' => 'public', 'created_by' => $user->id]);
        $dst->members()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

        $orig = $src->messages()->create(['user_id' => $user->id, 'body' => 'Original', 'type' => 'text']);

        // Zitat-Antwort: quoted_id wird gesetzt (nur gleicher Kanal).
        $this->actingAs($user)->postJson(route('chat.messages.store', $src), ['body' => 'Antwort', 'quoted_id' => $orig->sqid])
            ->assertCreated();
        $this->assertDatabaseHas('chat_messages', ['channel_id' => $src->id, 'body' => 'Antwort', 'quoted_id' => $orig->id]);

        // Weiterleiten in den Zielkanal: Kopie mit Herkunfts-Markierung.
        $this->actingAs($user)->postJson(route('chat.messages.forward', $orig), ['channel_id' => $dst->sqid])
            ->assertCreated();
        $this->assertDatabaseHas('chat_messages', ['channel_id' => $dst->id, 'body' => 'Original', 'forwarded_from_user_id' => $user->id]);
    }

    public function test_star_and_remind(): void {
        $user = $this->member();
        $channel = Channel::create(['organization_id' => $user->organization_id, 'name' => 'C', 'slug' => 'c', 'type' => 'channel', 'visibility' => 'public', 'created_by' => $user->id]);
        $channel->members()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);
        $msg = $channel->messages()->create(['user_id' => $user->id, 'body' => 'Merken', 'type' => 'text']);

        // Sternen (toggle an)
        $this->actingAs($user)->postJson(route('chat.messages.star', $msg))->assertOk();
        $this->assertDatabaseHas('chat_message_stars', ['message_id' => $msg->id, 'user_id' => $user->id]);
        // Sternen (toggle aus)
        $this->actingAs($user)->postJson(route('chat.messages.star', $msg))->assertOk();
        $this->assertDatabaseMissing('chat_message_stars', ['message_id' => $msg->id, 'user_id' => $user->id]);

        // Erinnerung in der Zukunft
        $this->actingAs($user)->postJson(route('chat.messages.remind', $msg), ['remind_at' => now()->addDay()->format('Y-m-d H:i:s')])
            ->assertCreated();
        $this->assertDatabaseHas('chat_reminders', ['message_id' => $msg->id, 'user_id' => $user->id, 'sent_at' => null]);
    }

    public function test_scheduled_message_is_queued_then_sent_by_command(): void {
        $user = $this->member();
        $channel = Channel::create(['organization_id' => $user->organization_id, 'name' => 'P', 'slug' => 'p', 'type' => 'channel', 'visibility' => 'public', 'created_by' => $user->id]);
        $channel->members()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

        // Planen → landet in der Warteschlange, NICHT in den Nachrichten.
        $this->actingAs($user)->postJson(route('chat.messages.store', $channel), [
            'body' => 'Später', 'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ])->assertStatus(202)->assertJson(['scheduled' => true]);
        $this->assertDatabaseHas('chat_scheduled_messages', ['channel_id' => $channel->id, 'body' => 'Später']);
        $this->assertDatabaseMissing('chat_messages', ['channel_id' => $channel->id, 'body' => 'Später']);

        // Fällig machen + Command laufen lassen → echte Nachricht entsteht.
        \App\Models\Chat\ScheduledMessage::query()->update(['scheduled_at' => now()->subMinute()]);
        $this->artisan('chat:send-scheduled')->assertSuccessful();
        $this->assertDatabaseHas('chat_messages', ['channel_id' => $channel->id, 'body' => 'Später']);
        $this->assertDatabaseMissing('chat_scheduled_messages', ['channel_id' => $channel->id, 'body' => 'Später']);
    }

    public function test_non_member_cannot_download_private_channel_attachment(): void {
        $owner = $this->member();
        $channel = Channel::create(['organization_id' => $owner->organization_id, 'name' => 'Geheim', 'slug' => 'gh', 'type' => 'group', 'visibility' => 'private', 'created_by' => $owner->id]);
        $channel->members()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);
        $msg = $channel->messages()->create(['user_id' => $owner->id, 'body' => 'x', 'type' => 'text']);
        $att = $msg->attachments()->create([
            'organization_id' => $owner->organization_id, 'user_id' => $owner->id,
            'disk' => 'local', 'path' => 'attachments/chat/missing.txt',
            'original_name' => 'x.txt', 'mime' => 'text/plain', 'size' => 1,
        ]);
        $url = \App\Http\Controllers\AttachmentController::downloadUrl($att);

        // Nicht-Mitglied derselben Organisation: trotz gültiger Signatur verboten.
        $intruder = User::factory()->user()->create(['organization_id' => $owner->organization_id]);
        $this->actingAs($intruder)->get($url)->assertForbidden();

        // Mitglied (Owner): Gate erlaubt → Datei fehlt → 404 (kein 403) belegt die Freigabe.
        $this->actingAs($owner)->get($url)->assertNotFound();
    }

    public function test_admin_cannot_access_private_channel_they_are_not_member_of(): void {
        $owner = $this->member();
        $admin = User::factory()->admin()->create(['organization_id' => $owner->organization_id]);

        // Privater Kanal, Admin ist KEIN Mitglied.
        $channel = Channel::create(['organization_id' => $owner->organization_id, 'name' => 'Vertraulich', 'slug' => 'vertraulich', 'type' => 'channel', 'visibility' => 'private', 'created_by' => $owner->id]);
        $channel->members()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);

        $msg = $channel->messages()->create(['user_id' => $owner->id, 'body' => 'geheim', 'type' => 'text']);

        // Admin darf private Inhalte weder lesen noch löschen (kein Admin-Bypass).
        $this->actingAs($admin)->getJson(route('chat.messages.index', $channel))->assertForbidden();
        $this->actingAs($admin)->deleteJson(route('chat.messages.destroy', $msg))->assertForbidden();
        $this->assertDatabaseHas('chat_messages', ['id' => $msg->id]);
    }
}
