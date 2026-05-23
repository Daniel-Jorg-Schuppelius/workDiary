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

use App\Enums\Protocol\ProtocolStatus;
use App\Enums\Protocol\ProtocolType;
use App\Models\DiaryEntry;
use App\Models\Protocol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtocolControllerTest extends TestCase {
    use RefreshDatabase;

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
                'item_type' => \App\Enums\Protocol\ProtocolItemType::Boolean->value,
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
        \Illuminate\Support\Facades\Storage::fake('local');
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();
        $protocols = app(\App\Services\Protocol\ProtocolService::class);
        $protocol = $protocols->create($entry, $user, [
            'type' => ProtocolType::Service->value,
            'title' => 'Fotoaufnahme',
        ]);
        $item = $protocols->addItem($protocol, $user, [
            'label' => 'Vorher/Nachher',
            'item_type' => \App\Enums\Protocol\ProtocolItemType::Photo->value,
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->image('vorher.jpg', 800, 600);

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->post(route('protocols.items.photos.store', $item), [
                'photo' => $file,
                'phase' => \App\Enums\Protocol\ProtocolItemPhotoPhase::Before->value,
                'caption' => 'Ausgangszustand',
            ])
            ->assertRedirect();

        $photo = \App\Models\ProtocolItemPhoto::query()
            ->where('protocol_item_id', $item->id)
            ->firstOrFail();
        $this->assertSame('Ausgangszustand', $photo->caption);

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->delete(route('protocols.items.photos.destroy', $photo))
            ->assertRedirect();

        $this->assertNull(\App\Models\ProtocolItemPhoto::query()->find($photo->id));
    }

    public function test_photo_upload_rejects_non_image(): void {
        \Illuminate\Support\Facades\Storage::fake('local');
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();
        $protocols = app(\App\Services\Protocol\ProtocolService::class);
        $protocol = $protocols->create($entry, $user, [
            'type' => ProtocolType::Service->value,
            'title' => 'Bild-Validierung',
        ]);
        $item = $protocols->addItem($protocol, $user, [
            'label' => 'Foto',
            'item_type' => \App\Enums\Protocol\ProtocolItemType::Photo->value,
        ]);

        $bad = \Illuminate\Http\UploadedFile::fake()->create('plain.txt', 10, 'text/plain');

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->post(route('protocols.items.photos.store', $item), [
                'photo' => $bad,
                'phase' => \App\Enums\Protocol\ProtocolItemPhotoPhase::Detail->value,
            ])
            ->assertSessionHasErrors('photo');
    }
}
