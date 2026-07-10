<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsRiskAssessmentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Enums\Isms\{AssessmentKind, AssessmentStatus, RiskStatus};
use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Models\Isms\{IsmsRisk, IsmsRiskAssessment};
use App\Models\Notification\NotificationRule;
use App\Models\{Organization, User};
use App\Services\Isms\RiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Risiko-Bewertungshistorie (Feature 046, Inkrement D): neue Stände statt
 * Überschreiben (assessment_no je Risiko), Freigabe mit Person/Zeitpunkt
 * (freigegebene Stände unveränderlich), Sync der maßgeblichen
 * Netto-Bewertung auf das Risiko (gross/target nicht), Direktbewertung
 * beim Inline-Update, Restrisiko-Akzeptanz nur mit freigegebener
 * Netto-Bewertung inkl. valid_until, Scanner-Event isms.riskReviewDue
 * (Dedup), Permissions und Mandantengrenze.
 */
class IsmsRiskAssessmentTest extends TestCase {
    use RefreshDatabase;

    public function test_store_creates_draft_with_sequential_numbers_per_risk(): void {
        $admin = User::factory()->admin()->create();
        $risk = $this->makeRisk($admin);
        $otherRisk = $this->makeRisk($admin);

        foreach ([['gross', 4, 5], ['net', 2, 3]] as [$kind, $likelihood, $impact]) {
            $this->actingAs($admin)
                ->post(route('isms.risks.assessments.store', $risk), [
                    'kind' => $kind,
                    'likelihood' => $likelihood,
                    'impact' => $impact,
                    'rationale' => 'Bewertung im Risiko-Workshop.',
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $numbers = $risk->assessments()->orderBy('assessment_no')->pluck('assessment_no')->all();
        $this->assertSame([1, 2], $numbers, 'assessment_no läuft je Risiko fortlaufend');

        /** @var IsmsRiskAssessment $first */
        $first = $risk->assessments()->orderBy('assessment_no')->firstOrFail();
        $this->assertSame(AssessmentStatus::Draft, $first->status, 'Neue Stände starten immer als Entwurf');
        $this->assertSame(20, $first->score, 'Score wird serverseitig berechnet (4×5)');
        $this->assertSame('B-1', $first->displayNo());

        // Die Nummerierung des zweiten Risikos läuft unabhängig.
        $this->actingAs($admin)
            ->post(route('isms.risks.assessments.store', $otherRisk), [
                'kind' => 'net',
                'likelihood' => 1,
                'impact' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame([1], $otherRisk->assessments()->pluck('assessment_no')->all());
    }

    public function test_approved_assessment_is_immutable(): void {
        $admin = User::factory()->admin()->create();
        $risk = $this->makeRisk($admin);
        $assessment = $this->makeAssessment($admin, $risk);

        $this->actingAs($admin)
            ->post(route('isms.risks.assessments.approve', $assessment))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $assessment->refresh();
        $this->assertSame(AssessmentStatus::Approved, $assessment->status);
        $this->assertSame($admin->id, (int) $assessment->approved_by_user_id, 'Freigabe dokumentiert die Person');
        $this->assertNotNull($assessment->approved_at, 'Freigabe dokumentiert den Zeitpunkt');

        // Update wird vom Model-Guard abgewiesen …
        try {
            $assessment->update(['rationale' => 'Nachträglich geändert.']);
            $this->fail('Freigegebene Bewertungen dürfen nicht änderbar sein.');
        } catch (ValidationException) {
            // erwartet
        }

        // … Löschen ebenso (Service UND Model-Guard).
        try {
            app(RiskService::class)->deleteAssessment($assessment->refresh(), $admin);
            $this->fail('Freigegebene Bewertungen dürfen nicht löschbar sein.');
        } catch (ValidationException) {
            // erwartet
        }

        $this->assertNotSame('Nachträglich geändert.', $assessment->refresh()->rationale);
        $this->assertNull($assessment->deleted_at);

        // Erneute Freigabe ist ebenfalls unzulässig.
        $this->actingAs($admin)
            ->from(route('isms.risks.index'))
            ->post(route('isms.risks.assessments.approve', $assessment))
            ->assertRedirect()
            ->assertSessionHasErrors('status');
    }

    public function test_draft_can_be_deleted(): void {
        $admin = User::factory()->admin()->create();
        $risk = $this->makeRisk($admin);
        $assessment = $this->makeAssessment($admin, $risk);

        $this->actingAs($admin)
            ->delete(route('isms.risks.assessments.destroy', $assessment))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('isms_risk_assessments', ['id' => $assessment->id]);
    }

    public function test_approving_net_assessment_syncs_risk_but_gross_and_target_do_not(): void {
        $admin = User::factory()->admin()->create();
        $risk = $this->makeRisk($admin, ['likelihood' => 2, 'impact' => 2, 'score' => 4]);

        // gross/target dokumentieren nur — das Risiko bleibt unberührt.
        foreach ([AssessmentKind::Gross, AssessmentKind::Target] as $kind) {
            $assessment = $this->makeAssessment($admin, $risk, ['kind' => $kind->value, 'likelihood' => 5, 'impact' => 5, 'score' => 25]);
            app(RiskService::class)->approveAssessment($assessment, $admin);
        }
        $this->assertSame(4, $risk->refresh()->score, 'gross/target dürfen das Risiko nicht verändern');

        // Die Netto-Freigabe zieht likelihood/impact/score nach.
        $net = $this->makeAssessment($admin, $risk, ['kind' => 'net', 'likelihood' => 4, 'impact' => 5, 'score' => 20]);
        app(RiskService::class)->approveAssessment($net, $admin);

        $risk->refresh();
        $this->assertSame(4, $risk->likelihood);
        $this->assertSame(5, $risk->impact);
        $this->assertSame(20, $risk->score, 'Approved net ist die maßgebliche aktuelle Bewertung');
    }

    public function test_inline_risk_update_records_self_approved_net_assessment(): void {
        $admin = User::factory()->admin()->create();
        $risk = $this->makeRisk($admin, ['likelihood' => 2, 'impact' => 2, 'score' => 4]);

        $this->actingAs($admin)
            ->put(route('isms.risks.update', $risk), [
                'title' => $risk->title,
                'category' => $risk->category->value,
                'likelihood' => 3,
                'impact' => 4,
                'treatment' => $risk->treatment->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        /** @var IsmsRiskAssessment $assessment */
        $assessment = $risk->assessments()->firstOrFail();
        $this->assertSame(AssessmentKind::Net, $assessment->kind);
        $this->assertSame(AssessmentStatus::Approved, $assessment->status, 'Direktbewertung ist selbst-freigegeben');
        $this->assertSame($admin->id, (int) $assessment->approved_by_user_id);
        $this->assertSame(12, $assessment->score);
        $this->assertSame(__('isms.assessment.rationale_direct'), $assessment->rationale);

        // Unveränderte Bewertung erzeugt KEINEN weiteren Stand.
        $this->actingAs($admin)
            ->put(route('isms.risks.update', $risk), [
                'title' => 'Nur Titel geändert',
                'category' => $risk->category->value,
                'likelihood' => 3,
                'impact' => 4,
                'treatment' => $risk->treatment->value,
            ])
            ->assertRedirect();

        $this->assertSame(1, $risk->assessments()->count(), 'Nur Bewertungsänderungen historisieren');
    }

    public function test_accepting_risk_requires_approved_net_assessment_with_valid_until(): void {
        $admin = User::factory()->admin()->create();
        $risk = $this->makeRisk($admin, ['status' => RiskStatus::Analyzed->value]);

        // Ohne freigegebene Netto-Bewertung mit valid_until ⇒ Fehler.
        $this->actingAs($admin)
            ->from(route('isms.risks.index'))
            ->post(route('isms.risks.transition', $risk), ['status' => 'accepted'])
            ->assertRedirect()
            ->assertSessionHasErrors('status');
        $this->assertSame(RiskStatus::Analyzed, $risk->refresh()->status);

        // Freigegebene Netto-Bewertung OHNE valid_until reicht nicht.
        IsmsRiskAssessment::factory()->net()->approved($admin->id)->create([
            'organization_id' => $admin->organization_id,
            'isms_risk_id' => $risk->id,
            'assessment_no' => 1,
            'valid_until' => null,
        ]);
        $this->actingAs($admin)
            ->from(route('isms.risks.index'))
            ->post(route('isms.risks.transition', $risk), ['status' => 'accepted'])
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        // Mit valid_until ⇒ Übergang erlaubt.
        IsmsRiskAssessment::factory()->net()->approved($admin->id)->create([
            'organization_id' => $admin->organization_id,
            'isms_risk_id' => $risk->id,
            'assessment_no' => 2,
            'valid_until' => now()->addYear()->toDateString(),
        ]);
        $this->actingAs($admin)
            ->post(route('isms.risks.transition', $risk), ['status' => 'accepted'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(RiskStatus::Accepted, $risk->refresh()->status);
    }

    public function test_scanner_fires_risk_review_due_exactly_once(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);

        $owner = User::factory()->user()->create(['organization_id' => $admin->organization_id]);

        // Determinismus: nur In-App, Empfänger ausschließlich der
        // Risikoeigentümer (notify_affected).
        NotificationRule::factory()->forEvent(NotificationEvent::IsmsRiskReviewDue)->create([
            'organization_id' => $admin->organization_id,
            'channels' => [NotificationChannel::InApp->value],
            'notify_affected' => true,
            'recipient_roles' => [],
        ]);

        $risk = $this->makeRisk($admin, ['owner_user_id' => $owner->id]);

        // Älterer (abgelöster) Stand mit überschrittenem valid_until darf
        // NICHT mehr feuern — nur der jüngste freigegebene Netto-Stand zählt.
        IsmsRiskAssessment::factory()->net()->approved($admin->id)->create([
            'organization_id' => $admin->organization_id,
            'isms_risk_id' => $risk->id,
            'assessment_no' => 1,
            'valid_until' => now()->subMonths(6)->toDateString(),
        ]);
        IsmsRiskAssessment::factory()->net()->approved($admin->id)->create([
            'organization_id' => $admin->organization_id,
            'isms_risk_id' => $risk->id,
            'assessment_no' => 2,
            'valid_until' => now()->addDays(10)->toDateString(),
        ]);

        // Risiko mit fernem Reviewdatum ⇒ kein Event.
        $quietRisk = $this->makeRisk($admin, ['owner_user_id' => $owner->id]);
        IsmsRiskAssessment::factory()->net()->approved($admin->id)->create([
            'organization_id' => $admin->organization_id,
            'isms_risk_id' => $quietRisk->id,
            'assessment_no' => 1,
            'valid_until' => now()->addYear()->toDateString(),
        ]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, $owner->notifications()->count(), 'Dedup: genau eine Benachrichtigung');
        $data = (array) $owner->notifications()->first()?->data;
        $this->assertSame(NotificationEvent::IsmsRiskReviewDue->value, $data['event'] ?? null);
    }

    public function test_regular_user_and_viewer_cannot_manage_assessments(): void {
        $user = User::factory()->user()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
        $gf = User::factory()->geschaeftsfuehrung()->create(['organization_id' => $user->organization_id]);
        $risk = $this->makeRisk($admin);
        $assessment = $this->makeAssessment($admin, $risk);

        foreach ([$user, $gf] as $forbidden) {
            $this->actingAs($forbidden)
                ->post(route('isms.risks.assessments.store', $risk), [
                    'kind' => 'net',
                    'likelihood' => 1,
                    'impact' => 1,
                ])
                ->assertForbidden();
            $this->actingAs($forbidden)
                ->post(route('isms.risks.assessments.approve', $assessment))
                ->assertForbidden();
        }

        $this->assertSame(1, $risk->assessments()->count());
        $this->assertSame(AssessmentStatus::Draft, $assessment->refresh()->status);
    }

    public function test_cross_organization_assessment_is_not_accessible(): void {
        $admin = User::factory()->admin()->create();
        $otherOrg = Organization::factory()->create(['slug' => 'isms-assessment-cross']);
        $otherAdmin = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        $foreignRisk = $this->makeRisk($otherAdmin);
        $foreignAssessment = $this->makeAssessment($otherAdmin, $foreignRisk);

        app()->instance('currentOrganization', $admin->organization);

        $this->actingAs($admin)
            ->post(route('isms.risks.assessments.store', $foreignRisk), [
                'kind' => 'net',
                'likelihood' => 1,
                'impact' => 1,
            ])
            ->assertNotFound();
        $this->actingAs($admin)
            ->post(route('isms.risks.assessments.approve', $foreignAssessment))
            ->assertNotFound();

        $this->assertSame(1, $foreignRisk->assessments()->withoutGlobalScopes()->count());
        $this->assertSame(AssessmentStatus::Draft, $foreignAssessment->refresh()->status);
    }

    /** Risiko in der Organisation des Users (Muster IsmsRiskTest). */
    private function makeRisk(User $owner, array $overrides = []): IsmsRisk {
        app()->instance('currentOrganization', $owner->organization);

        return IsmsRisk::factory()->create([
            'organization_id' => $owner->organization_id,
            ...$overrides,
        ]);
    }

    /** Bewertungs-Entwurf zum Risiko (laufende Nummer je Risiko). */
    private function makeAssessment(User $owner, IsmsRisk $risk, array $overrides = []): IsmsRiskAssessment {
        app()->instance('currentOrganization', $owner->organization);

        return IsmsRiskAssessment::factory()->create([
            'organization_id' => $owner->organization_id,
            'isms_risk_id' => $risk->id,
            'assessment_no' => ((int) $risk->assessments()->withTrashed()->max('assessment_no')) + 1,
            'created_by_user_id' => $owner->id,
            ...$overrides,
        ]);
    }
}
