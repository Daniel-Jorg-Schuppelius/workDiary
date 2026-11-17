<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KanbanTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Project;

use App\Enums\Diary\Status;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Services\UI\DateRangeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KanbanTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        // Kanban liest den Range jetzt aus dem globalen Context.
        app(DateRangeContext::class)->set(DateRangeContext::PRESET_THIS_YEAR);
    }

    public function test_card_exposes_authorized_lifecycle_actions_for_dragdrop(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create([
            'organization_id' => $user->organization_id,
            'status' => Status::Planned,
            'is_archived' => false,
        ]);

        $response = $this->actingAs($user)->get(route('kanban.index'));

        $response->assertOk()
            ->assertSee('data-kanban-card', false)
            ->assertSee(route('diary.lifecycle', [$entry, 'action' => 'accept']), false)
            ->assertSee(route('diary.lifecycle', [$entry, 'action' => 'cancel']), false);
    }

    public function test_foreign_card_exposes_no_lifecycle_actions_without_permission(): void {
        $owner = User::factory()->user()->create();
        $viewer = User::factory()->user()->create(['organization_id' => $owner->organization_id]);
        $entry = DiaryEntry::factory()->for($owner)->create([
            'organization_id' => $owner->organization_id,
            'status' => Status::Planned,
            'is_archived' => false,
        ]);

        $response = $this->actingAs($viewer)->get(route('kanban.index', ['scope' => 'team']));

        $response->assertOk()
            ->assertSee('data-kanban-card', false)
            ->assertDontSee(route('diary.lifecycle', [$entry, 'action' => 'accept']), false);
    }

    /**
     * Prüfpflicht (Feature 198): die Karte nennt sie, damit kanban.js beim Zug
     * nach „Erledigt“ und in die Prüfspalten auf den Auftrag verweist; die
     * Aktion „Abschließen“ wird ihr gar nicht erst angeboten.
     */
    public function test_review_card_carries_its_review_requirement_for_the_move_hint(): void {
        $user = User::factory()->user()->create();
        $type = \App\Models\Classification\EntryType::factory()->create(['organization_id' => $user->organization_id, 'requires_review' => true]);
        $review = DiaryEntry::factory()->for($user)->create([
            'organization_id' => $user->organization_id,
            'entry_type_id' => $type->id,
            'status' => Status::InProgress,
            'is_archived' => false,
        ]);
        $plain = DiaryEntry::factory()->for($user)->create([
            'organization_id' => $user->organization_id,
            'status' => Status::InProgress,
            'is_archived' => false,
        ]);

        $html = (string) $this->actingAs($user)->get(route('kanban.index'))
            ->assertOk()
            ->assertSee('data-status="' . Status::InReview->value . '"', false)
            ->assertDontSee(route('diary.lifecycle', [$review, 'action' => 'complete']), false)
            ->assertSee(route('diary.lifecycle', [$plain, 'action' => 'complete']), false)
            ->getContent();

        $this->assertMatchesRegularExpression('/data-id="' . $review->id . '"\s+data-status="1"\s+data-requires-review="1"/', $html);
        $this->assertMatchesRegularExpression('/data-id="' . $plain->id . '"\s+data-status="1"\s+data-requires-review="0"/', $html);
    }

    public function test_kanban_limits_entries_to_200_for_large_ranges(): void {
        $user = User::factory()->user()->create();
        DiaryEntry::factory()->count(250)->for($user)->create(['is_archived' => false]);

        $this->actingAs($user)
            ->get(route('kanban.index'))
            ->assertOk()
            ->assertViewHas('isLimited', true)
            ->assertViewHas('byStatus', function ($byStatus) {
                return $byStatus->flatten(1)->count() === 200;
            });
    }
}
