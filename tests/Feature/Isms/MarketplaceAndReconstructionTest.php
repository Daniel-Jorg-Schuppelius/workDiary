<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MarketplaceAndReconstructionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Models\{Organization, User};
use App\Services\Classification\BranchProfileInstaller;
use App\Services\Isms\{AssessmentSnapshotService, ConformityService, ScopeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Nachtrag 042 (Profil-Versionierung/Marketplace) + 046b
 * (Stichtags-Rekonstruktion): Versions-Tracking + Update-Erkennung,
 * Re-Apply idempotent, JSON-Import mit Domänen-Validierung,
 * min_app_version-Guard; Bewertungs-Snapshots rekonstruieren den Stand zu T.
 */
final class MarketplaceAndReconstructionTest extends TestCase {
    use RefreshDatabase;

    private function adminOrg(): array {
        $org = Organization::factory()->create();
        app()->instance('currentOrganization', $org);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        return [$org, $admin];
    }

    public function test_install_tracks_version_and_reapply_is_idempotent(): void {
        [$org, $admin] = $this->adminOrg();

        $first = app(BranchProfileInstaller::class)->install($org, 'it', $admin);
        $this->assertSame(
            $first['version'],
            data_get($org->refresh()->settings, 'branch_profile_versions.it'),
        );

        // Re-Apply ohne force: nichts wird doppelt angelegt.
        $second = app(BranchProfileInstaller::class)->install($org, 'it', $admin);
        $this->assertSame(0, array_sum((array) $second['created']));
    }

    public function test_marketplace_import_validates_domains_and_installs(): void {
        [, $admin] = $this->adminOrg();

        // Unbekannte Domäne → Ablehnung.
        $bad = UploadedFile::fake()->createWithContent('bad.json', (string) json_encode([
            'code' => 'custom-x',
            'label' => 'Custom X',
            'version' => 2,
            'classifications' => ['erfundene_domaene' => [['code' => 'x', 'label' => 'X']]],
        ]));
        $this->actingAs($admin)->post(route('admin.branch-profiles.import'), ['file' => $bad])
            ->assertRedirect()->assertSessionHas('error');

        // Gültiges Profil → installiert + Version getrackt.
        $good = UploadedFile::fake()->createWithContent('good.json', (string) json_encode([
            'code' => 'custom-x',
            'label' => 'Custom X',
            'version' => 2,
            'tags_seed' => ['#custom'],
        ]));
        $this->actingAs($admin)->post(route('admin.branch-profiles.import'), ['file' => $good])
            ->assertRedirect(route('admin.branch-profiles.index'));

        $org = app('currentOrganization');
        $this->assertSame(2, data_get($org->refresh()->settings, 'branch_profile_versions.custom-x'));
    }

    public function test_min_app_version_guard_rejects_new_profiles(): void {
        [$org, $admin] = $this->adminOrg();

        try {
            app(BranchProfileInstaller::class)->installProfile($org, [
                'code' => 'future',
                'label' => 'Zukunft',
                'min_app_version' => '99.0.0',
            ], $admin);
            $this->fail('min_app_version-Guard griff nicht.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('99.0.0', $e->getMessage());
        }
    }

    public function test_reconstruction_returns_state_at_date(): void {
        [$org, $admin] = $this->adminOrg();
        $scope = app(ScopeService::class)->ensureDefaultScope((int) $org->id);

        // T1: Status angelegt (Snapshot via saved-Hook).
        $status = app(ConformityService::class)->create($admin, $scope, ['norm' => 'ISO/IEC 27001', 'edition' => '2022']);
        $t1 = now()->addSecond();

        // Später: Statusübergang (weiterer Snapshot).
        $this->travel(2)->days();
        app(ConformityService::class)->transition($status->fresh(), \App\Enums\Isms\NormConformityStatus::GapAnalysisDone, $admin);

        // Stand zu T1: noch notAssessed.
        $stateT1 = app(AssessmentSnapshotService::class)->stateAt($scope, $t1);
        $this->assertSame('notAssessed', $stateT1['norm_statuses'][0]['status']);

        // Stand heute: gapAnalysisDone.
        $stateNow = app(AssessmentSnapshotService::class)->stateAt($scope, now());
        $this->assertSame('gapAnalysisDone', $stateNow['norm_statuses'][0]['status']);
    }
}
