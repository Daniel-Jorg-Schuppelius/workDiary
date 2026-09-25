<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimPatternTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Claims;

use App\Models\Claims\ClaimCase;
use App\Models\Classification\Classification;
use App\Models\Inventory\StockLot;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Claims\{ClaimCaseService, ClaimPatternDetector};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-886: Serienfehler und Chargenprobleme regelbasiert erkennen. */
class ClaimPatternTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function claim(array $attributes): ClaimCase {
        $case = app(ClaimCaseService::class)->open($this->organization, $this->admin, [
            'title' => 'Reklamation',
            'source' => 'manual',
            'priority' => 'normal',
            'severity' => 'minor',
        ]);
        $case->forceFill($attributes)->save();

        return $case;
    }

    public function test_rules_group_by_lot_and_supplier_defect_above_threshold(): void {
        $lot = StockLot::factory()->create(['organization_id' => $this->organization->id, 'lot_no' => 'CH-4711']);
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ventil AG']);
        $leak = Classification::factory()->create(['organization_id' => $this->organization->id, 'label' => 'Undicht']);
        foreach (range(1, 3) as $i) {
            $this->claim(['stock_lot_id' => $lot->id, 'supplier_id' => $supplier->id, 'defect_type_classification_id' => $leak->id]);
        }
        $this->claim(['supplier_id' => $supplier->id]);
        $this->claim(['stock_lot_id' => StockLot::factory()->create(['organization_id' => $this->organization->id])->id]);

        $patterns = app(ClaimPatternDetector::class)->detect($this->organization->id, CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addMinute(), 3);

        $this->assertEqualsCanonicalizing(
            [['lot', 'CH-4711', 3], ['supplier_defect', 'Ventil AG × Undicht', 3]],
            array_map(static fn (array $p): array => [$p['rule'], $p['label'], $p['count']], $patterns),
        );
        $this->assertSame([], app(ClaimPatternDetector::class)->detect($this->organization->id, CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addMinute(), 4));
    }

    public function test_report_lists_patterns_and_scan_notifies_once(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ventil AG']);
        $leak = Classification::factory()->create(['organization_id' => $this->organization->id, 'label' => 'Undicht']);
        foreach (range(1, 3) as $i) {
            $this->claim(['supplier_id' => $supplier->id, 'defect_type_classification_id' => $leak->id]);
        }

        $this->actingAs($this->admin)
            ->get(route('claims.reports.index', ['from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk()
            ->assertSee(__('claims.pattern.title'))
            ->assertSee('Ventil AG × Undicht');

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, $this->admin->notifications()->count());
        $this->assertSame('claim.pattern', (string) ($this->admin->notifications()->first()?->data['event'] ?? ''));
    }
}
