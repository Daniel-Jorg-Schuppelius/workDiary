<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgScopedUniqueIndexTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Tenant;

use App\Models\{Holiday, Organization, Tag};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23 (F2/F3): holidays(date, is_recurring) und tags.slug
 * waren DB-weit unique, obwohl beide Modelle org-gescopt sind. Zwei
 * Organisationen müssen denselben Betriebsfeiertag und gleichnamige Tags
 * (mit identischem Slug) anlegen können.
 */
class OrgScopedUniqueIndexTest extends TestCase {
    use RefreshDatabase;

    public function test_two_organizations_can_store_the_same_fixed_holiday(): void {
        $orgA = Organization::factory()->create(['slug' => 'hol-a']);
        $orgB = Organization::factory()->create(['slug' => 'hol-b']);

        $attributes = ['date' => '2026-12-24', 'name' => 'Heiligabend', 'is_recurring' => true, 'recurrence_type' => 'fixed'];

        $a = $this->withOrg($orgA, fn() => Holiday::query()->create($attributes));
        $b = $this->withOrg($orgB, fn() => Holiday::query()->create($attributes));

        $this->assertNotSame($a->id, $b->id);
        $this->assertSame((int) $orgA->id, (int) $a->organization_id);
        $this->assertSame((int) $orgB->id, (int) $b->organization_id);
    }

    public function test_same_tag_name_yields_the_same_slug_in_each_organization(): void {
        $orgA = Organization::factory()->create(['slug' => 'tag-a']);
        $orgB = Organization::factory()->create(['slug' => 'tag-b']);

        $a = $this->withOrg($orgA, fn() => Tag::findOrCreateByName('Wichtig'));
        $b = $this->withOrg($orgB, fn() => Tag::findOrCreateByName('Wichtig'));
        $c = Tag::findOrCreateByNameForOrganization('Wichtig', (int) $orgB->id);

        $this->assertSame('wichtig', $a->slug);
        $this->assertSame('wichtig', $b->slug, 'Die zweite Organisation darf nicht auf „wichtig-2“ ausweichen müssen.');
        $this->assertSame($b->id, $c->id, 'Explizite Org-Variante findet den bestehenden Tag derselben Org.');

        // Innerhalb einer Organisation bleibt der Slug eindeutig.
        $second = $this->withOrg($orgA, fn() => Tag::query()->create(['name' => 'Wichtig!']));
        $this->assertSame('wichtig-2', $second->slug);
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function withOrg(Organization $org, \Closure $callback): mixed {
        $previous = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        app()->instance('currentOrganization', $org);
        try {
            return $callback();
        } finally {
            if ($previous instanceof Organization) {
                app()->instance('currentOrganization', $previous);
            } else {
                app()->forgetInstance('currentOrganization');
            }
        }
    }
}
