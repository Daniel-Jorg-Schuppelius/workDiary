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
                'required' => true,
            ])
            ->assertRedirect();

        $item = $protocol->items()->firstOrFail();

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->put(route('protocols.items.fill', $item), [
                'result' => 'ok',
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
}
