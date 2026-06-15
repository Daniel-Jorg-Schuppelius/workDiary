<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Protocol;

use App\Enums\Asset\AssetOwnership;
use App\Enums\Protocol\{ProtocolItemPhotoPhase, ProtocolItemType, ProtocolStatus, ProtocolType};
use App\Models\{Asset, DiaryEntry, Protocol, ProtocolItemPhoto, User};
use App\Services\Protocol\ProtocolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProtocolControllerTest extends TestCase {
    use RefreshDatabase;

    public function test_user_can_store_protocol_against_asset(): void {
        $user = User::factory()->user()->create();
        $asset = Asset::factory()->create([
            'organization_id' => $user->organization_id,
            'customer_id' => null,
            'owned_by' => AssetOwnership::Organization->value,
        ]);

        $this->actingAs($user)
            ->from('/')
            ->post(route('protocols.store'), [
                'subject_kind' => 'asset',
                'subject_id' => $asset->id,
                'type' => ProtocolType::Service->value,
                'title' => 'Wartungsprotokoll Asset',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('protocols', [
            'subject_type' => Asset::class,
            'subject_id' => $asset->id,
            'type' => ProtocolType::Service->value,
            'status' => ProtocolStatus::Draft->value,
            'title' => 'Wartungsprotokoll Asset',
            'created_by_user_id' => $user->id,
        ]);
    }

    public function test_store_protocol_persists_existing_and_new_tags(): void {
        $user = User::factory()->user()->create();
        $asset = Asset::factory()->create([
            'organization_id' => $user->organization_id,
            'customer_id' => null,
            'owned_by' => AssetOwnership::Organization->value,
        ]);
        $existing = \App\Models\Tag::create([
            'name' => 'Wartung',
            'organization_id' => $user->organization_id,
        ]);

        $this->actingAs($user)
            ->from('/')
            ->post(route('protocols.store'), [
                'subject_kind' => 'asset',
                'subject_id' => $asset->id,
                'type' => ProtocolType::Service->value,
                'title' => 'Protokoll mit Tags',
                'tag_ids' => [$existing->sqid],
                'new_tags' => 'Inspektion, Sicherheit',
            ])
            ->assertRedirect();

        $protocol = Protocol::query()->latest('id')->firstOrFail();
        $names = $protocol->tags()->pluck('name')->sort()->values()->all();

        $this->assertSame(['Inspektion', 'Sicherheit', 'Wartung'], $names);
    }

    public function test_update_protocol_syncs_tags(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();
        $protocol = Protocol::factory()->create([
            'organization_id' => $user->organization_id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'created_by_user_id' => $user->id,
            'status' => ProtocolStatus::Draft->value,
        ]);
        $protocol->tags()->attach(\App\Models\Tag::create([
            'name' => 'Alt',
            'organization_id' => $user->organization_id,
        ]));

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->put(route('protocols.update', $protocol), [
                'title' => 'Aktualisiert',
                'new_tags' => 'Neu',
            ])
            ->assertRedirect();

        $names = $protocol->tags()->pluck('name')->all();
        $this->assertSame(['Neu'], $names);
    }

    public function test_user_can_store_protocol_against_diary_entry(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->post(route('protocols.store'), [
                'subject_kind' => 'diary',
                'subject_id' => $entry->id,
                'type' => ProtocolType::Service->value,
                'title' => 'Servicebesuch',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('protocols', [
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'type' => ProtocolType::Service->value,
            'status' => ProtocolStatus::Draft->value,
            'title' => 'Servicebesuch',
            'created_by_user_id' => $user->id,
        ]);
    }

    public function test_guest_cannot_store(): void {
        $entry = DiaryEntry::factory()->for(User::factory()->user())->create();

        $this->post(route('protocols.store'), [
            'subject_kind' => 'diary',
            'subject_id' => $entry->id,
            'type' => ProtocolType::Service->value,
            'title' => 'X',
        ])->assertRedirect(route('login'));
    }

    public function test_request_review_transition_via_http(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();
        $protocol = Protocol::factory()
            ->for($entry, 'subject')
            ->state([
                'created_by_user_id' => $user->id,
                'organization_id' => $user->organization_id,
            ])
            ->create();

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->post(route('protocols.transition', ['protocol' => $protocol, 'action' => 'requestReview']))
            ->assertRedirect();

        $this->assertSame(ProtocolStatus::InReview, $protocol->refresh()->status);
    }

    public function test_update_on_signed_protocol_returns_error(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();
        $protocol = Protocol::factory()
            ->for($entry, 'subject')
            ->state([
                'created_by_user_id' => $user->id,
                'organization_id' => $user->organization_id,
            ])
            ->signed()
            ->create();

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->put(route('protocols.update', $protocol), ['title' => 'Neuer Titel'])
            ->assertForbidden();
    }

    public function test_supersede_requires_reason(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();
        $protocol = Protocol::factory()
            ->for($entry, 'subject')
            ->state([
                'created_by_user_id' => $user->id,
                'organization_id' => $user->organization_id,
            ])
            ->signed()
            ->create();
        // Permission fuer supersede
        $user->givePermissionTo('protocol.supersede');

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->post(route('protocols.transition', ['protocol' => $protocol, 'action' => 'supersede']), [
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');
    }

    public function test_add_item_and_fill_via_http(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();
        $protocol = Protocol::factory()
            ->for($entry, 'subject')
            ->state([
                'created_by_user_id' => $user->id,
                'organization_id' => $user->organization_id,
            ])
            ->create();

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->post(route('protocols.items.store', $protocol), [
                'label' => 'Sichtprüfung',
                'item_type' => ProtocolItemType::Boolean->value,
                'required' => true,
            ])
            ->assertRedirect();

        $item = $protocol->items()->firstOrFail();

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->put(route('protocols.items.fill', $item), [
                'value_json' => ['value' => true],
                'note' => 'i. O.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('protocol_items', [
            'id' => $item->id,
            'result' => 'ok',
            'note' => 'i. O.',
            'measured_by_user_id' => $user->id,
        ]);
    }

    public function test_user_can_upload_and_delete_photo(): void {
        Storage::fake('local');
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();
        $protocols = app(ProtocolService::class);
        $protocol = $protocols->create($entry, $user, [
            'type' => ProtocolType::Service->value,
            'title' => 'Fotoaufnahme',
        ]);
        $item = $protocols->addItem($protocol, $user, [
            'label' => 'Vorher/Nachher',
            'item_type' => ProtocolItemType::Photo->value,
        ]);

        $file = UploadedFile::fake()->image('vorher.jpg', 800, 600);

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->post(route('protocols.items.photos.store', $item), [
                'photo' => $file,
                'phase' => ProtocolItemPhotoPhase::Before->value,
                'caption' => 'Ausgangszustand',
            ])
            ->assertRedirect();

        $photo = ProtocolItemPhoto::query()
            ->where('protocol_item_id', $item->id)
            ->firstOrFail();
        $this->assertSame('Ausgangszustand', $photo->caption);

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->delete(route('protocols.items.photos.destroy', $photo))
            ->assertRedirect();

        $this->assertNull(ProtocolItemPhoto::query()->find($photo->id));
    }

    public function test_photo_upload_rejects_non_image(): void {
        Storage::fake('local');
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();
        $protocols = app(ProtocolService::class);
        $protocol = $protocols->create($entry, $user, [
            'type' => ProtocolType::Service->value,
            'title' => 'Bild-Validierung',
        ]);
        $item = $protocols->addItem($protocol, $user, [
            'label' => 'Foto',
            'item_type' => ProtocolItemType::Photo->value,
        ]);

        $bad = UploadedFile::fake()->create('plain.txt', 10, 'text/plain');

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->post(route('protocols.items.photos.store', $item), [
                'photo' => $bad,
                'phase' => ProtocolItemPhotoPhase::Detail->value,
            ])
            ->assertSessionHasErrors('photo');
    }
}
