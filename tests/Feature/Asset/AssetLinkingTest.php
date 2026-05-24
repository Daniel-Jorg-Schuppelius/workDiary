<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetLinkingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Asset\AssetOwnership;
use App\Models\{Asset, DiaryEntry, MaterialUsage, Project, Timesheet, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetLinkingTest extends TestCase {
    use RefreshDatabase;

    public function test_asset_is_linkable_with_diary_entry_and_material_usage(): void {
        $user = User::factory()->user()->create();
        $asset = Asset::factory()->create([
            'organization_id' => $user->organization_id,
            'customer_id' => null,
            'owned_by' => AssetOwnership::Organization->value,
        ]);

        $entry = DiaryEntry::factory()->for($user)->create([
            'organization_id' => $user->organization_id,
            'asset_id' => $asset->id,
        ]);

        $project = Project::factory()->create([
            'organization_id' => $user->organization_id,
        ]);
        $timesheet = Timesheet::query()->create([
            'organization_id' => $user->organization_id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'kind' => 'project',
            'work_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $usage = MaterialUsage::query()->create([
            'organization_id' => $user->organization_id,
            'timesheet_id' => $timesheet->id,
            'asset_id' => $asset->id,
            'description' => 'Filtereinsatz',
            'quantity' => '1.000',
            'unit' => 'Stk.',
            'unit_price' => '10.0000',
            'tax_rate' => '19.00',
        ]);

        $this->assertSame($asset->id, $entry->refresh()->asset?->id);
        $this->assertSame($asset->id, $usage->refresh()->asset?->id);
        $this->assertTrue($asset->refresh()->diaryEntries()->whereKey($entry->id)->exists());
        $this->assertTrue($asset->refresh()->materialUsages()->whereKey($usage->id)->exists());
    }
}
