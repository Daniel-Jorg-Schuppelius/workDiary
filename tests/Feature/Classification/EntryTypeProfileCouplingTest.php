<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntryTypeProfileCouplingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Classification;

use App\Models\{EntryType, Organization};
use App\Services\Classification\BranchProfileInstaller;
use Database\Seeders\EntryTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Default-Eintragstypen sind ans Branchenprofil gekoppelt: Erstausstattung
 * nur für Orgs ohne Typen (Observer/Seeder), profilfremde Spezial-Typen
 * tauchen nicht mehr auf und gelöschte Typen bleiben gelöscht — der
 * Deploy-Seeder (db:seed bei jedem Deploy) fasst bestehende Orgs nicht an.
 */
class EntryTypeProfileCouplingTest extends TestCase {
    use RefreshDatabase;

    /** @return array<int, string> */
    private function slugsFor(Organization $org): array {
        return EntryType::query()->withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->orderBy('sort')
            ->get()
            ->map(fn (EntryType $type): string => $type->slug)
            ->all();
    }

    public function test_new_organization_bootstraps_neutral_defaults(): void {
        $org = Organization::factory()->create();

        $this->assertSame([EntryType::SLUG_GENERAL, EntryType::SLUG_SERVICE], $this->slugsFor($org));
    }

    public function test_new_organization_with_profile_bootstraps_profile_types(): void {
        $org = Organization::factory()->create([
            'settings' => ['branch_profile_code' => 'pflege'],
        ]);

        $this->assertSame([EntryType::SLUG_GENERAL, EntryType::SLUG_CARE_VISIT], $this->slugsFor($org));
    }

    public function test_seeder_does_not_resurrect_deleted_types(): void {
        $org = Organization::factory()->create();
        EntryType::query()->withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('slug', EntryType::SLUG_SERVICE)
            ->delete();

        $this->seed(EntryTypeSeeder::class);

        $this->assertSame([EntryType::SLUG_GENERAL], $this->slugsFor($org));
    }

    public function test_seeder_bootstraps_empty_org_according_to_profile(): void {
        $org = Organization::factory()->create();
        EntryType::query()->withoutGlobalScopes()->where('organization_id', $org->id)->delete();
        $org->forceFill(['settings' => ['branch_profile_code' => 'it']])->save();

        $this->seed(EntryTypeSeeder::class);

        $this->assertSame(
            [EntryType::SLUG_GENERAL, EntryType::SLUG_SERVICE, EntryType::SLUG_IT_TICKET],
            $this->slugsFor($org)
        );
    }

    public function test_installer_adds_missing_coupled_types_without_touching_existing(): void {
        $org = Organization::factory()->create();
        EntryType::query()->withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('slug', EntryType::SLUG_GENERAL)
            ->update(['label' => 'Mein Typ']);

        $result = (new BranchProfileInstaller)->install($org, 'it');

        $this->assertSame(1, $result['created']['entry_types']);
        $this->assertSame(2, $result['skipped']['entry_types']);
        $this->assertSame(
            [EntryType::SLUG_GENERAL, EntryType::SLUG_SERVICE, EntryType::SLUG_IT_TICKET],
            $this->slugsFor($org)
        );
        $this->assertSame('Mein Typ', EntryType::query()->withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('slug', EntryType::SLUG_GENERAL)
            ->value('label'));

        $again = (new BranchProfileInstaller)->install($org, 'it');
        $this->assertSame(0, $again['created']['entry_types']);
        $this->assertSame(3, $again['skipped']['entry_types']);
    }
}
