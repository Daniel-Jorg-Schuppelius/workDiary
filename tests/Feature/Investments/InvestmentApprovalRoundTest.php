<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentApprovalRoundTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Investments;

use App\Enums\Investments\InvestmentBudgetRequestStatus;
use App\Models\Investments\{InvestmentBudgetRequest, InvestmentCase};
use App\Models\Platform\User;
use App\Services\Approval\ApprovalService;
use App\Services\Investments\InvestmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Budgetfreigabe und -ablehnung treffen die geltende Freigaberunde — die
 * offenen Stufen einer abgelösten Runde bleiben unberührt.
 */
final class InvestmentApprovalRoundTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $requester;

    private User $second;

    private User $third;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->requester = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->second = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->third = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** Zweistufiger Antrag (über der Schwelle), dessen Kette neu gestartet wurde, bevor jemand entschieden hat. */
    private function restartedRequest(): InvestmentBudgetRequest {
        $case = InvestmentCase::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Ersatz Servicefahrzeug',
            'category' => 'machine',
            'status' => 'comparison',
            'created_by' => $this->requester->id,
        ]);
        $request = app(InvestmentService::class)->submitBudget($case, ['amount' => '25000.00'], $this->requester);
        $this->assertSame(2, app(ApprovalService::class)->startNextRound($request));

        return $request;
    }

    public function test_approval_decides_the_current_round(): void {
        $service = app(InvestmentService::class);
        $request = $this->restartedRequest();

        $this->assertSame('pending', $service->approveBudget($request, $this->second));
        $this->assertSame('approved_all', $service->approveBudget($request->fresh(), $this->third));

        $this->assertSame(InvestmentBudgetRequestStatus::Approved, $request->fresh()->status);
        $this->assertSame(2, $request->approvals()->where('round', 2)->where('decision', 'approved')->count());
        $this->assertSame(0, $request->approvals()->where('round', 1)->whereNotNull('decision')->count());
    }

    public function test_rejection_decides_the_current_round(): void {
        $request = $this->restartedRequest();

        app(InvestmentService::class)->rejectBudget($request, $this->second, 'Zu teuer');

        $this->assertSame(InvestmentBudgetRequestStatus::Rejected, $request->fresh()->status);
        $this->assertSame('rejected', $request->approvals()->where('round', 2)->where('step', 1)->value('decision'));
        $this->assertSame(0, $request->approvals()->where('round', 1)->whereNotNull('decision')->count());
    }
}
