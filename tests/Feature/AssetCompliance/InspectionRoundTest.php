<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InspectionRoundTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\AssetCompliance;

use App\Enums\AssetCompliance\AssetInspectionRoundStatus;
use App\Models\Asset\Asset;
use App\Models\AssetCompliance\{AssetComplianceProfile, AssetInspectionRound};
use App\Models\Platform\User;
use App\Services\AssetCompliance\AssetComplianceService;
use Database\Seeders\AssetComplianceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-899: Prüfmittelrunde — Soll-Liste, Scan, Schnellerfassung, Fehlende. */
final class InspectionRoundTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->seed(AssetComplianceCatalogSeeder::class);
    }

    private function dutyFor(string $name, string $assetNo, string $location, string $dueOn): Asset {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => $name, 'asset_no' => $assetNo, 'location_text' => $location]);
        $profile = AssetComplianceProfile::query()->whereNull('organization_id')->where('code', 'uvv_general')->firstOrFail();
        $assignment = app(AssetComplianceService::class)->assign($profile, $asset, $this->admin);
        $assignment->forceFill(['next_due_on' => $dueOn])->save();

        return $asset;
    }

    public function test_round_freezes_due_duties_scan_opens_capture_and_close_leaves_missing(): void {
        $drill = $this->dutyFor('Bohrhammer', 'A-100', 'Halle 1', now()->subDays(3)->toDateString());
        $this->dutyFor('Leiter', 'A-101', 'Halle 1', now()->addDays(10)->toDateString());
        $this->dutyFor('Kompressor', 'A-102', 'Halle 1', now()->addDays(90)->toDateString());
        $this->dutyFor('Säge', 'A-200', 'Halle 2', now()->addDays(5)->toDateString());

        $this->actingAs($this->admin)->get(route('asset-compliance.rounds.create'))->assertOk()->assertSee('Halle 1');
        $this->actingAs($this->admin)->post(route('asset-compliance.rounds.store'), [
            'name' => 'UVV Halle 1', 'due_until' => now()->addDays(30)->toDateString(), 'location_text' => 'Halle 1',
        ])->assertRedirect();

        $round = AssetInspectionRound::query()->firstOrFail();
        $this->assertSame(2, $round->items()->count(), 'nur fällige Pflichten des Standorts');

        $show = $this->actingAs($this->admin)->get(route('asset-compliance.rounds.show', $round))->assertOk();
        $show->assertSee('data-nfc-read="#code"', false);
        $this->actingAs($this->admin)->get(route('assets.show', $drill))->assertOk()->assertSee('data-nfc-write="' . route('assets.show', $drill) . '"', false);
        $show->assertSee('Bohrhammer')->assertSee(__('inspection_round.overdue'))->assertDontSee('Kompressor');

        $item = $round->items()->where('asset_id', $drill->id)->firstOrFail();
        $this->actingAs($this->admin)->post(route('asset-compliance.rounds.scan', $round), ['code' => url('/assets/' . $drill->sqid)])
            ->assertRedirect(route('asset-compliance.rounds.capture', [$round, $item]));
        $this->actingAs($this->admin)->post(route('asset-compliance.rounds.scan', $round), ['code' => 'A-200'])
            ->assertSessionHas('error');

        $this->actingAs($this->admin)->get(route('asset-compliance.rounds.capture', [$round, $item]))->assertOk();
        $this->actingAs($this->admin)->post(route('asset-compliance.rounds.capture.store', [$round, $item]), ['result' => 'passed', 'signature_name' => 'M. Muster'])
            ->assertRedirect(route('asset-compliance.rounds.show', $round));
        $this->assertNotNull($item->fresh()->asset_inspection_event_id);
        $this->assertTrue($drill->fresh()->next_inspection_on->greaterThan(now()), 'Fälligkeit rückt vor');

        $this->actingAs($this->admin)->post(route('asset-compliance.rounds.scan', $round), ['code' => 'A-100'])
            ->assertSessionHas('warning');

        $this->actingAs($this->admin)->post(route('asset-compliance.rounds.close', $round))->assertRedirect();
        $this->assertSame(AssetInspectionRoundStatus::Closed, $round->fresh()->status);
        $this->actingAs($this->admin)->get(route('asset-compliance.rounds.index'))->assertOk()->assertSee('1 / 2');
    }

    public function test_empty_selection_is_rejected(): void {
        $this->actingAs($this->admin)->post(route('asset-compliance.rounds.store'), ['name' => 'Leer', 'due_until' => now()->addDay()->toDateString(), 'location_text' => 'Nirgendwo'])
            ->assertSessionHasErrors('due_until');
        $this->assertSame(0, AssetInspectionRound::query()->count());
    }
}
