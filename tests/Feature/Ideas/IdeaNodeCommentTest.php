<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaNodeCommentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ideas;

use App\Enums\Ideas\IdeaShareRole;
use App\Models\Platform\User;
use App\Services\Ideas\{IdeaMapService, IdeaNodeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1005: Kommentarfaden an Knoten der Ideenkarte. */
final class IdeaNodeCommentTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_viewers_comment_on_a_node_and_the_count_reaches_the_editor(): void {
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $maps = app(IdeaMapService::class);
        $owner = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $viewer = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Vera Leserin']);
        $outsider = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $map = $maps->create($this->organization, $owner, 'Strategie');
        $node = app(IdeaNodeService::class)->create($map, $map->rootNode()->firstOrFail(), 'Neue Märkte', $owner);
        $maps->shareWithUser($map, $viewer, IdeaShareRole::Viewer, $owner);

        $this->actingAs($outsider)->get(route('ideas.nodes.comments', [$map, $node]))->assertForbidden();
        $this->actingAs($viewer)->get(route('ideas.nodes.comments', [$map, $node]))->assertOk()->assertSee(__('ideas.comments.empty'));
        $this->actingAs($viewer)->post(route('ideas.nodes.comments.store', [$map, $node]), ['body' => 'Welche Region zuerst?'])
            ->assertRedirect(route('ideas.show', $map));
        $this->actingAs($outsider)->post(route('ideas.nodes.comments.store', [$map, $node]), ['body' => 'Fremd'])->assertForbidden();

        $this->assertSame(1, $node->comments()->count());
        $this->actingAs($owner)->get(route('ideas.nodes.comments', [$map, $node]))->assertOk()->assertSee('Welche Region zuerst?')->assertSee('Vera Leserin');
        $this->actingAs($owner)->getJson(route('ideas.maps.tree', $map))->assertOk()
            ->assertJsonFragment(['sqid' => $node->sqid, 'comment_count' => 1]);
        $this->actingAs($owner)->get(route('ideas.show', $map))->assertOk()->assertSee('"comment_count":1', false);
    }

    /** MVP-1018: Anhänge am Knoten — sehen mit der Karte, hochladen nur mit Bearbeitungsrecht. */
    public function test_node_attachments_follow_the_map_rights(): void {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $maps = app(IdeaMapService::class);
        $owner = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $viewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $map = $maps->create($this->organization, $owner, 'Strategie');
        $node = app(IdeaNodeService::class)->create($map, $map->rootNode()->firstOrFail(), 'Messeauftritt', $owner);
        $maps->shareWithUser($map, $viewer, IdeaShareRole::Viewer, $owner);
        $file = \Illuminate\Http\UploadedFile::fake()->create('standplan.pdf', 20, 'application/pdf');

        $this->actingAs($viewer)->post(route('ideas.nodes.attachments.store', [$map, $node]), ['file' => $file])->assertForbidden();
        $this->actingAs($owner)->post(route('ideas.nodes.attachments.store', [$map, $node]), ['file' => $file])->assertRedirect(route('ideas.show', $map));

        $attachment = $node->attachments()->sole();
        $this->actingAs($viewer)->get(route('ideas.nodes.attachments', [$map, $node]))->assertOk()->assertSee('standplan.pdf')->assertDontSee(__('ideas.attachments.action.add'));
        $this->actingAs($viewer)->get(\App\Http\Controllers\Attachments\AttachmentController::downloadUrl($attachment))->assertOk();
        $this->actingAs($owner)->getJson(route('ideas.maps.tree', $map))->assertJsonFragment(['sqid' => $node->sqid, 'attachment_count' => 1]);
    }
}
