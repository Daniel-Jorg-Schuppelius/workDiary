<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolTagsAttachmentsOrderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Protocol;

use App\Enums\Protocol\{ProtocolEventType, ProtocolItemPhotoPhase, ProtocolItemType, ProtocolType};
use App\Enums\User\Permission;
use App\Models\Attachments\Attachment;
use App\Models\Classification\Tag;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Protocol\{Protocol, ProtocolItem, ProtocolItemPhoto};
use App\Services\Protocol\{ProtocolItemPhotoService, ProtocolService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * MVP-1573: Tagging, Anhänge am ganzen Protokoll, Foto-Geodaten über die
 * Organisationseinstellung und das Umsortieren der Punkte.
 */
class ProtocolTagsAttachmentsOrderTest extends TestCase {
    use RefreshDatabase;

    private User $admin;

    private DiaryEntry $entry;

    private Protocol $protocol;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->admin()->create();
        $this->entry = DiaryEntry::factory()->for($this->admin)->create(['organization_id' => $this->admin->organization_id]);
        $this->protocol = app(ProtocolService::class)->create($this->entry, $this->admin, ['title' => 'Wartung Lüftung', 'type' => ProtocolType::Service->value]);
    }

    public function test_tags_are_picked_in_the_form_and_shown_on_the_page(): void {
        $tag = Tag::factory()->create(['organization_id' => $this->admin->organization_id, 'name' => 'Dachgeschoss']);

        $this->actingAs($this->admin)->get(route('protocols.create', ['subject_kind' => 'diary', 'subject' => $this->entry->sqid]))
            ->assertOk()->assertSee('x-data="tagPicker"', false);
        $this->actingAs($this->admin)->get(route('protocols.edit', $this->protocol))
            ->assertOk()->assertSee('x-data="tagPicker"', false)->assertSee('Dachgeschoss');

        $this->actingAs($this->admin)->from(route('protocols.show', $this->protocol))
            ->put(route('protocols.update', $this->protocol), ['title' => 'Wartung Lüftung', 'tag_ids' => [$tag->sqid], 'new_tags' => 'Eilig'])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['Dachgeschoss', 'Eilig'], $this->protocol->tags()->pluck('name')->all());
        $this->actingAs($this->admin)->get(route('protocols.show', $this->protocol))->assertOk()->assertSee('Dachgeschoss')->assertSee('Eilig');

        // Der Picker schickt new_tags immer mit: ohne Auswahl sind die Tags entfernt.
        $this->actingAs($this->admin)->put(route('protocols.update', $this->protocol), ['title' => 'Wartung Lüftung', 'new_tags' => '']);
        $this->assertSame(0, $this->protocol->tags()->count());
    }

    public function test_attachments_are_uploaded_journaled_and_locked_once_immutable(): void {
        $upload = route('attachments.store', ['type' => 'protocol', 'id' => $this->protocol->sqid]);
        $this->actingAs($this->admin)->get(route('protocols.show', $this->protocol))->assertOk()->assertSee($upload, false);

        $this->actingAs($this->admin)->from(route('protocols.show', $this->protocol))
            ->post($upload, ['file' => UploadedFile::fake()->create('lageplan.pdf', 20, 'application/pdf')])
            ->assertSessionHasNoErrors();
        $attachment = $this->protocol->attachments()->firstOrFail();
        $this->assertSame('lageplan.pdf', $attachment->original_name);
        $this->assertSame(1, $this->protocol->journal()->where('event', ProtocolEventType::AttachmentAdded)->count());

        $this->actingAs($this->admin)->delete(route('attachments.destroy', $attachment))->assertSessionHasNoErrors();
        $this->assertSame(0, $this->protocol->attachments()->count());
        $this->assertSame(1, $this->protocol->journal()->where('event', ProtocolEventType::AttachmentRemoved)->count());

        $this->actingAs($this->admin)->post($upload, ['file' => UploadedFile::fake()->create('nachweis.pdf', 20, 'application/pdf')]);
        $kept = $this->protocol->attachments()->firstOrFail();
        app(ProtocolService::class)->sign($this->protocol, $this->admin);

        // Nachweis: weder Upload noch Löschen, auch nicht über den Admin-Durchgriff der Policy.
        $this->actingAs($this->admin)->post($upload, ['file' => UploadedFile::fake()->create('spaet.pdf', 20, 'application/pdf')])->assertForbidden();
        $this->actingAs($this->admin)->delete(route('attachments.destroy', $kept))->assertForbidden();
        $this->assertNotNull(Attachment::query()->find($kept->id));
        $this->actingAs($this->admin)->get(route('protocols.show', $this->protocol->fresh()))->assertOk()->assertDontSee($upload, false)->assertSee('nachweis.pdf');
    }

    public function test_photo_location_follows_the_organization_setting(): void {
        $item = app(ProtocolService::class)->addItem($this->protocol, $this->admin, ['label' => 'Zustand', 'item_type' => ProtocolItemType::Photo->value]);
        $photo = new ProtocolItemPhoto;

        foreach ([[null, false], [['protocol' => ['photo_geo' => '1']], true]] as [$settings, $expected]) {
            $this->admin->organization->forceFill(['settings' => $settings])->save();
            $this->mock(ProtocolItemPhotoService::class, function ($mock) use ($expected, $photo): void {
                $mock->shouldReceive('upload')->once()
                    ->with(Mockery::any(), Mockery::any(), ProtocolItemPhotoPhase::Before, Mockery::any(), Mockery::on(static fn (array $options): bool => $options['allow_geo'] === $expected))
                    ->andReturn($photo);
            });

            // Ein allow_geo aus dem Formular zählt nicht mehr: es entscheidet die Organisation.
            $this->actingAs($this->admin)->from(route('protocols.show', $this->protocol))
                ->post(route('protocols.items.photos.store', $item), ['photo' => UploadedFile::fake()->image('vorher.jpg'), 'phase' => 'before', 'allow_geo' => $expected ? '0' : '1'])
                ->assertSessionHasNoErrors();
        }
    }

    public function test_photo_location_is_shown_only_with_the_right(): void {
        $item = app(ProtocolService::class)->addItem($this->protocol, $this->admin, ['label' => 'Zustand', 'item_type' => ProtocolItemType::Photo->value]);
        $attachment = Attachment::factory()->create([
            'organization_id' => $this->admin->organization_id,
            'attachable_type' => $item->getMorphClass(),
            'attachable_id' => $item->id,
            'mime' => 'image/jpeg',
        ]);
        ProtocolItemPhoto::query()->create([
            'protocol_item_id' => $item->id, 'attachment_id' => $attachment->id, 'phase' => 'before', 'sort_order' => 1,
            'geo_lat' => '52.520008', 'geo_lng' => '13.404954', 'captured_by_user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->get(route('protocols.show', $this->protocol))->assertOk()->assertSee('52.520008, 13.404954');

        $creator = User::factory()->user()->create(['organization_id' => $this->admin->organization_id]);
        $this->assertFalse($creator->can(Permission::ProtocolItemPhotoViewGeo->value));
        $this->protocol->forceFill(['created_by_user_id' => $creator->id])->save();
        $this->actingAs($creator)->get(route('protocols.show', $this->protocol))->assertOk()->assertDontSee('52.520008');
    }

    public function test_items_are_reordered_in_the_draft_and_children_stay_with_their_group(): void {
        $service = app(ProtocolService::class);
        $first = $service->addItem($this->protocol, $this->admin, ['label' => 'Sichtprüfung', 'item_type' => 'boolean']);
        $group = $service->addItem($this->protocol, $this->admin, ['label' => 'Messungen', 'item_type' => 'group']);
        $childA = $service->addItem($this->protocol, $this->admin, ['label' => 'Druck', 'item_type' => 'number', 'parent_item_id' => $group->id]);
        $childB = $service->addItem($this->protocol, $this->admin, ['label' => 'Temperatur', 'item_type' => 'number', 'parent_item_id' => $group->id]);
        $last = $service->addItem($this->protocol, $this->admin, ['label' => 'Abschluss', 'item_type' => 'text']);

        // Unterpunkte stehen auf der Seite einmal, unter ihrer Gruppe.
        $page = $this->actingAs($this->admin)->get(route('protocols.show', $this->protocol))->assertOk()
            ->assertSee(route('protocols.items.order-form', $this->protocol), false)
            ->getContent();
        $this->assertSame(1, substr_count((string) $page, '>Druck</td>'));

        $this->actingAs($this->admin)->get(route('protocols.items.order-form', $this->protocol))
            ->assertOk()
            ->assertSee('data-protocol-order-list', false)
            ->assertSeeInOrder(['Sichtprüfung', 'Messungen', 'Druck', 'Temperatur', 'Abschluss']);

        // Abschluss nach vorn, Temperatur vor Druck; Druck wird aus der Gruppe gezogen und bleibt trotzdem bei ihr.
        $foreign = ProtocolItem::query()->create(['protocol_id' => Protocol::factory()->create(['organization_id' => $this->admin->organization_id])->id, 'sort_order' => 1, 'item_type' => 'text', 'label' => 'Fremd']);
        $this->actingAs($this->admin)->from(route('protocols.show', $this->protocol))
            ->put(route('protocols.items.order', $this->protocol), ['order_ids' => [$last->sqid, $childB->sqid, $first->sqid, $childA->sqid, $group->sqid, $foreign->sqid]])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['Abschluss', 'Sichtprüfung', 'Messungen', 'Temperatur', 'Druck'],
            $this->protocol->items()->reorder('sort_order')->pluck('label')->all(),
        );
        $this->assertSame(1, (int) $foreign->fresh()->sort_order);
        $event = $this->protocol->journal()->where('event', ProtocolEventType::ItemReordered)->firstOrFail();
        $this->assertSame([$last->id, $first->id, $group->id, $childB->id, $childA->id], $event->payload['order']);

        $service->requestReview($this->protocol, $this->admin);
        $creator = User::factory()->user()->create(['organization_id' => $this->admin->organization_id]);
        $this->protocol->forceFill(['created_by_user_id' => $creator->id])->save();
        $this->actingAs($creator)->put(route('protocols.items.order', $this->protocol), ['order_ids' => [$first->sqid]])->assertForbidden();
        $this->actingAs($this->admin)->from(route('protocols.show', $this->protocol))
            ->put(route('protocols.items.order', $this->protocol), ['order_ids' => [$first->sqid]])
            ->assertSessionHasErrors('status');
    }
}
