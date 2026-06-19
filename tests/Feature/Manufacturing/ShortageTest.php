<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShortageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Enums\Manufacturing\{ProcurementStatus, SubstituteStatus};
use App\Models\{Article, ManufacturingOrder, ManufacturingOrderMaterial, MaterialSubstitute, Organization, ProcurementRequest};
use App\Services\Manufacturing\ShortageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Fehlmaterialprozess (Feature 048): Ersatzmaterial als auditierte Abweichung
 * (ohne Stücklisten-Mutation) und Beschaffungsbedarf als offener Punkt.
 */
final class ShortageTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private ShortageService $shortage;
    private Article $planned;
    private Article $substitute;
    private ManufacturingOrderMaterial $material;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->shortage = app(ShortageService::class);
        $this->planned = Article::factory()->create(['organization_id' => $this->organization->id]);
        $this->substitute = Article::factory()->create(['organization_id' => $this->organization->id]);

        $order = ManufacturingOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->planned->id,
        ]);
        $this->material = $order->materials()->create([
            'article_id' => $this->planned->id,
            'name_snapshot' => 'Sollmaterial',
            'target_qty' => '10',
            'unit_snapshot' => 'Stk',
        ]);
    }

    public function test_request_and_approve_substitute_without_changing_bom(): void {
        $sub = $this->shortage->requestSubstitute($this->material, $this->substitute, null, '10', 'Lieferengpass');

        $this->assertSame(SubstituteStatus::Requested, $sub->status);
        $this->assertSame($this->planned->id, (int) $sub->planned_article_id);
        $this->assertSame($this->substitute->id, (int) $sub->substitute_article_id);
        $this->assertSame('Lieferengpass', $sub->reason);

        $approver = \App\Models\User::factory()->create(['organization_id' => $this->organization->id]);
        $this->shortage->approveSubstitute($sub, $approver->id);
        $this->assertSame(SubstituteStatus::Approved, $sub->fresh()->status);
        $this->assertSame($approver->id, (int) $sub->fresh()->approved_by);

        // Stückliste bleibt unverändert.
        $this->assertSame('10.0000', $this->material->fresh()->target_qty);
    }

    public function test_reject_substitute(): void {
        $sub = $this->shortage->requestSubstitute($this->material, $this->substitute, null, '3', 'Qualität');
        $this->shortage->rejectSubstitute($sub);

        $this->assertSame(SubstituteStatus::Rejected, $sub->fresh()->status);
    }

    public function test_double_decision_throws(): void {
        $sub = $this->shortage->requestSubstitute($this->material, $this->substitute, null, '1', 'x');
        $this->shortage->approveSubstitute($sub);

        $this->expectException(RuntimeException::class);
        $this->shortage->approveSubstitute($sub->fresh());
    }

    public function test_procurement_request_is_open(): void {
        $req = $this->shortage->createProcurementRequest($this->planned, null, '5');

        $this->assertSame(ProcurementStatus::Open, $req->status);
        $this->assertSame('5.0000', $req->quantity);
    }

    public function test_records_are_isolated_per_organization(): void {
        $this->shortage->requestSubstitute($this->material, $this->substitute, null, '1', 'x');
        $this->shortage->createProcurementRequest($this->planned, null, '1');
        $this->assertSame(1, MaterialSubstitute::query()->count());
        $this->assertSame(1, ProcurementRequest::query()->count());

        $orgB = Organization::factory()->create();
        app()->instance('currentOrganization', $orgB);
        $this->assertSame(0, MaterialSubstitute::query()->count());
        $this->assertSame(0, ProcurementRequest::query()->count());
    }
}
