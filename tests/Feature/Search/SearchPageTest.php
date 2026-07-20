<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Search;

use App\Models\{Attachment, Comment, Customer, DiaryEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vollaudit 2026-07 (M8): Vollergebnisseite /suche (globale-suche.md AK 2–3)
 * mit Domänen-/Zeitraum-/Kunden-Filter sowie die neuen MVP-014-Domänen
 * Kommentare und Anhang-Metadaten (Sichtbarkeit über den Auftrag).
 */
final class SearchPageTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_full_results_page_groups_hits_and_filters_by_domain(): void {
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Acme Industries GmbH',
        ]);
        DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'title' => 'Acme Wartung Halle 3',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('search.index', ['q' => 'acme']))
            ->assertOk();
        $keys = collect($response->viewData('groups'))->pluck('key')->all();
        $this->assertContains('customers', $keys);
        $this->assertContains('diary', $keys);

        $filtered = $this->actingAs($this->user)
            ->get(route('search.index', ['q' => 'acme', 'domain' => 'customers']))
            ->assertOk();
        $this->assertSame(['customers'], collect($filtered->viewData('groups'))->pluck('key')->all());
    }

    public function test_comments_domain_respects_diary_visibility(): void {
        $mine = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'title' => 'Eigener Auftrag',
        ]);
        $foreignUser = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $foreign = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $foreignUser->id,
            'title' => 'Fremder Auftrag',
        ]);

        Comment::query()->create([
            'organization_id' => $this->organization->id,
            'commentable_type' => DiaryEntry::class,
            'commentable_id' => $mine->id,
            'user_id' => $this->user->id,
            'body' => 'Spezialventil DX9 nachbestellen',
        ]);
        Comment::query()->create([
            'organization_id' => $this->organization->id,
            'commentable_type' => DiaryEntry::class,
            'commentable_id' => $foreign->id,
            'user_id' => $foreignUser->id,
            'body' => 'Spezialventil DX9 defekt gemeldet',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('search.index', ['q' => 'Spezialventil', 'domain' => 'comments']))
            ->assertOk();
        $groups = collect($response->viewData('groups'));
        $this->assertCount(1, $groups);
        $this->assertCount(1, $groups->first()['items']);
        $this->assertStringContainsString('nachbestellen', $groups->first()['items'][0]['title']);
    }

    public function test_attachment_metadata_searchable_for_own_diary(): void {
        $mine = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'title' => 'Auftrag mit Anhang',
        ]);
        Attachment::factory()->create([
            'organization_id' => $this->organization->id,
            'attachable_type' => DiaryEntry::class,
            'attachable_id' => $mine->id,
            'user_id' => $this->user->id,
            'original_name' => 'pruefprotokoll_dx9.pdf',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('search.index', ['q' => 'pruefprotokoll', 'domain' => 'attachments']))
            ->assertOk();
        $groups = collect($response->viewData('groups'));
        $this->assertCount(1, $groups);
        $this->assertSame('pruefprotokoll_dx9.pdf', $groups->first()['items'][0]['title']);
    }

    public function test_type_ahead_returns_all_results_link(): void {
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Acme Industries GmbH',
        ]);

        $this->actingAs($this->user)
            ->getJson(route('api.internal.search', ['q' => 'acme']))
            ->assertOk()
            ->assertJsonPath('allUrl', route('search.index', ['q' => 'acme']));
    }
}
