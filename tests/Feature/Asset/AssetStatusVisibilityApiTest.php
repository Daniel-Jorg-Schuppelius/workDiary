<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetStatusVisibilityApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Asset\AssetOwnership;
use App\Enums\OpenIssue\{OpenIssueSeverity, OpenIssueSource, OpenIssueStatus, OpenIssueVisibility};
use App\Enums\Protocol\ProtocolType;
use App\Models\{Asset, OpenIssue, Protocol, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class AssetStatusVisibilityApiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_asset_status_visibility_shows_blocked_and_defect_indicators(): void {
        $this->setUpOrganization();

        $user = User::factory()->admin()->create([
            'organization_id' => $this->organization->id,
        ]);
        Sanctum::actingAs($user);

        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'blocked',
            'customer_id' => null,
            'owned_by' => AssetOwnership::Organization->value,
        ]);

        OpenIssue::query()->create([
            'organization_id' => $this->organization->id,
            'subject_type' => Asset::class,
            'subject_id' => $asset->id,
            'source_type' => OpenIssueSource::ProtocolDefect->value,
            'source_ref_id' => null,
            'title' => 'Defekt am Asset',
            'description' => null,
            'category' => 'asset',
            'severity' => OpenIssueSeverity::Critical->value,
            'status' => OpenIssueStatus::Blocked->value,
            'assignee_user_id' => $user->id,
            'due_at' => now()->addDay(),
            'visibility' => OpenIssueVisibility::Internal->value,
            'closed_at' => null,
            'closed_by_user_id' => null,
            'closed_reason' => null,
            'created_by_user_id' => $user->id,
        ]);

        Protocol::factory()->for($asset, 'subject')->state([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $user->id,
            'type' => ProtocolType::Defect->value,
            'title' => 'Defektprotokoll',
        ])->create();

        $response = $this->getJson(route('api.assets.status-visibility', ['asset' => $asset]));

        $response->assertOk();
        $response->assertJsonPath('data.asset_id', $asset->id);
        $response->assertJsonPath('data.status', 'blocked');
        $response->assertJsonPath('data.is_blocked', true);
        $response->assertJsonPath('data.has_defect', true);
        $response->assertJsonPath('data.attention_level', 'critical');
        $response->assertJsonPath('data.open_issues.total', 1);
        $response->assertJsonPath('data.open_issues.blocked', 1);
        $response->assertJsonPath('data.open_issues.critical', 1);
        $response->assertJsonPath('data.defect_protocols.total', 1);
    }
}
