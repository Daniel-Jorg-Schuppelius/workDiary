<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Ideas;

use App\Models\{IdeaMap, IdeaNode, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 054, MVP-138: Import aus FreeMind (`.mm`) und OPML → neue, private
 * Karte des Importeurs. XXE-gehärtet (externe Entities werden nicht expandiert),
 * unbekannte Formate werden abgewiesen.
 */
final class IdeaMapImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    private function upload(string $name, string $content): UploadedFile {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_opml_import_creates_map_and_tree(): void {
        $opml = '<?xml version="1.0" encoding="UTF-8"?><opml version="2.0">'
            . '<head><title>Meta</title></head><body>'
            . '<outline text="Wurzel"><outline text="Kind A"/><outline text="Kind B" _note="Eine Notiz"/></outline>'
            . '</body></opml>';

        $this->actingAs($this->user)->post(route('ideas.import'), ['file' => $this->upload('karte.opml', $opml)])
            ->assertRedirect();

        $map = IdeaMap::query()->where('title', 'Wurzel')->firstOrFail();
        $this->assertSame($this->user->id, (int) $map->owner_user_id);
        $this->assertSame('private', $map->visibility->value);

        $root = $map->rootNode()->firstOrFail();
        $this->assertSame('Wurzel', $root->title);
        $children = $root->children()->orderBy('sort_order')->pluck('title')->all();
        $this->assertSame(['Kind A', 'Kind B'], $children);
        $this->assertSame('Eine Notiz', $map->nodes()->where('title', 'Kind B')->value('note'));
    }

    public function test_freemind_import_creates_map_and_tree(): void {
        $mm = '<map version="1.0.1"><node TEXT="Zentrale Idee">'
            . '<node TEXT="Ast 1"><node TEXT="Blatt"/></node><node TEXT="Ast 2"/>'
            . '</node></map>';

        $this->actingAs($this->user)->post(route('ideas.import'), ['file' => $this->upload('mindmap.mm', $mm)])
            ->assertRedirect();

        $map = IdeaMap::query()->where('title', 'Zentrale Idee')->firstOrFail();
        $this->assertSame(4, $map->nodes()->count()); // Wurzel + Ast1 + Blatt + Ast2
        $root = $map->rootNode()->firstOrFail();
        $this->assertSame(['Ast 1', 'Ast 2'], $root->children()->orderBy('sort_order')->pluck('title')->all());
    }

    public function test_freemind_import_creates_cross_links_from_arrowlinks(): void {
        $mm = '<map version="1.0.1"><node ID="ID_ROOT" TEXT="Wurzel">'
            . '<node ID="ID_A" TEXT="A"><arrowlink DESTINATION="ID_B" MIDDLE_LABEL="hängt ab"/></node>'
            . '<node ID="ID_B" TEXT="B"/>'
            . '</node></map>';

        $this->actingAs($this->user)->post(route('ideas.import'), ['file' => $this->upload('links.mm', $mm)])
            ->assertRedirect();

        $map = IdeaMap::query()->where('title', 'Wurzel')->firstOrFail();
        $a = $map->nodes()->where('title', 'A')->firstOrFail();
        $b = $map->nodes()->where('title', 'B')->firstOrFail();

        $this->assertDatabaseHas('idea_node_links', [
            'idea_map_id' => $map->id,
            'source_node_id' => $a->id,
            'target_node_id' => $b->id,
            'label' => 'hängt ab',
        ]);
        $this->assertSame(1, $map->links()->count());
    }

    public function test_import_rejects_unsupported_format(): void {
        $this->actingAs($this->user)->post(route('ideas.import'), ['file' => $this->upload('x.xml', '<html><body>nope</body></html>')])
            ->assertRedirect()
            ->assertSessionHasErrors('file');

        $this->assertSame(0, IdeaMap::query()->count());
    }

    public function test_import_does_not_expand_external_xxe_entities(): void {
        $secretFile = tempnam(sys_get_temp_dir(), 'xxe');
        file_put_contents($secretFile, 'TOPSECRET-DATA');
        $xxe = '<?xml version="1.0"?><!DOCTYPE opml [<!ENTITY xxe SYSTEM "file://' . $secretFile . '">]>'
            . '<opml version="2.0"><body><outline text="&xxe;"/></body></opml>';

        $this->actingAs($this->user)->post(route('ideas.import'), ['file' => $this->upload('evil.opml', $xxe)]);
        @unlink($secretFile);

        // Egal ob Parse fehlschlägt oder ein leerer Knoten entsteht: der
        // Dateiinhalt darf NIE in der DB landen.
        $this->assertSame(0, IdeaNode::query()->where('title', 'like', '%TOPSECRET%')->count());
    }
}
