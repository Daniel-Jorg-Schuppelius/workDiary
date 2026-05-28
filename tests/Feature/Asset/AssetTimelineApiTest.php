<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetTimelineApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Asset\AssetOwnership;
use App\Enums\Protocol\ProtocolType;
use App\Models\{Asset, Attachment, DiaryEntry, MaterialUsage, Project, Protocol, Timesheet, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class AssetTimelineApiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_asset_timeline_api_returns_linked_events(): void {
        $this->setUpOrganization();

        $user = User::factory()->admin()->create([
            'organization_id' => $this->organization->id,
        ]);
        Sanctum::actingAs($user);

        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => null,
            'owned_by' => AssetOwnership::Organization->value,
        ]);
        $asset->audit('asset.created', ['asset_no' => $asset->asset_no]);

        DiaryEntry::factory()->for($user)->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'title' => 'Auftrag mit Asset',
        ]);

        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $timesheet = Timesheet::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'kind' => 'project',
            'work_date' => now()->toDateString(),
            'status' => 'draft',
        ]);
        MaterialUsage::query()->create([
            'organization_id' => $this->organization->id,
            'timesheet_id' => $timesheet->id,
            'asset_id' => $asset->id,
            'description' => 'Dichtung',
            'quantity' => '2.000',
            'unit' => 'Stk.',
            'unit_price' => '4.5000',
            'tax_rate' => '19.00',
        ]);

        Protocol::factory()->for($asset, 'subject')->state([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $user->id,
            'type' => ProtocolType::Service->value,
            'title' => 'Asset-Protokoll',
        ])->create();

        Attachment::query()->create([
            'organization_id' => $this->organization->id,
            'attachable_type' => Asset::class,
            'attachable_id' => $asset->id,
            'user_id' => $user->id,
            'disk' => 'local',
            'path' => 'attachments/test/asset.txt',
            'original_name' => 'asset.txt',
            'mime' => 'text/plain',
            'size' => 20,
        ]);

        $response = $this->getJson(route('api.assets.timeline', ['asset' => $asset, 'limit' => 50]));

        $response->assertOk()->assertJsonStructure([
            'data' => [
                '*' => [
                    'kind',
                    'occurred_at',
                    'payload',
                ],
            ],
        ]);

        /** @var array<int, array{kind?: string}> $rows */
        $rows = $response->json('data');
        $kinds = [];
        foreach ($rows as $row) {
            if (isset($row['kind'])) {
                $kinds[] = $row['kind'];
            }
        }

        $this->assertContains('asset.audit', $kinds);
        $this->assertContains('order.linked', $kinds);
        $this->assertContains('protocol.linked', $kinds);
        $this->assertContains('material.linked', $kinds);
        $this->assertContains('attachment.linked', $kinds);
    }
}
