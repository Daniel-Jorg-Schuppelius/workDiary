<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TagsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use App\Models\Tag;
use App\Models\User;
use App\Services\UI\DateRangeContext;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagsTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        // Tagebuch-Listing nutzt den globalen Range; auf das ganze Jahr
        // stellen, damit die Factory-Eintr\u00e4ge (\u00b11 Monat) sichtbar sind.
        app(DateRangeContext::class)->set(DateRangeContext::PRESET_THIS_YEAR);
    }

    public function test_tag_can_be_attached_to_diary_shift_assignment(): void {
        $user = User::factory()->user()->create();
        $tag = Tag::create(['name' => 'Wartung', 'organization_id' => $user->organization_id]);

        $entry = DiaryEntry::factory()->for($user)->create();
        $shift = OnCallShift::factory()->for($user)->create();
        $assignment = EmergencyAssignment::factory()->for($user)->create();

        $entry->tags()->attach($tag);
        $shift->tags()->attach($tag);
        $assignment->tags()->attach($tag);

        $this->assertSame(1, $tag->diaryEntries()->count());
        $this->assertSame(1, $tag->shifts()->count());
        $this->assertSame(1, $tag->assignments()->count());
    }

    public function test_find_or_create_by_name_is_case_insensitive(): void {
        $a = Tag::findOrCreateByName('Backup');
        $b = Tag::findOrCreateByName('backup');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Tag::count());
    }

    public function test_unique_slug_avoids_collisions(): void {
        Tag::create(['name' => 'Alpha', 'slug' => 'alpha']);
        $slug = Tag::uniqueSlug('Alpha');

        $this->assertSame('alpha-2', $slug);
    }

    public function test_diary_store_persists_existing_and_new_tags(): void {
        $user = User::factory()->user()->create();
        $existing = Tag::create(['name' => 'Telefon', 'organization_id' => $user->organization_id]);

        $this->actingAs($user)
            ->post(route('diary.store'), [
                'content' => 'Mit Tags gespeichert',
                'status' => 2,
                'tag_ids' => [$existing->id],
                'new_tags' => 'Eskalation, Server',
            ])
            ->assertRedirect();

        $entry = DiaryEntry::latest('id')->first();
        $names = $entry->tags()->pluck('name')->sort()->values()->all();

        $this->assertSame(['Eskalation', 'Server', 'Telefon'], $names);
        $this->assertSame(3, Tag::count());
    }

    public function test_diary_index_filters_by_tag(): void {
        $user = User::factory()->user()->create();
        $tag = Tag::create(['name' => 'Filterbar', 'organization_id' => $user->organization_id]);

        $matching = DiaryEntry::factory()->for($user)->create(['content' => 'Treffer Tag']);
        $other = DiaryEntry::factory()->for($user)->create(['content' => 'Ohne Tag']);
        $matching->tags()->attach($tag);

        $this->actingAs($user)
            ->get(route('diary.index', ['tag' => $tag->id]))
            ->assertOk()
            ->assertSee('Treffer Tag')
            ->assertDontSee('Ohne Tag');
    }

    public function test_admin_can_create_and_delete_tag_via_routes(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('tags.store'), ['name' => 'Notfall'])
            ->assertRedirect();

        $tag = Tag::firstWhere('name', 'Notfall');
        $this->assertNotNull($tag);

        $this->actingAs($admin)
            ->delete(route('tags.destroy', $tag))
            ->assertRedirect();

        $this->assertNull(Tag::find($tag->id));
    }

    public function test_non_admin_cannot_update_or_delete_tag(): void {
        $user = User::factory()->user()->create();
        $tag = Tag::create(['name' => 'Schutz', 'organization_id' => $user->organization_id]);

        $this->actingAs($user)
            ->put(route('tags.update', $tag), ['name' => 'Neu'])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('tags.destroy', $tag))
            ->assertForbidden();
    }

    public function test_tags_index_renders_for_authenticated_user(): void {
        $user = User::factory()->user()->create();
        Tag::create(['name' => 'Sichtbar', 'organization_id' => $user->organization_id]);

        $this->actingAs($user)
            ->get(route('tags.index'))
            ->assertOk()
            ->assertSee('Sichtbar');
    }
}
