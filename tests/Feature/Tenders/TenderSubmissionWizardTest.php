<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderSubmissionWizardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Tenders;

use App\Enums\Applications\TenderProcedureType;
use App\Models\Applications\{ApplicationOpportunity, TenderCompetitorBid};
use App\Models\User;
use App\Services\Applications\TenderSubmissionPreflight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Abgabeassistent und Submissionsergebnis (MVP-628).
 *
 * Kern der Prüfung: **Sperren sperren, Hinweise nicht.** Eine abgelaufene
 * Abgabefrist darf die Dokumentation nicht verhindern — sonst ließe sich ein
 * bereits abgegebenes Angebot nie eintragen.
 */
final class TenderSubmissionWizardTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function opportunity(array $attributes = []): ApplicationOpportunity {
        return ApplicationOpportunity::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'title' => 'Neubau Kita',
            'kind' => 'tender',
            'status' => 'in_progress',
            'go_decision' => 'go',
            'estimated_value' => '250000',
            'procedure_type' => TenderProcedureType::PublicInvitation,
            'submission_deadline' => now()->addWeeks(2)->toDateString(),
            'binding_until' => now()->addMonths(2)->toDateString(),
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    public function test_wizard_reports_a_clean_case(): void {
        $opportunity = $this->opportunity();

        $response = $this->actingAs($this->admin)->get(route('tenders.submit-wizard', $opportunity));

        $response->assertOk()->assertSee('Keine Beanstandungen. Die Akte ist abgabebereit.');
        $this->assertSame([], $response->viewData('findings'));
        $this->assertFalse($response->viewData('blocked'));
    }

    /** Ohne Go-Entscheidung ist die Abgabe gesperrt — nicht bloß beanstandet. */
    public function test_missing_go_decision_blocks(): void {
        $opportunity = $this->opportunity(['go_decision' => 'pending']);

        $response = $this->actingAs($this->admin)->get(route('tenders.submit-wizard', $opportunity));

        $this->assertTrue($response->viewData('blocked'));
        $this->assertContains('go_missing', array_column($response->viewData('findings'), 'code'));
    }

    /** Offene Pflicht-Unterlagen sperren; optionale nicht. */
    public function test_open_required_documents_block_but_optional_ones_do_not(): void {
        $opportunity = $this->opportunity();
        $opportunity->requirements()->create([
            'organization_id' => $this->organization->id,
            'label' => 'Referenzliste',
            'kind' => 'document',
            'required' => true,
            'status' => 'open',
            'position' => 1,
        ]);

        $blocked = $this->actingAs($this->admin)->get(route('tenders.submit-wizard', $opportunity));
        $this->assertTrue($blocked->viewData('blocked'));

        $opportunity->requirements()->update(['required' => false]);
        $clear = $this->actingAs($this->admin)->get(route('tenders.submit-wizard', $opportunity));
        $this->assertFalse($clear->viewData('blocked'));
    }

    /**
     * Eine abgelaufene Frist ist ein Hinweis, keine Sperre: Die Einreichung
     * wird hier dokumentiert, oft am Tag danach.
     */
    public function test_expired_deadline_warns_but_does_not_block(): void {
        $opportunity = $this->opportunity(['submission_deadline' => now()->subDays(2)->toDateString()]);

        $response = $this->actingAs($this->admin)->get(route('tenders.submit-wizard', $opportunity));

        $this->assertFalse($response->viewData('blocked'));
        $codes = array_column($response->viewData('findings'), 'code');
        $this->assertContains('deadline_passed', $codes);

        // Und die Dokumentation gelingt trotzdem.
        $this->actingAs($this->admin)->post(route('tenders.submit', $opportunity), ['channel' => 'portal'])
            ->assertRedirect();
        $this->assertSame('submitted', $opportunity->refresh()->status);
    }

    /** Fehlende Nebenangaben melden sich, ohne aufzuhalten. */
    public function test_missing_side_information_only_warns(): void {
        $opportunity = $this->opportunity(['procedure_type' => null, 'binding_until' => null, 'estimated_value' => null]);

        $findings = app(TenderSubmissionPreflight::class)->check($opportunity);
        $codes = array_column($findings, 'code');

        $this->assertContains('procedure_missing', $codes);
        $this->assertContains('binding_missing', $codes);
        $this->assertContains('value_missing', $codes);
        $this->assertFalse(app(TenderSubmissionPreflight::class)->isBlocked($findings));
    }

    // ── Submissionsergebnis ──────────────────────────────────────────────

    public function test_competitor_bid_is_recorded_with_own_offer(): void {
        $opportunity = $this->opportunity();

        $this->actingAs($this->admin)->post(route('tenders.bids.store', $opportunity), [
            'bidder_name' => 'Wir selbst',
            'amount' => '250000',
            'rank' => 2,
            'is_own' => '1',
            'source' => 'opening',
        ])->assertRedirect();

        $this->actingAs($this->admin)->post(route('tenders.bids.store', $opportunity), [
            'bidder_name' => 'Bau GmbH',
            'amount' => '200000',
            'rank' => 1,
            'is_winner' => '1',
            'source' => 'opening',
        ])->assertRedirect();

        $bids = $opportunity->competitorBids()->get();
        $this->assertCount(2, $bids);
        // Nach Rang sortiert: der Zuschlagsempfänger zuerst.
        $this->assertSame('Bau GmbH', $bids->first()?->bidder_name);
        $this->assertTrue($bids->firstWhere('is_own', true)?->is_own);
    }

    /** Die Akte zeigt den Preisabstand — das ist der Ertrag der Erfassung. */
    public function test_case_shows_the_price_gap(): void {
        $opportunity = $this->opportunity();
        TenderCompetitorBid::query()->create([
            'organization_id' => $this->organization->id,
            'application_opportunity_id' => $opportunity->id,
            'bidder_name' => 'Wir selbst', 'amount' => '220000', 'rank' => 2, 'is_own' => true, 'source' => 'opening',
        ]);
        TenderCompetitorBid::query()->create([
            'organization_id' => $this->organization->id,
            'application_opportunity_id' => $opportunity->id,
            'bidder_name' => 'Bau GmbH', 'amount' => '200000', 'rank' => 1, 'is_winner' => true, 'source' => 'opening',
        ]);

        $this->actingAs($this->admin)
            ->get(route('tenders.show', $opportunity))
            ->assertOk()
            ->assertSee('Submissionsergebnis')
            // 220.000 sind 10 % über 200.000.
            ->assertSee('10,0 %');
    }

    public function test_bid_of_another_case_cannot_be_removed(): void {
        $opportunity = $this->opportunity();
        $other = $this->opportunity(['title' => 'Andere Akte']);
        $bid = TenderCompetitorBid::query()->create([
            'organization_id' => $this->organization->id,
            'application_opportunity_id' => $other->id,
            'bidder_name' => 'Bau GmbH', 'amount' => '200000', 'source' => 'opening',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('tenders.bids.destroy', [$opportunity, $bid]))
            ->assertNotFound();
    }
}
