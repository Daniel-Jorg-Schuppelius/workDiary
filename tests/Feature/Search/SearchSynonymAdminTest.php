<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchSynonymAdminTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Models\SearchSynonymGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Feature 153 (MVP-772): Pflege der Such-Synonyme unter `organization.update`. */
final class SearchSynonymAdminTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_admin_manages_groups(): void {
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->get(route('admin.search-synonyms.index'))->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.search-synonyms.store'), ['terms' => "SMTP\nMailrelay, smtp\nSend Connector"])
            ->assertRedirect(route('admin.search-synonyms.index'));

        $group = SearchSynonymGroup::query()->firstOrFail();
        $this->assertSame(['SMTP', 'Mailrelay', 'Send Connector'], $group->terms);
        $this->assertSame((int) $this->organization->id, (int) $group->organization_id);

        $this->actingAs($admin)->patch(route('admin.search-synonyms.update', $group), ['terms' => "smtp\npostfix"])->assertRedirect();
        $this->assertSame(['smtp', 'postfix'], $group->fresh()->terms);

        $this->actingAs($admin)->post(route('admin.search-synonyms.toggle', $group))->assertRedirect();
        $this->assertFalse($group->fresh()->active);

        $this->actingAs($admin)->delete(route('admin.search-synonyms.destroy', $group))->assertRedirect();
        $this->assertSame(0, SearchSynonymGroup::query()->count());
    }

    public function test_a_group_needs_two_different_terms(): void {
        $this->actingAs($this->orgAdmin())
            ->post(route('admin.search-synonyms.store'), ['terms' => "smtp\nSMTP"])
            ->assertSessionHasErrors('terms');

        $this->assertSame(0, SearchSynonymGroup::query()->count());
    }

    public function test_preset_import_skips_groups_that_already_exist(): void {
        $admin = $this->orgAdmin();
        SearchSynonymGroup::query()->create(['terms' => ['SMTP', 'eigenes Relay'], 'active' => true]);

        $this->actingAs($admin)->post(route('admin.search-synonyms.preset'), ['preset' => 'it'])->assertRedirect();

        $presetGroups = count((array) config('search.synonym_presets.it'));
        // Die smtp-Gruppe der Vorlage überschneidet sich mit der eigenen Pflege.
        $this->assertSame($presetGroups, SearchSynonymGroup::query()->count());

        $this->actingAs($admin)->post(route('admin.search-synonyms.preset'), ['preset' => 'it'])->assertRedirect();
        $this->assertSame($presetGroups, SearchSynonymGroup::query()->count());
    }

    public function test_plain_users_may_not_manage_synonyms(): void {
        $user = $this->orgUser();

        $this->actingAs($user)->get(route('admin.search-synonyms.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.search-synonyms.store'), ['terms' => "a1\nb2"])->assertForbidden();
    }
}
