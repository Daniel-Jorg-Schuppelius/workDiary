<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LookupCacheTenantTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Tenant;

use App\Models\{Organization, Tag};
use App\Support\LookupCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, A1: Die Tag-Optionen wurden unter einem globalen
 * Cache-Key abgelegt, obwohl die Query den OrganizationScope trägt — der
 * zweite Mandant bekam fünf Minuten lang die Tags des ersten.
 */
class LookupCacheTenantTest extends TestCase {
    use RefreshDatabase;

    public function test_tag_options_are_cached_per_organization(): void {
        Cache::flush();

        $orgA = Organization::factory()->create(['slug' => 'lookup-a']);
        $orgB = Organization::factory()->create(['slug' => 'lookup-b']);

        $this->withOrg($orgA, fn() => Tag::factory()->create(['name' => 'ZZTAGORGA']));
        $this->withOrg($orgB, fn() => Tag::factory()->create(['name' => 'ZZTAGORGB']));

        $namesA = $this->withOrg($orgA, fn() => LookupCache::tagOptions()->pluck('name')->all());
        $namesB = $this->withOrg($orgB, fn() => LookupCache::tagOptions()->pluck('name')->all());

        $this->assertSame(['ZZTAGORGA'], $namesA);
        $this->assertSame(['ZZTAGORGB'], $namesB, 'Org B darf nicht die gecachten Tags von Org A erhalten.');
    }

    public function test_tag_change_invalidates_only_its_organization(): void {
        Cache::flush();

        $orgA = Organization::factory()->create(['slug' => 'lookup-inv-a']);
        $orgB = Organization::factory()->create(['slug' => 'lookup-inv-b']);

        $this->withOrg($orgA, fn() => Tag::factory()->create(['name' => 'Alpha']));
        $this->withOrg($orgB, fn() => Tag::factory()->create(['name' => 'Beta']));

        $this->withOrg($orgA, fn() => LookupCache::tagOptions());
        $this->withOrg($orgB, fn() => LookupCache::tagOptions());

        // Neuer Tag in Org A → Org A sieht ihn sofort, Org B bleibt unverändert.
        $this->withOrg($orgA, fn() => Tag::factory()->create(['name' => 'Gamma']));

        $namesA = $this->withOrg($orgA, fn() => LookupCache::tagOptions()->pluck('name')->sort()->values()->all());
        $namesB = $this->withOrg($orgB, fn() => LookupCache::tagOptions()->pluck('name')->all());

        $this->assertSame(['Alpha', 'Gamma'], $namesA);
        $this->assertSame(['Beta'], $namesB);
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
