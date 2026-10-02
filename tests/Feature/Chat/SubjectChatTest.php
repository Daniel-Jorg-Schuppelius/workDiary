<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubjectChatTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Chat;

use App\Models\Chat\Channel;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Models\Project\Project;
use App\Services\Chat\SubjectChannelService;
use App\Services\Stammdaten\ProjectMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1061: ein Kanal je Projekt bzw. Auftrag, Eingeteilte als Mitglieder, Dateien am Träger. */
class SubjectChatTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['name' => 'Chat am Projekt']);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    public function test_project_chat_is_created_once_and_follows_the_assignment(): void {
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $alice = User::factory()->create(['organization_id' => $this->organization->id]);
        $project->members()->attach($alice->id);

        $this->post(route('chat.subject.open', ['project', $project->sqid]))->assertRedirect();
        $channel = Channel::query()->where('subject_type', $project->getMorphClass())->where('subject_id', $project->id)->firstOrFail();
        $this->assertSame('private', $channel->visibility);
        $this->assertEqualsCanonicalizing([$this->admin->id, $alice->id], $channel->members()->pluck('users.id')->all());

        $bob = User::factory()->create(['organization_id' => $this->organization->id]);
        $project->members()->attach($bob->id);
        $this->post(route('chat.subject.open', ['project', $project->sqid]))->assertRedirect(route('chat.show', $channel));

        $this->assertSame(1, Channel::query()->whereNotNull('subject_type')->count());
        $this->assertEqualsCanonicalizing([$this->admin->id, $alice->id, $bob->id], $channel->members()->pluck('users.id')->all());
    }

    public function test_order_chat_shows_its_files_to_members_only(): void {
        $worker = User::factory()->create(['organization_id' => $this->organization->id]);
        $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $worker->id]);

        $this->post(route('chat.subject.open', ['diary', $entry->sqid]))->assertRedirect();
        $channel = Channel::query()->whereNotNull('subject_type')->firstOrFail();
        $this->assertTrue($channel->members()->whereKey($worker->id)->exists());
        $message = $channel->messages()->create(['user_id' => $worker->id, 'body' => 'Foto Zählerschrank', 'type' => 'text']);
        $message->attachments()->create([
            'organization_id' => $this->organization->id, 'user_id' => $worker->id,
            'disk' => 'local', 'path' => 'attachments/chat/zaehler.jpg',
            'original_name' => 'zaehler.jpg', 'mime' => 'image/jpeg', 'size' => 1,
        ]);

        $this->get(route('diary.show', $entry))->assertOk()->assertSee('zaehler.jpg')->assertSee(__('chat.subject.open'));

        $outsider = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($outsider)->get(route('diary.show', $entry))->assertOk()->assertDontSee('zaehler.jpg');
    }

    public function test_foreign_or_invisible_subjects_are_refused(): void {
        $foreign = Organization::factory()->create();
        $foreignProject = Project::factory()->create(['organization_id' => $foreign->id]);
        $this->post(route('chat.subject.open', ['project', $foreignProject->sqid]))->assertNotFound();

        // Ohne Rolle sieht man nur eigene Aufträge — und kommt damit auch nicht in deren Chat.
        $stranger = User::factory()->create(['organization_id' => $this->organization->id]);
        $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->admin->id]);
        $this->actingAs($stranger)->post(route('chat.subject.open', ['diary', $entry->sqid]))->assertForbidden();

        $this->assertSame(0, Channel::query()->count());
    }

    public function test_project_merge_keeps_one_channel_per_project(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $source = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);
        $target = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);
        $service = app(SubjectChannelService::class);
        $sourceChannel = $service->open($source, $this->admin);
        $targetChannel = $service->open($target, $this->admin);

        app(ProjectMergeService::class)->merge($source, $target);

        $this->assertSame($targetChannel->id, $service->channelFor($target)?->id);
        $this->assertNull($sourceChannel->fresh()->subject_type);
        $this->assertNotNull($sourceChannel->fresh());
    }
}
