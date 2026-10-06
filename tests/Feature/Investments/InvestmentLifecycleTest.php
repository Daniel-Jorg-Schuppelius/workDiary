<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Investments;

use App\Enums\Investments\{InvestmentBudgetRequestStatus, InvestmentCaseStatus, InvestmentDeviationStatus};
use App\Enums\User\UserRole;
use App\Models\Investments\InvestmentCase;
use App\Models\Platform\{Organization, User};
use App\Services\Investments\InvestmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 069, MVP-200–207: Investitionsakte — Schwellenwert-Freigabekette
 * (Vier-Augen ab Grenze, Selbstfreigabe-Sperre), eingefrorener
 * Budget-Snapshot, Sperre gegen stille Erhöhung (Nachtrag über genehmigte
 * Abweichung), Ist-Wert-Projektion und Nachbewertung; Rechte-/Tenant-Schutz.
 */
final class InvestmentLifecycleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $second;

    private User $third;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->second = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->third = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function makeCase(): InvestmentCase {
        return InvestmentCase::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Ersatz Servicefahrzeug',
            'category' => 'machine',
            'status' => 'comparison',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_budget_below_threshold_needs_single_approval_and_freezes_snapshot(): void {
        $service = app(InvestmentService::class);
        $case = $this->makeCase();

        $request = $service->submitBudget($case, ['amount' => '5000.00'], $this->admin);
        $this->assertSame(1, $request->approvals()->count(), 'Unter der Schwelle genügt eine Stufe.');
        $this->assertSame(InvestmentCaseStatus::InApproval, $case->fresh()->status);

        // Selbstfreigabe-Sperre: Antragsteller darf nicht freigeben.
        try {
            $service->approveBudget($request, $this->admin);
            $this->fail('Selbstfreigabe wurde akzeptiert.');
        } catch (\RuntimeException) {
        }

        $result = $service->approveBudget($request, $this->second);
        $this->assertSame('approved_all', $result);
        $fresh = $request->fresh();
        $this->assertSame(InvestmentBudgetRequestStatus::Approved, $fresh->status);
        $this->assertSame('5000.00', (string) data_get($fresh->snapshot, 'amount'));
        $this->assertSame(InvestmentCaseStatus::Approved, $case->fresh()->status);
    }

    public function test_budget_at_threshold_requires_four_eyes_chain(): void {
        $service = app(InvestmentService::class);
        $case = $this->makeCase();

        $request = $service->submitBudget($case, ['amount' => '25000.00'], $this->admin);
        $this->assertSame(2, $request->approvals()->count(), 'Ab der Schwelle: Vier-Augen (2 Stufen).');

        $this->assertSame('pending', $service->approveBudget($request, $this->second));

        // Vier Augen heißt zwei Personen: wer Stufe 1 entschieden hat, darf
        // Stufe 2 nicht auch entscheiden (Sicherheitsscan 2026-08-23, S-34).
        try {
            $service->approveBudget($request->fresh(), $this->second);
            $this->fail('Dieselbe Person hat beide Stufen entschieden.');
        } catch (\RuntimeException) {
        }

        $this->assertSame('approved_all', $service->approveBudget($request->fresh(), $this->third));
        $this->assertSame(InvestmentBudgetRequestStatus::Approved, $request->fresh()->status);
    }

    public function test_supplement_requires_approved_budget_deviation_and_supersedes(): void {
        $service = app(InvestmentService::class);
        $case = $this->makeCase();
        $request = $service->submitBudget($case, ['amount' => '5000.00'], $this->admin);
        $service->approveBudget($request, $this->second);

        // Abweichung melden (durch Antragsteller) + entscheiden (zweite Person).
        $deviation = $case->deviations()->create([
            'organization_id' => $this->organization->id,
            'kind' => 'budget',
            'description' => 'Lieferant erhöht Preis',
            'amount_delta' => '1500.00',
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);

        // Nachtrag ohne genehmigte Abweichung → gesperrt (keine stille Erhöhung).
        try {
            $service->supplementBudget($case->refresh(), $deviation, ['amount' => '6500.00'], $this->admin);
            $this->fail('Nachtrag ohne genehmigte Abweichung akzeptiert.');
        } catch (\RuntimeException) {
        }

        // Selbstentscheidung der Abweichung ist gesperrt.
        try {
            $service->decideDeviation($deviation, 'approved', null, $this->admin);
            $this->fail('Selbstfreigabe der Abweichung akzeptiert.');
        } catch (\RuntimeException) {
        }
        $service->decideDeviation($deviation, 'approved', 'ok', $this->second);

        $supplement = $service->supplementBudget($case->refresh(), $deviation->refresh(), ['amount' => '6500.00'], $this->admin);
        $this->assertSame(2, $supplement->version);
        $this->assertSame(InvestmentBudgetRequestStatus::Superseded, $request->fresh()->status, 'Alter genehmigter Stand bleibt als superseded erhalten.');
        $service->approveBudget($supplement, $this->second);
        $this->assertSame('6500.00', (string) $case->refresh()->approvedBudget()?->amount);
    }

    public function test_projection_sums_actuals_and_linked_assets(): void {
        $service = app(InvestmentService::class);
        $case = $this->makeCase();
        $request = $service->submitBudget($case, ['amount' => '9000.00'], $this->admin);
        $service->approveBudget($request, $this->second);

        $case->actuals()->create([
            'organization_id' => $this->organization->id,
            'source' => 'manual',
            'amount' => '2500.00',
            'occurred_on' => now()->toDateString(),
            'created_by' => $this->admin->id,
        ]);
        $asset = \App\Models\Asset\Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'acquisition_cost' => '4000.00',
        ]);
        $case->links()->create([
            'organization_id' => $this->organization->id,
            'linkable_type' => $asset->getMorphClass(),
            'linkable_id' => $asset->id,
            'created_by' => $this->admin->id,
        ]);

        $projection = $service->projection($case->refresh());
        $this->assertSame(9000.0, $projection['approved']);
        $this->assertSame(6500.0, $projection['actual']);
        $this->assertSame(2500.0, $projection['remaining']);
    }

    /**
     * Wertobjekt-Audit 2026-09-19: Die Mittelbindung rechnete (float) auf
     * unit_price (Money) — crashte — und las die nicht existente Spalte
     * quantity statt ordered_qty (war dadurch immer 0).
     */
    public function test_projection_commits_linked_purchase_order_lines(): void {
        $supplier = \App\Models\Supplier\Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $warehouse = \App\Models\Inventory\Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $order = app(\App\Services\Procurement\PurchaseOrderService::class)->createDraft($this->organization, $supplier, $warehouse);
        $article = \App\Models\Article\Article::factory()->create(['organization_id' => $this->organization->id, 'purchasable' => true]);
        $variant = \App\Models\Article\ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'is_default' => true,
            'option_signature' => 'default',
        ]);
        $order->lines()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'article_variant_id' => $variant->id,
            'description' => 'Laptop',
            'ordered_qty' => '3',
            'unit' => 'Stk',
            'unit_price' => '1250.0000',
            'currency' => 'EUR',
        ]);
        $case = $this->makeCase();
        $case->links()->create([
            'organization_id' => $this->organization->id,
            'linkable_type' => $order->getMorphClass(),
            'linkable_id' => $order->id,
            'created_by' => $this->admin->id,
        ]);

        $projection = app(InvestmentService::class)->projection($case->refresh());

        $this->assertSame(3750.0, $projection['committed']);
    }

    public function test_ui_flow_review_and_access_control(): void {
        // Anlage über die UI (Buchhaltung darf führen + freigeben).
        $accounting = $this->userWithRole(UserRole::Buchhaltung->value);
        $this->actingAs($accounting)->post(route('investments.store'), [
            'title' => 'Serverschrank',
            'category' => 'it',
            'urgency' => 'medium',
        ])->assertRedirect();
        $case = InvestmentCase::query()->firstOrFail();

        // Normale Rolle sieht nichts.
        $plain = $this->userWithRole(UserRole::User->value);
        $this->actingAs($plain)->get(route('investments.index'))->assertForbidden();
        $this->actingAs($plain)->get(route('investments.show', $case))->assertForbidden();

        // Nachbewertung erst nach Abschluss; Statuswechsel in Umsetzung
        // verlangt genehmigtes Budget.
        $this->actingAs($accounting)->post(route('investments.status', $case), ['status' => 'in_progress'])
            ->assertSessionHas('error');

        $service = app(InvestmentService::class);
        $request = $service->submitBudget($case->refresh(), ['amount' => '900.00'], $accounting);
        $service->approveBudget($request, $this->second);
        $this->actingAs($accounting)->post(route('investments.status', $case), ['status' => 'in_progress'])->assertSessionHas('success');
        $this->actingAs($accounting)->post(route('investments.status', $case), ['status' => 'completed'])->assertSessionHas('success');

        $this->actingAs($accounting)->post(route('investments.review.store', $case), [
            'benefit_result' => 'Ausfallzeiten halbiert.',
        ])->assertSessionHas('success');
        $this->assertSame(InvestmentCaseStatus::PostReview, $case->fresh()->status);

        // Fremde Org: 404.
        $otherOrg = Organization::factory()->create();
        $foreign = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        app()->instance('currentOrganization', $otherOrg);
        $this->actingAs($foreign)->get(route('investments.show', $case))->assertNotFound();
    }

    // ── Zurückstellen, Wieder aufnehmen, Ablehnung endgültig (Konsolidierungs-Audit 2026-10, vierte Runde) ──

    private function showPage(InvestmentCase $case): string {
        return (string) $this->actingAs($this->admin)->get(route('investments.show', $case))->assertOk()->getContent();
    }

    public function test_deferring_remembers_the_phase_and_resuming_leads_back_to_it(): void {
        $case = $this->makeCase();
        $html = $this->showPage($case);
        $this->assertStringContainsString('<input type="hidden" name="status" value="deferred">', $html);
        $this->assertStringContainsString(e(__('Zurückstellen')), $html);
        $this->assertStringNotContainsString('<option value="deferred"', $html);
        $this->assertStringNotContainsString(e(__('Wieder aufnehmen')), $html);

        $this->actingAs($this->admin)->post(route('investments.status', $case), ['status' => 'deferred'])
            ->assertSessionHas('success', (string) __('Akte zurückgestellt.'));
        $case->refresh();
        $this->assertSame(InvestmentCaseStatus::Deferred, $case->status);
        $this->assertSame(InvestmentCaseStatus::Comparison, $case->deferred_from_status);

        // Zurückgestellt: statt des Selects der Knopf „Wieder aufnehmen“ mit der gemerkten Phase.
        $html = $this->showPage($case);
        $this->assertStringContainsString('<input type="hidden" name="status" value="comparison">', $html);
        $this->assertStringContainsString(e(__('Wieder aufnehmen')), $html);
        $this->assertStringNotContainsString('<select name="status"', $html);
        $this->assertStringNotContainsString('name="status" value="deferred"', $html);

        // Nur die gemerkte Phase führt zurück.
        $this->actingAs($this->admin)->post(route('investments.status', $case), ['status' => 'idea'])->assertSessionHas('error');
        $this->assertSame(InvestmentCaseStatus::Deferred, $case->fresh()->status);
        $this->actingAs($this->admin)->post(route('investments.status', $case), ['status' => 'comparison'])
            ->assertSessionHas('success', (string) __('Akte wieder aufgenommen.'));
        $case->refresh();
        $this->assertSame(InvestmentCaseStatus::Comparison, $case->status);
        $this->assertNull($case->deferred_from_status);
    }

    public function test_legacy_deferred_case_without_phase_resumes_into_the_idea(): void {
        $case = InvestmentCase::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Altbestand ohne Phase',
            'category' => 'it',
            'status' => 'deferred',
            'created_by' => $this->admin->id,
        ]);
        $this->assertNull($case->fresh()->deferred_from_status);
        $this->assertStringContainsString('<input type="hidden" name="status" value="idea">', $this->showPage($case));

        $this->actingAs($this->admin)->post(route('investments.status', $case), ['status' => 'comparison'])->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('investments.status', $case), ['status' => 'idea'])->assertSessionHas('success');
        $this->assertSame(InvestmentCaseStatus::Idea, $case->fresh()->status);
    }

    public function test_defer_button_appears_only_in_planning(): void {
        foreach (InvestmentCaseStatus::cases() as $status) {
            $case = InvestmentCase::query()->create([
                'organization_id' => $this->organization->id,
                'title' => 'Akte ' . $status->value,
                'category' => 'it',
                'status' => $status,
                'created_by' => $this->admin->id,
            ]);
            $html = $this->showPage($case);
            if ($status->isPlanning()) {
                $this->assertStringContainsString('name="status" value="deferred"', $html, $status->value);
            } else {
                $this->assertStringNotContainsString('name="status" value="deferred"', $html, $status->value);
            }
            if ($status !== InvestmentCaseStatus::Deferred) {
                $this->assertStringNotContainsString(e(__('Wieder aufnehmen')), $html, $status->value);
            }
        }
    }

    public function test_a_rejected_case_stays_rejected(): void {
        $case = InvestmentCase::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Abgelehnter Kran',
            'category' => 'machine',
            'status' => 'rejected',
            'created_by' => $this->admin->id,
        ]);
        $entries = $case->auditLogs()->count();

        // Statusform: Handpflege-Ziele mit Meldung, Freigabe-/Endstände schon in der Validierung.
        foreach (InvestmentCaseStatus::manual() as $target) {
            $this->actingAs($this->admin)->post(route('investments.status', $case), ['status' => $target->value])
                ->assertSessionHas('error', (string) __('Statuswechsel von :from nach :to ist nicht zulässig.', ['from' => InvestmentCaseStatus::Rejected->label(), 'to' => $target->label()]))
                ->assertSessionMissing('success');
        }
        foreach (['in_approval', 'approved', 'cancelled', 'post_review', 'rejected'] as $target) {
            $this->actingAs($this->admin)->post(route('investments.status', $case), ['status' => $target])->assertSessionHasErrors('status');
        }

        // Kein neuer Budgetantrag, kein Zurückstellen, kein Wiederaufnehmen.
        $this->actingAs($this->admin)->post(route('investments.budget.submit', $case), ['amount' => '1000.00', 'cost_kind' => 'purchase', 'financing' => 'cash'])
            ->assertSessionHas('error', (string) __('Budgetanträge sind nur in der Planungsphase möglich.'));
        $this->assertSame(0, $case->budgetRequests()->count());
        $service = app(InvestmentService::class);
        try {
            $service->defer($case->fresh());
            $this->fail('Eine abgelehnte Akte wurde zurückgestellt.');
        } catch (\RuntimeException $e) {
            $this->assertSame((string) __('Zurückstellen ist nur in der Planungsphase möglich.'), $e->getMessage());
        }
        try {
            $service->resume($case->fresh());
            $this->fail('Eine abgelehnte Akte wurde wieder aufgenommen.');
        } catch (\RuntimeException $e) {
            $this->assertSame((string) __('Nur eine zurückgestellte Akte lässt sich wieder aufnehmen.'), $e->getMessage());
        }

        // Auch der Abbruch über eine genehmigte Abweichung führt nicht aus „abgelehnt“ heraus.
        $deviation = $case->deviations()->create([
            'organization_id' => $this->organization->id,
            'kind' => 'cancellation',
            'description' => 'Doch nicht nötig',
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);
        $this->actingAs($this->second)->post(route('investments.deviations.decide', [$case, $deviation]), ['decision' => 'approved'])
            ->assertSessionHas('error', (string) __('Eine abgelehnte Akte bleibt abgelehnt.'));
        $this->assertSame(InvestmentDeviationStatus::Open, $deviation->fresh()->status);

        $this->assertSame(InvestmentCaseStatus::Rejected, $case->fresh()->status);
        $this->assertSame($entries, $case->auditLogs()->count(), 'Keine Änderung, kein Protokolleintrag.');
        $this->assertStringNotContainsString('name="status"', $this->showPage($case));
    }

    public function test_deviation_decision_starts_empty_and_requires_a_choice(): void {
        $service = app(InvestmentService::class);
        $case = $this->makeCase();
        $service->approveBudget($service->submitBudget($case, ['amount' => '5000.00'], $this->admin), $this->second);
        $deviation = $case->deviations()->create([
            'organization_id' => $this->organization->id,
            'kind' => 'budget',
            'description' => 'Lieferant erhöht Preis',
            'amount_delta' => '500.00',
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);

        $html = (string) $this->actingAs($this->second)->get(route('investments.show', $case))->assertOk()->getContent();
        $this->assertStringContainsString('<option value="" selected disabled>' . e(__('Bitte wählen')) . '</option>', $html);
        $this->assertStringNotContainsString('<option value="approved" selected', $html);

        $this->actingAs($this->second)->post(route('investments.deviations.decide', [$case, $deviation]), ['note' => 'ohne Wahl'])
            ->assertSessionHasErrors('decision');
        $this->assertSame(InvestmentDeviationStatus::Open, $deviation->fresh()->status);
    }

    public function test_report_renders_and_exports(): void {
        $service = app(InvestmentService::class);
        $case = $this->makeCase();
        $request = $service->submitBudget($case, ['amount' => '5000.00'], $this->admin);
        $service->approveBudget($request, $this->second);

        $this->actingAs($this->admin)->get(route('investments.report'))
            ->assertOk()
            ->assertSee('Ersatz Servicefahrzeug');
        $csv = $this->actingAs($this->admin)->get(route('investments.report', ['export' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('Ersatz Servicefahrzeug', (string) $csv->getContent());
    }
}
