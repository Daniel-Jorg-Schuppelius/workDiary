<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LikeSearchEscapingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{Customer, Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Whitebox 2026-07-10 (SQL-Minor): `whereLikeEscaped` escapt `%`/`_` in
 * Nutzereingaben, damit sie nicht als Wildcards wirken (explizites
 * ESCAPE '!' — portabel über MySQL und SQLite, anders als Backslash).
 */
class LikeSearchEscapingTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $creator = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        foreach (['a_c GmbH', 'abc GmbH', '100% Cotton'] as $name) {
            Customer::create([
                'organization_id' => $this->org->id,
                'name' => $name,
                'created_by' => $creator->id,
            ]);
        }
    }

    public function test_underscore_is_literal_not_wildcard(): void {
        $hits = Customer::query()->whereLikeEscaped('name', 'a_c')->pluck('name');

        $this->assertSame(['a_c GmbH'], $hits->all(), 'Ein rohes LIKE würde auch "abc GmbH" treffen.');
    }

    public function test_percent_is_literal_not_wildcard(): void {
        $this->assertSame(
            ['100% Cotton'],
            Customer::query()->whereLikeEscaped('name', '100%')->pluck('name')->all(),
        );
        // Reine Wildcard-Eingabe matcht nichts (statt ALLES).
        $this->assertSame(0, Customer::query()->whereLikeEscaped('name', '%%%')->count());
    }

    public function test_or_variant_and_prefix_side(): void {
        $hits = Customer::query()
            ->where(function ($q): void {
                $q->whereLikeEscaped('name', 'a_c')
                    ->orWhereLikeEscaped('name', '100%');
            })
            ->pluck('name');

        $this->assertEqualsCanonicalizing(['a_c GmbH', '100% Cotton'], $hits->all());

        $this->assertSame(['abc GmbH'], Customer::query()->whereLikeEscaped('name', 'abc', 'prefix')->pluck('name')->all());
    }
}
