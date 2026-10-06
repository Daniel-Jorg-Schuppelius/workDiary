<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentStatusEnumCastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Investments;

use App\Enums\Investments\{InvestmentBudgetRequestStatus, InvestmentCaseStatus, InvestmentDeviationStatus};
use App\Models\Asset\Asset;
use App\Models\Investments\{InvestmentBudgetRequest, InvestmentCase, InvestmentDeviation, InvestmentProgram, StrategicObjective};
use App\Models\Platform\User;
use App\Services\Demo\Contracts\DemoSeedContext;
use App\Services\Investments\Demo\InvestmentsDemoBlock;
use App\Services\Investments\{InvestmentProgramService, InvestmentService, StrategicObjectiveService};
use App\Services\Investments\Liquidity\PlannedInvestmentSource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k3-10 (Welle 6): Investitionsakte,
 * Budgetantrag und Abweichung führen ihren Status als Enum. Ein Vergleich
 * gegen die frühere Zeichenkette ist danach still falsch — die Akte bliebe
 * in der Idee stehen, die Umsetzung startete nie, jede Zeile wäre grau.
 */
final class InvestmentStatusEnumCastTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $second;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-10-05 12:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->second = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function investment(InvestmentCaseStatus $status, array $attributes = []): InvestmentCase {
        return InvestmentCase::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'title' => 'Ersatz Servicefahrzeug',
            'category' => 'machine',
            'urgency' => 'medium',
            'status' => $status,
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    private function budget(InvestmentCase $case, InvestmentBudgetRequestStatus $status, string $amount = '5000.00'): InvestmentBudgetRequest {
        return InvestmentBudgetRequest::query()->create([
            'organization_id' => $this->organization->id,
            'investment_case_id' => $case->id,
            'version' => (int) $case->budgetRequests()->max('version') + 1,
            'amount' => $amount,
            'cost_kind' => 'purchase',
            'financing' => 'cash',
            'status' => $status,
            'requested_by' => $this->admin->id,
        ]);
    }

    private function deviation(InvestmentCase $case, InvestmentDeviationStatus $status, string $kind = 'budget'): InvestmentDeviation {
        return $case->deviations()->create([
            'organization_id' => $this->organization->id,
            'kind' => $kind,
            'description' => 'Lieferant erhöht den Preis',
            'amount_delta' => '1500.00',
            'status' => $status,
            'created_by' => $this->admin->id,
        ]);
    }

    private function page(string $url): string {
        return (string) $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
    }

    /** Eigene Meldung statt `assertSee`: Dessen Fehlertext trägt die ganze Seite. */
    private function assertPageHas(string $needle, string $html): void {
        $this->assertTrue(str_contains($html, $needle), "Auf der Seite fehlt: {$needle}");
    }

    private function assertPageLacks(string $needle, string $html): void {
        $this->assertFalse(str_contains($html, $needle), "Auf der Seite steht unerwartet: {$needle}");
    }

    private function assertBadge(string $tone, string $label, string $html): void {
        $this->assertSame(1, preg_match('/badge-' . $tone . '[^>]*>\s*' . preg_quote(e($label), '/') . '\s*</u', $html), "Kein Abzeichen „{$label}“ im Ton {$tone}.");
    }

    // ── Akte ─────────────────────────────────────────────────────────────

    public function test_case_file_offers_the_actions_that_fit_the_status(): void {
        $planning = $this->investment(InvestmentCaseStatus::Comparison);
        $html = $this->page(route('investments.show', $planning));
        $this->assertPageHas('<option value="comparison" selected>' . e(InvestmentCaseStatus::Comparison->label()) . '</option>', $html);
        $this->assertPageLacks('<option value="idea" selected>', $html);
        $this->assertPageHas('action="' . route('investments.options.store', $planning) . '"', $html);
        $this->assertPageHas('action="' . route('investments.budget.submit', $planning) . '"', $html);
        // In der Planung gibt es nur den Knopf „Zurückstellen“, keinen Sprung in Umsetzung oder Abschluss.
        $this->assertPageHas('<input type="hidden" name="status" value="deferred">', $html);
        $this->assertPageLacks('<input type="hidden" name="status" value="in_progress">', $html);
        $this->assertPageLacks('<input type="hidden" name="status" value="completed">', $html);
        $this->assertPageLacks('action="' . route('investments.review.store', $planning) . '"', $html);

        $approved = $this->investment(InvestmentCaseStatus::Approved);
        $html = $this->page(route('investments.show', $approved));
        $this->assertPageHas('<input type="hidden" name="status" value="in_progress">', $html);
        $this->assertPageHas(e(__('Umsetzung starten')), $html);
        $this->assertPageLacks('action="' . route('investments.options.store', $approved) . '"', $html);
        $this->assertPageLacks('action="' . route('investments.budget.submit', $approved) . '"', $html);

        $running = $this->investment(InvestmentCaseStatus::InProgress);
        $html = $this->page(route('investments.show', $running));
        $this->assertPageHas('<input type="hidden" name="status" value="completed">', $html);
        $this->assertPageLacks(e(__('Umsetzung starten')), $html);

        foreach ([InvestmentCaseStatus::Completed, InvestmentCaseStatus::Cancelled] as $finished) {
            $case = $this->investment($finished);
            $html = $this->page(route('investments.show', $case));
            $this->assertPageHas('action="' . route('investments.review.store', $case) . '"', $html);
            $this->assertPageLacks('<input type="hidden" name="status"', $html);
        }
    }

    public function test_list_colours_the_badge_and_filters_by_status(): void {
        $this->investment(InvestmentCaseStatus::Approved, ['title' => 'Genehmigte Presse']);
        $this->investment(InvestmentCaseStatus::Rejected, ['title' => 'Abgelehnter Kran']);

        $html = $this->page(route('investments.index'));
        $this->assertBadge('success', InvestmentCaseStatus::Approved->label(), $html);
        $this->assertBadge('error', InvestmentCaseStatus::Rejected->label(), $html);
        foreach (InvestmentCaseStatus::cases() as $status) {
            $this->assertPageHas('<option value="' . $status->value . '"', $html);
        }

        $response = $this->actingAs($this->admin)->get(route('investments.index', ['status' => 'rejected']))->assertOk()
            ->assertViewHas('cases', fn ($cases): bool => $cases->pluck('title')->all() === ['Abgelehnter Kran']);
        $this->assertPageHas('<option value="rejected" selected>' . e(InvestmentCaseStatus::Rejected->label()) . '</option>', (string) $response->getContent());

        // Unbekannter Filterwert: ungefiltert.
        $this->actingAs($this->admin)->get(route('investments.index', ['status' => 'unbekannt']))->assertOk()
            ->assertViewHas('cases', fn ($cases): bool => $cases->total() === 2)
            ->assertViewHas('filters', ['status' => '', 'category' => '']);
    }

    public function test_every_status_keeps_its_badge_tone(): void {
        $tones = [];
        foreach (InvestmentCaseStatus::cases() as $status) {
            $tones[$status->value] = $status->tone();
        }

        $this->assertSame([
            'idea' => 'ghost', 'screening' => 'ghost', 'comparison' => 'ghost', 'budget_request' => 'warning', 'in_approval' => 'warning',
            'approved' => 'success', 'rejected' => 'error', 'deferred' => 'neutral', 'in_progress' => 'primary', 'completed' => 'success',
            'cancelled' => 'neutral', 'post_review' => 'info',
        ], $tones);
    }

    public function test_first_option_moves_an_early_case_into_the_comparison(): void {
        foreach ([[InvestmentCaseStatus::Idea, InvestmentCaseStatus::Comparison], [InvestmentCaseStatus::Screening, InvestmentCaseStatus::Comparison], [InvestmentCaseStatus::BudgetRequest, InvestmentCaseStatus::BudgetRequest]] as [$from, $expected]) {
            $case = $this->investment($from);

            $this->actingAs($this->admin)->post(route('investments.options.store', $case), ['title' => 'Angebot A', 'one_time_cost' => '1000'])
                ->assertSessionHas('success');

            $this->assertSame($expected, $case->fresh()->status, $from->value);
        }
    }

    public function test_first_link_starts_the_implementation_of_an_approved_case(): void {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        foreach ([[InvestmentCaseStatus::Approved, InvestmentCaseStatus::InProgress], [InvestmentCaseStatus::Completed, InvestmentCaseStatus::Completed]] as [$from, $expected]) {
            $case = $this->investment($from);
            $this->budget($case, InvestmentBudgetRequestStatus::Approved);

            $this->actingAs($this->admin)->post(route('investments.links.store', $case), ['linkable_type' => 'asset', 'linkable_sqid' => $asset->sqid])
                ->assertSessionHas('success');

            $this->assertSame($expected, $case->fresh()->status, $from->value);
        }
    }

    public function test_manual_status_accepts_exactly_the_manual_stages(): void {
        $case = $this->investment(InvestmentCaseStatus::Idea);

        foreach (['in_approval', 'approved', 'rejected', 'cancelled', 'post_review', 'unbekannt', ''] as $rejected) {
            $this->actingAs($this->admin)->post(route('investments.status', $case), ['status' => $rejected])->assertSessionHasErrors('status');
        }
        $this->assertSame(InvestmentCaseStatus::Idea, $case->fresh()->status);
        $this->assertSame(['idea', 'screening', 'comparison', 'budget_request', 'in_progress', 'completed', 'deferred'], array_column(InvestmentCaseStatus::manual(), 'value'));

        // Die Planungsphasen lassen sich untereinander setzen, vor und zurück.
        foreach ([InvestmentCaseStatus::Screening, InvestmentCaseStatus::Comparison, InvestmentCaseStatus::BudgetRequest, InvestmentCaseStatus::Idea] as $stage) {
            $this->actingAs($this->admin)->post(route('investments.status', $case), ['status' => $stage->value])->assertSessionHas('success');
            $this->assertSame($stage, $case->fresh()->status);
        }
        // Derselbe Stand noch einmal: keine Meldung, kein weiterer Protokolleintrag.
        $entries = $case->auditLogs()->count();
        $this->actingAs($this->admin)->post(route('investments.status', $case), ['status' => 'idea'])->assertSessionHas('success')->assertSessionMissing('error');
        $this->assertSame($entries, $case->auditLogs()->count());

        // Nach der Freigabe bleibt der Weg der beiden Knöpfe: Umsetzung starten, abschließen.
        $approved = $this->investment(InvestmentCaseStatus::Approved);
        $this->budget($approved, InvestmentBudgetRequestStatus::Approved);
        foreach ([InvestmentCaseStatus::InProgress, InvestmentCaseStatus::Completed] as $stage) {
            $this->actingAs($this->admin)->post(route('investments.status', $approved), ['status' => $stage->value])->assertSessionHas('success');
            $this->assertSame($stage, $approved->fresh()->status);
        }

        // Umsetzung und Abschluss setzen ein genehmigtes Budget voraus.
        foreach ([[InvestmentCaseStatus::Approved, 'in_progress'], [InvestmentCaseStatus::InProgress, 'completed']] as [$from, $blocked]) {
            $unfunded = $this->investment($from);
            $this->actingAs($this->admin)->post(route('investments.status', $unfunded), ['status' => $blocked])
                ->assertSessionHas('error', (string) __('Umsetzung erst nach genehmigtem Budget.'));
            $this->assertSame($from, $unfunded->fresh()->status);
        }
    }

    public function test_manual_targets_follow_what_the_case_file_offers(): void {
        $table = [];
        foreach (InvestmentCaseStatus::cases() as $status) {
            $table[$status->value] = array_column($status->manualTargets(), 'value');
        }

        $this->assertSame([
            'idea' => ['screening', 'comparison', 'budget_request', 'deferred'],
            'screening' => ['idea', 'comparison', 'budget_request', 'deferred'],
            'comparison' => ['idea', 'screening', 'budget_request', 'deferred'],
            'budget_request' => ['idea', 'screening', 'comparison', 'deferred'],
            'in_approval' => [],
            'approved' => ['in_progress'],
            'rejected' => [],
            'deferred' => ['idea'],
            'in_progress' => ['completed'],
            'completed' => [],
            'cancelled' => [],
            'post_review' => [],
        ], $table);

        // Zurückgestellt führt nur in die gemerkte Phase zurück; ohne Phase (Altbestand) in die Idee.
        $this->assertSame([InvestmentCaseStatus::Comparison], InvestmentCaseStatus::Deferred->manualTargets(InvestmentCaseStatus::Comparison));
        $this->assertSame(InvestmentCaseStatus::Idea->manualTargets(), InvestmentCaseStatus::Idea->manualTargets(InvestmentCaseStatus::Screening), 'Außerhalb von „zurückgestellt“ spielt die Rücksprungphase keine Rolle.');
    }

    public function test_manual_status_is_refused_outside_the_offered_steps(): void {
        foreach (InvestmentCaseStatus::cases() as $from) {
            if ($from->isPlanning() || $from === InvestmentCaseStatus::Deferred) {
                continue;
            }
            $case = $this->investment($from);
            $this->budget($case, InvestmentBudgetRequestStatus::Approved);
            $entries = $case->auditLogs()->count();

            foreach (InvestmentCaseStatus::manual() as $target) {
                if ($target === $from || in_array($target, $from->manualTargets(), true)) {
                    continue;
                }
                $this->actingAs($this->admin)->post(route('investments.status', $case), ['status' => $target->value])
                    ->assertSessionHas('error', (string) __('Statuswechsel von :from nach :to ist nicht zulässig.', ['from' => $from->label(), 'to' => $target->label()]))
                    ->assertSessionMissing('success');
            }

            $this->assertSame($from, $case->fresh()->status, $from->value);
            $this->assertSame($entries, $case->auditLogs()->count(), $from->value);
        }

        // Aus der Planung führt kein Sprung an der Freigabe vorbei.
        $planning = $this->investment(InvestmentCaseStatus::BudgetRequest);
        $this->budget($planning, InvestmentBudgetRequestStatus::Approved);
        foreach (['in_progress', 'completed'] as $skipped) {
            $this->actingAs($this->admin)->post(route('investments.status', $planning), ['status' => $skipped])->assertSessionHas('error');
        }
        $this->assertSame(InvestmentCaseStatus::BudgetRequest, $planning->fresh()->status);
    }

    public function test_deferred_stays_reachable_from_planning_and_leads_back(): void {
        // Das Select bietet „zurückgestellt“ nicht an — der Knopf schickt es; zurück geht es nur in die gemerkte Phase.
        $case = $this->investment(InvestmentCaseStatus::Screening);
        $this->assertPageLacks('<option value="deferred"', $this->page(route('investments.show', $case)));

        $this->actingAs($this->admin)->post(route('investments.status', $case), ['status' => 'deferred'])->assertSessionHas('success');
        $this->assertSame(InvestmentCaseStatus::Deferred, $case->fresh()->status);
        $this->actingAs($this->admin)->post(route('investments.status', $case), ['status' => 'completed'])->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('investments.status', $case), ['status' => 'idea'])->assertSessionHas('error');
        $this->assertSame(InvestmentCaseStatus::Deferred, $case->fresh()->status);
        $this->actingAs($this->admin)->post(route('investments.status', $case), ['status' => 'screening'])->assertSessionHas('success');
        $this->assertSame(InvestmentCaseStatus::Screening, $case->fresh()->status);
    }

    public function test_review_needs_a_finished_case(): void {
        $running = $this->investment(InvestmentCaseStatus::InProgress);
        $this->actingAs($this->admin)->post(route('investments.review.store', $running), ['benefit_result' => 'Ausfallzeiten halbiert.'])->assertSessionHas('error');
        $this->assertSame(InvestmentCaseStatus::InProgress, $running->fresh()->status);

        foreach ([InvestmentCaseStatus::Completed, InvestmentCaseStatus::Cancelled] as $finished) {
            $case = $this->investment($finished);
            $this->actingAs($this->admin)->post(route('investments.review.store', $case), ['benefit_result' => 'Ausfallzeiten halbiert.'])->assertSessionHas('success');
            $this->assertSame(InvestmentCaseStatus::PostReview, $case->fresh()->status);
        }
    }

    public function test_supplier_rating_opens_with_the_implementation(): void {
        foreach (InvestmentCaseStatus::cases() as $status) {
            $rateable = in_array($status, [InvestmentCaseStatus::InProgress, InvestmentCaseStatus::Completed, InvestmentCaseStatus::PostReview], true);

            $this->actingAs($this->admin)->get(route('investments.show', $this->investment($status)))->assertOk()
                ->assertViewHas('canRateSuppliers', $rateable);
        }
    }

    // ── Budgetantrag und Abweichung ──────────────────────────────────────

    public function test_budget_requests_and_deviations_offer_the_action_that_fits_their_status(): void {
        $case = $this->investment(InvestmentCaseStatus::InApproval);
        $superseded = $this->budget($case, InvestmentBudgetRequestStatus::Superseded);
        $pending = $this->budget($case, InvestmentBudgetRequestStatus::InApproval, '6500.00');
        $open = $this->deviation($case, InvestmentDeviationStatus::Open);
        $approved = $this->deviation($case, InvestmentDeviationStatus::Approved);
        $rejected = $this->deviation($case, InvestmentDeviationStatus::Rejected);
        $scope = $this->deviation($case, InvestmentDeviationStatus::Approved, 'scope');

        $html = $this->page(route('investments.show', $case));

        $this->assertPageHas('action="' . route('investments.budget.approve', [$case, $pending]) . '"', $html);
        $this->assertPageHas('action="' . route('investments.budget.reject', [$case, $pending]) . '"', $html);
        $this->assertPageLacks('action="' . route('investments.budget.approve', [$case, $superseded]) . '"', $html);
        $this->assertBadge('outline', InvestmentBudgetRequestStatus::Superseded->label(), $html);
        $this->assertBadge('outline', InvestmentBudgetRequestStatus::InApproval->label(), $html);

        $this->assertPageHas('action="' . route('investments.deviations.decide', [$case, $open]) . '"', $html);
        $this->assertPageLacks('action="' . route('investments.deviations.decide', [$case, $approved]) . '"', $html);
        $this->assertPageLacks('action="' . route('investments.deviations.decide', [$case, $rejected]) . '"', $html);
        // Den Nachtrag gibt es nur zur genehmigten Budget-Abweichung.
        $this->assertPageHas('action="' . route('investments.budget.supplement', [$case, $approved]) . '"', $html);
        foreach ([$open, $rejected, $scope] as $deviation) {
            $this->assertPageLacks('action="' . route('investments.budget.supplement', [$case, $deviation]) . '"', $html);
        }
        $this->assertBadge('warning', InvestmentDeviationStatus::Open->label(), $html);
        $this->assertBadge('success', InvestmentDeviationStatus::Approved->label(), $html);
        $this->assertBadge('error', InvestmentDeviationStatus::Rejected->label(), $html);
    }

    public function test_budget_is_decided_only_while_in_approval(): void {
        $service = app(InvestmentService::class);
        $case = $this->investment(InvestmentCaseStatus::Comparison);
        $request = $service->submitBudget($case, ['amount' => '5000.00'], $this->admin);
        $this->assertSame(InvestmentBudgetRequestStatus::InApproval, $request->fresh()->status);

        // Solange ein Antrag offen ist, gibt es keinen zweiten.
        try {
            $service->submitBudget($case->fresh()->fill(['status' => InvestmentCaseStatus::Comparison]), ['amount' => '1.00'], $this->admin);
            $this->fail('Zweiter offener Antrag akzeptiert.');
        } catch (\RuntimeException $e) {
            $this->assertSame((string) __('Es ist bereits ein Budgetantrag offen.'), $e->getMessage());
        }

        $service->rejectBudget($request->fresh(), $this->second, 'zu teuer');
        $this->assertSame(InvestmentBudgetRequestStatus::Rejected, $request->fresh()->status);
        $this->assertSame(InvestmentCaseStatus::Rejected, $case->fresh()->status);
        foreach ([fn () => $service->approveBudget($request->fresh(), $this->second), fn () => $service->rejectBudget($request->fresh(), $this->second, 'nochmal')] as $again) {
            try {
                $again();
                $this->fail('Entschiedener Antrag erneut entschieden.');
            } catch (\RuntimeException $e) {
                $this->assertSame((string) __('Der Antrag ist nicht in Freigabe.'), $e->getMessage());
            }
        }

        // Budgetanträge gibt es nur in der Planungsphase.
        $this->expectExceptionMessage((string) __('Budgetanträge sind nur in der Planungsphase möglich.'));
        $service->submitBudget($case->fresh(), ['amount' => '5000.00'], $this->admin);
    }

    public function test_deviation_is_decided_once_and_only_with_a_decision(): void {
        $service = app(InvestmentService::class);
        $case = $this->investment(InvestmentCaseStatus::InProgress);
        $deviation = $this->deviation($case, InvestmentDeviationStatus::Open, 'cancellation');

        $this->actingAs($this->second)->post(route('investments.deviations.decide', [$case, $deviation]), ['decision' => 'open'])->assertSessionHasErrors('decision');
        foreach (['open', 'unbekannt'] as $invalid) {
            try {
                $service->decideDeviation($deviation, $invalid, null, $this->second);
                $this->fail("Entscheidung „{$invalid}“ akzeptiert.");
            } catch (\RuntimeException $e) {
                $this->assertSame((string) __('Ungültige Entscheidung.'), $e->getMessage());
            }
        }
        $this->assertSame(InvestmentDeviationStatus::Open, $deviation->fresh()->status);

        $this->actingAs($this->second)->post(route('investments.deviations.decide', [$case, $deviation]), ['decision' => 'approved'])->assertSessionHas('success');
        $this->assertSame(InvestmentDeviationStatus::Approved, $deviation->fresh()->status);
        // Der genehmigte Abbruch beendet die Akte.
        $this->assertSame(InvestmentCaseStatus::Cancelled, $case->fresh()->status);

        $this->expectExceptionMessage((string) __('Die Abweichung ist bereits entschieden.'));
        $service->decideDeviation($deviation->fresh(), 'rejected', null, $this->second);
    }

    /** Der Demo-Block fängt jeden Fehler ab und meldet dann nur „0 angelegt“. */
    public function test_demo_block_seeds_a_case_with_a_budget_request_in_approval(): void {
        $context = new DemoSeedContext($this->organization, $this->admin, collect([$this->admin]), [], null, null, null);

        $this->assertSame(['investments' => 1], (new InvestmentsDemoBlock)->seed($context));

        $case = InvestmentCase::query()->sole();
        $this->assertSame(InvestmentCaseStatus::InApproval, $case->status);
        $this->assertSame(InvestmentBudgetRequestStatus::InApproval, $case->budgetRequests()->sole()->status);
    }

    // ── Bericht, Portfolios, Liquidität ──────────────────────────────────

    public function test_report_groups_by_the_stored_value_and_exports_it(): void {
        $approved = $this->investment(InvestmentCaseStatus::Approved);
        $this->budget($approved, InvestmentBudgetRequestStatus::Approved);
        $this->budget($this->investment(InvestmentCaseStatus::InApproval, ['title' => 'Kran']), InvestmentBudgetRequestStatus::InApproval);
        $this->deviation($approved, InvestmentDeviationStatus::Open);
        $this->deviation($approved, InvestmentDeviationStatus::Rejected);

        $response = $this->actingAs($this->admin)->get(route('investments.report'))->assertOk()
            ->assertViewHas('pipeline', ['in_approval' => 1, 'approved' => 1])
            ->assertViewHas('openApprovals', 1)
            ->assertViewHas('openDeviations', 1)
            ->assertViewHas('statusOptions', fn (array $options): bool => array_keys($options) === array_column(InvestmentCaseStatus::cases(), 'value')
                && $options['in_approval'] === InvestmentCaseStatus::InApproval->label());
        $this->assertSame(1, preg_match('/<span>' . preg_quote(e(InvestmentCaseStatus::InApproval->label()), '/') . '<\/span><span class="tabular-nums">1<\/span>/u', (string) $response->getContent()));

        $this->actingAs($this->admin)->get(route('investments.report', ['status' => 'approved']))->assertOk()
            ->assertViewHas('pipeline', ['approved' => 1]);

        $csv = (string) $this->actingAs($this->admin)->get(route('investments.report', ['export' => 'csv']))->assertOk()->getContent();
        $this->assertSame(1, preg_match('/Ersatz Servicefahrzeug"?;"?approved"?;/u', $csv), 'Die Exportzeile trägt nicht den gespeicherten Statuswert.');
    }

    public function test_portfolios_count_cases_by_the_stored_status(): void {
        $program = InvestmentProgram::query()->create(['organization_id' => $this->organization->id, 'name' => 'Werkstatt 2030', 'starts_year' => 2026, 'ends_year' => 2027, 'currency' => 'EUR', 'status' => 'planning']);
        $objective = StrategicObjective::query()->create(['organization_id' => $this->organization->id, 'title' => 'Energiekosten senken', 'is_active' => true]);
        foreach ([InvestmentCaseStatus::Approved, InvestmentCaseStatus::Approved, InvestmentCaseStatus::Idea] as $index => $status) {
            $this->investment($status, ['title' => 'Akte ' . $index, 'investment_program_id' => $program->id, 'strategic_objective_id' => $objective->id, 'planned_year' => 2026]);
        }

        $this->assertSame(['approved' => 2, 'idea' => 1], app(InvestmentProgramService::class)->portfolio($program)['by_status']);
        $this->assertSame(['approved' => 2, 'idea' => 1], app(StrategicObjectiveService::class)->portfolio($objective)['by_status']);

        foreach ([route('investments.programs.show', $program), route('investments.objectives.show', $objective)] as $url) {
            $html = $this->page($url);
            $this->assertSame(2, substr_count($html, '<td>' . e(InvestmentCaseStatus::Approved->label()) . '</td>'));
            $this->assertSame(1, substr_count($html, '<td>' . e(InvestmentCaseStatus::Idea->label()) . '</td>'));
        }
    }

    public function test_liquidity_forecast_plans_only_cases_that_are_still_open(): void {
        foreach (InvestmentCaseStatus::cases() as $status) {
            $this->investment($status, ['title' => $status->value, 'starts_on' => '2026-11-01', 'estimated_amount' => '1000.00', 'currency' => 'EUR']);
        }

        $items = (new PlannedInvestmentSource)->items($this->organization, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-12-31'));

        $this->assertEqualsCanonicalizing(
            ['idea', 'screening', 'comparison', 'budget_request', 'in_approval', 'approved', 'in_progress'],
            array_column($items, 'label'),
        );
    }
}
