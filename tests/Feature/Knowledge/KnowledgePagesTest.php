<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePagesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Enums\Knowledge\KnowledgePageStatus;
use App\Enums\User\Permission;
use App\Models\Audit\AuditLog;
use App\Models\Knowledge\{KnowledgePage, KnowledgePageVersion, KnowledgeSpace, KnowledgeSpaceReader};
use App\Models\Platform\{Team, User};
use App\Services\Collections\Import\{ImportedDocument, KnowledgeImportService};
use App\Services\Knowledge\Pages\KnowledgePageService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Wissensseiten (Feature 191, MVP-1664/1665): Fassungen, Konflikt beim
 * Speichern, Freigabe, Leser sehen die freigegebene Fassung, Zurückholen,
 * Papierkorb, Leserecht je Bereich, sicheres Markdown und Import.
 */
final class KnowledgePagesTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private KnowledgeSpace $space;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->space = KnowledgeSpace::factory()->create(['organization_id' => $this->organization->id, 'title' => 'Handbuch']);
    }

    public function test_a_page_is_created_edited_and_approved_with_versions(): void {
        $this->actingAs($this->admin)->post(route('knowledge.pages.store'), [
            'space' => $this->space->sqid,
            'kind' => 'page',
            'title' => 'Arbeitsplatz einrichten',
            'body' => "Erste Fassung\nZeile zwei",
            'tags' => 'Einarbeitung, IT',
        ])->assertSessionHasNoErrors();

        $page = KnowledgePage::query()->where('title', 'Arbeitsplatz einrichten')->firstOrFail();
        $this->assertSame(1, $page->version);
        $this->assertSame(KnowledgePageStatus::Draft, $page->status);
        $this->assertNull($page->approved_version);
        $this->assertSame((int) $this->admin->id, (int) $page->owner_user_id);
        $this->assertSame(['Einarbeitung', 'IT'], $page->tags->pluck('name')->sort()->values()->all());

        $this->actingAs($this->admin)->post(route('knowledge.pages.approve', $page))->assertSessionHasNoErrors();
        $page->refresh();
        $this->assertSame(KnowledgePageStatus::Valid, $page->status);
        $this->assertSame(1, $page->approved_version);
        $this->assertTrue(AuditLog::query()->where('event', 'knowledge_page.approved')->exists());

        // Neue Fassung: Entwurf, Leser sehen weiter Fassung 1.
        $this->actingAs($this->admin)->put(route('knowledge.pages.update', $page), [
            'title' => 'Arbeitsplatz einrichten',
            'body' => "Zweite Fassung\nZeile zwei",
            'base_version' => 1,
            'note' => 'Text überarbeitet',
        ])->assertSessionHasNoErrors();
        $page->refresh();
        $this->assertSame(2, $page->version);
        $this->assertSame(1, $page->approved_version);
        $this->assertSame(KnowledgePageStatus::Draft, $page->status);
        $this->assertSame(2, KnowledgePageVersion::query()->where('knowledge_page_id', $page->id)->count());

        $reader = $this->actorIn($this->organization, [Permission::KnowledgePageViewAny]);
        $this->actingAs($reader)->get(route('knowledge.pages.show', $page))
            ->assertOk()
            ->assertSee('Erste Fassung')
            ->assertDontSee('Zweite Fassung');
        $this->actingAs($this->admin)->get(route('knowledge.pages.show', $page))
            ->assertOk()
            ->assertSee('Zweite Fassung');

        $this->actingAs($this->admin)->get(route('knowledge.pages.versions', ['page' => $page, 'from' => 1, 'to' => 2]))
            ->assertOk()
            ->assertSee('Erste Fassung')
            ->assertSee('Zweite Fassung')
            ->assertSee('Text überarbeitet');
    }

    public function test_saving_against_an_outdated_version_reports_a_conflict(): void {
        $page = $this->page('Konfliktseite', 'Start');
        app(KnowledgePageService::class)->update($page, $this->admin, ['title' => 'Konfliktseite', 'body' => 'Von jemand anderem'], 1);

        $this->actingAs($this->admin)->put(route('knowledge.pages.update', $page), [
            'title' => 'Konfliktseite',
            'body' => 'Meine Änderung',
            'base_version' => 1,
        ])->assertSessionHasErrors('base_version');

        $this->assertSame('Von jemand anderem', $page->refresh()->body);
        $this->assertSame(2, $page->version);
    }

    public function test_a_reader_never_sees_a_page_without_approved_version(): void {
        $page = $this->page('Nur Entwurf', 'Geheimer Entwurf');
        $reader = $this->actorIn($this->organization, [Permission::KnowledgePageViewAny]);

        $this->actingAs($reader)->get(route('knowledge.pages.show', $page))->assertForbidden();
        $this->actingAs($reader)->get(route('knowledge.pages.index'))->assertOk()->assertDontSee('Nur Entwurf');
    }

    public function test_an_older_version_is_restored_as_new_draft(): void {
        $page = $this->page('Zurückholen', 'Alt');
        $service = app(KnowledgePageService::class);
        $service->approve($page, $this->admin);
        $service->update($page, $this->admin, ['title' => 'Zurückholen', 'body' => 'Neu'], 1);

        $version = KnowledgePageVersion::query()->where('knowledge_page_id', $page->id)->where('version', 1)->firstOrFail();
        $this->actingAs($this->admin)->post(route('knowledge.pages.versions.restore', [$page, $version]))->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertSame(3, $page->version);
        $this->assertSame('Alt', $page->body);
        $this->assertSame(KnowledgePageStatus::Draft, $page->status);
    }

    public function test_review_sets_the_next_date_and_expired_validity_blocks_approval(): void {
        $page = $this->page('Prüfseite', 'Text');
        $page->forceFill(['review_interval_months' => 6])->save();
        $service = app(KnowledgePageService::class);
        $service->approve($page, $this->admin);
        $this->assertSame(CarbonImmutable::today()->addMonthsNoOverflow(6)->toDateString(), $page->refresh()->next_review_on?->toDateString());

        $this->actingAs($this->admin)->post(route('knowledge.pages.outdate', $page))->assertSessionHasNoErrors();
        $this->assertSame(KnowledgePageStatus::Outdated, $page->refresh()->status);

        $this->actingAs($this->admin)->post(route('knowledge.pages.review', $page))->assertSessionHasNoErrors();
        $this->assertSame(KnowledgePageStatus::Valid, $page->refresh()->status);

        $page->forceFill(['valid_until' => CarbonImmutable::yesterday()->toDateString()])->save();
        $this->actingAs($this->admin)->post(route('knowledge.pages.review', $page))->assertSessionHasErrors('valid_until');
    }

    public function test_trash_restore_and_permanent_delete(): void {
        $page = $this->page('Wegwerfseite', 'Inhalt');
        $this->actingAs($this->admin)->delete(route('knowledge.pages.destroy', $page))->assertRedirect();
        $this->assertSoftDeleted($page);

        $this->actingAs($this->admin)->get(route('knowledge.pages.trash'))->assertOk()->assertSee('Wegwerfseite');
        $this->actingAs($this->admin)->post(route('knowledge.pages.restore', $page->sqid))->assertRedirect();
        $this->assertNotSoftDeleted($page);

        app(KnowledgePageService::class)->trash($page->refresh(), $this->admin);
        $manager = $this->actorIn($this->organization, [Permission::KnowledgePageViewAny, Permission::KnowledgePageEdit, Permission::KnowledgePageManage]);
        $this->actingAs($manager)->delete(route('knowledge.pages.force-delete', $page->sqid))->assertForbidden();

        $this->actingAs($this->admin)->delete(route('knowledge.pages.force-delete', $page->sqid))->assertRedirect();
        $this->assertDatabaseMissing('knowledge_pages', ['id' => $page->id]);
        $this->assertDatabaseMissing('knowledge_page_versions', ['knowledge_page_id' => $page->id]);
    }

    public function test_empty_trash_command_purges_after_the_retention_period(): void {
        $old = $this->page('Alt im Papierkorb', 'x');
        $fresh = $this->page('Frisch im Papierkorb', 'y');
        $service = app(KnowledgePageService::class);
        $service->trash($old, $this->admin);
        $service->trash($fresh, $this->admin);
        KnowledgePage::query()->withTrashed()->whereKey($old->id)->update(['deleted_at' => now()->subDays(KnowledgePage::TRASH_DAYS + 1)]);

        $this->artisan('knowledge-pages:empty-trash')->assertSuccessful();

        $this->assertDatabaseMissing('knowledge_pages', ['id' => $old->id]);
        $this->assertSoftDeleted($fresh);
    }

    public function test_a_restricted_space_is_only_readable_for_its_readers(): void {
        $restricted = KnowledgeSpace::factory()->restricted()->create(['organization_id' => $this->organization->id, 'title' => 'Geschäftsleitung']);
        $page = $this->page('Vertrauliche Regel', 'Nur Leitung', $restricted);
        app(KnowledgePageService::class)->approve($page, $this->admin);

        $outsider = $this->actorIn($this->organization, [Permission::KnowledgePageViewAny]);
        $this->actingAs($outsider)->get(route('knowledge.pages.show', $page))->assertForbidden();
        $this->actingAs($outsider)->get(route('knowledge.pages.index'))->assertOk()->assertDontSee('Geschäftsleitung');

        $insider = $this->actorIn($this->organization, [Permission::KnowledgePageViewAny]);
        $team = Team::factory()->create(['organization_id' => $this->organization->id]);
        $insider->teams()->attach($team->id);
        KnowledgeSpaceReader::query()->create(['organization_id' => $this->organization->id, 'knowledge_space_id' => $restricted->id, 'kind' => 'team', 'team_id' => $team->id]);
        $this->actingAs($insider)->get(route('knowledge.pages.show', $page))->assertOk()->assertSee('Nur Leitung');
    }

    public function test_markdown_is_rendered_safely_with_callouts_and_details(): void {
        $page = $this->page('Markdown', implode("\n", [
            '<script>alert(1)</script>',
            '[Klick](javascript:alert(2))',
            ':::achtung Vorsicht <b>heiß</b>',
            'Inhalt im Kasten',
            ':::',
            ':::details Mehr',
            'Versteckter Text',
            ':::',
            '![Fremd](https://tracker.example.test/pixel.png)',
            '| A | B |',
            '| --- | --- |',
            '| 1 | 2 |',
        ]));

        $response = $this->actingAs($this->admin)->get(route('knowledge.pages.show', $page))->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('javascript:alert(2)', $html);
        $this->assertStringContainsString('alert alert-warning', $html);
        $this->assertStringContainsString('Vorsicht &lt;b&gt;heiß&lt;/b&gt;', $html);
        $this->assertStringContainsString('<summary class="collapse-title font-medium">Mehr</summary>', $html);
        $this->assertStringNotContainsString('<img src="https://tracker.example.test/pixel.png"', $html);
        $this->assertStringContainsString('<table>', $html);
    }

    public function test_markdown_files_are_imported_as_drafts(): void {
        $file = UploadedFile::fake()->createWithContent('urlaub.md', "---\ntitle: Urlaub beantragen\ntags: [Personal]\n---\nSo beantragen Sie Urlaub. #Abwesenheit");

        $this->actingAs($this->admin)->post(route('knowledge.pages.import.store'), [
            'space' => $this->space->sqid,
            'files' => [$file],
        ])->assertSessionHasNoErrors();

        $page = KnowledgePage::query()->where('title', 'Urlaub beantragen')->firstOrFail();
        $this->assertSame(KnowledgePageStatus::Draft, $page->status);
        $this->assertStringContainsString('So beantragen Sie Urlaub.', (string) $page->body);
        $this->assertEqualsCanonicalizing(['Personal', 'Abwesenheit'], $page->tags->pluck('name')->all());
    }

    public function test_notebook_import_targets_knowledge_pages_with_sections_and_references(): void {
        $report = app(KnowledgeImportService::class)->import($this->organization, $this->admin, 'obsidian_test', 'obsidian_note', 'Tresor', KnowledgeImportService::TARGET_PAGE, [
            new ImportedDocument(externalId: 'a', title: 'Server', text: 'Siehe [[Backup]]', folders: ['IT'], names: ['Server'], linkNames: ['Backup']),
            new ImportedDocument(externalId: 'b', title: 'Backup', text: 'Täglich um 2 Uhr', folders: ['IT', 'Betrieb'], names: ['Backup']),
        ]);

        $this->assertNotNull($report->space);
        $this->assertSame('Tresor', $report->space->title);
        $server = KnowledgePage::query()->where('title', 'Server')->firstOrFail();
        $backup = KnowledgePage::query()->where('title', 'Backup')->firstOrFail();
        $this->assertSame('IT', $server->section?->title);
        $this->assertSame('Betrieb', $backup->parent?->title);
        $this->assertSame(1, $report->linksResolved);
        $this->assertDatabaseHas('content_references', ['source_id' => $server->id, 'target_id' => $backup->id, 'target_type' => $backup->getMorphClass()]);
    }

    private function page(string $title, string $body, ?KnowledgeSpace $space = null): KnowledgePage {
        return app(KnowledgePageService::class)->create($space ?? $this->space, null, null, $this->admin, ['title' => $title, 'body' => $body]);
    }
}
