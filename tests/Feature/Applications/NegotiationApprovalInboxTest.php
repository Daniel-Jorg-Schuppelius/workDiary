<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NegotiationApprovalInboxTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Applications;

use App\Enums\Applications\{ApplicationContractNegotiationStatus, ApplicationOpportunityStatus, JobApplicationStatus};
use App\Enums\Approval\ApprovalStepKind;
use App\Enums\User\UserRole;
use App\Models\Applications\{ApplicationContractNegotiation, ApplicationOpportunity, JobApplication};
use App\Models\Approval\Approval;
use App\Models\Platform\{Organization, User};
use App\Services\Applications\ContractNegotiationService;
use App\Services\Approval\ApprovalResponsibility;
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Entscheidung 2026-10-06: Stufen einer Vertragsverhandlung erscheinen im
 * Genehmigungs-Eingang über die Abbildung Stufenart → Rolle der
 * Organisation; eine Entscheidung dort nimmt denselben Weg wie an der Akte.
 */
final class NegotiationApprovalInboxTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $creator;

    private User $accounting;

    private User $lead;

    private User $personnel;

    private ContractNegotiationService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->creator = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->accounting = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $this->personnel = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
        $this->service = app(ContractNegotiationService::class);
    }

    private function tenderNegotiation(string $title = 'Rahmenvertrag 2027', ?User $creator = null): ApplicationContractNegotiation {
        $creator ??= $this->creator;
        $opportunity = ApplicationOpportunity::query()->create([
            'organization_id' => $creator->organization_id,
            'title' => 'Ausschreibung ' . $title,
            'kind' => 'tender',
            'status' => ApplicationOpportunityStatus::Won,
            'created_by' => $creator->id,
        ]);
        $negotiation = $this->service->open($opportunity, $title, null, $creator);
        $this->service->addVersion($negotiation, 'draft', 'Erstentwurf', [], $creator);

        return $negotiation->refresh();
    }

    private function hrNegotiation(string $title = 'Arbeitsvertrag Kim'): ApplicationContractNegotiation {
        $application = JobApplication::query()->create([
            'organization_id' => $this->organization->id,
            'candidate_name' => 'Kim Beispiel',
            'email' => 'kim@example.test',
            'source' => 'website',
            'status' => JobApplicationStatus::Offer,
            'received_at' => now(),
        ]);
        $negotiation = $this->service->open($application, $title, null, $this->creator);
        $this->service->addVersion($negotiation, 'draft', 'Angebot', [], $this->creator);

        return $negotiation->refresh();
    }

    private function step(ApplicationContractNegotiation $negotiation, int $step, int $round = 1): Approval {
        return $negotiation->approvals()->where('round', $round)->where('step', $step)->orderByDesc('id')->firstOrFail();
    }

    private function responsibility(ApprovalStepKind $kind, UserRole $role): string {
        return $kind->label() . ' · ' . __('Rolle') . ': ' . $role->label();
    }

    /** @return array<string, mixed>|null */
    private function lastAudit(ApplicationContractNegotiation $negotiation, string $event): ?array {
        return $negotiation->auditLogs()->where('event', $event)->latest('id')->first()?->changes;
    }

    public function test_steps_appear_for_the_role_of_their_kind(): void {
        $tender = $this->tenderNegotiation();
        $hr = $this->hrNegotiation();

        // Kaufmännisch ist jeweils Stufe 1 — beide bei der Buchhaltung, mit Verweis auf die Akte.
        $this->actingAs($this->accounting)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertSee('Vertragsverhandlung: Rahmenvertrag 2027')
            ->assertSee('Vertragsverhandlung: Arbeitsvertrag Kim')
            ->assertSee(route('tenders.show', $tender->negotiable), false)
            ->assertSee($this->responsibility(ApprovalStepKind::Commercial, UserRole::Buchhaltung));

        // Fachliche und HR-Stufe warten auf Stufe 1.
        $this->actingAs($this->lead)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertDontSee('Vertragsverhandlung:');
        $this->actingAs($this->personnel)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertDontSee('Vertragsverhandlung:');

        $this->actingAs($this->accounting)
            ->post(route('servicedesk.approvals.decide', $this->step($hr, 1)), ['decision' => 'approved'])
            ->assertRedirect(route('servicedesk.approvals.index'));

        // Die HR-Stufe sieht nur die Personalverwaltung, die Buchhaltung nicht mehr.
        $this->actingAs($this->personnel)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertSee('Vertragsverhandlung: Arbeitsvertrag Kim')
            ->assertDontSee('Vertragsverhandlung: Rahmenvertrag 2027')
            ->assertSee(route('recruiting.applications.show', $hr->negotiable), false)
            ->assertSee($this->responsibility(ApprovalStepKind::Hr, UserRole::Personalverwaltung));
        $this->actingAs($this->accounting)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertDontSee('Vertragsverhandlung: Arbeitsvertrag Kim')
            ->assertSee('Vertragsverhandlung: Rahmenvertrag 2027');
        $this->actingAs($this->lead)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertDontSee('Vertragsverhandlung: Arbeitsvertrag Kim');
    }

    public function test_decision_in_the_inbox_has_the_same_effect_as_on_the_file(): void {
        $viaInbox = $this->tenderNegotiation('Über den Eingang');
        $viaFile = $this->tenderNegotiation('An der Akte');

        $this->service->approve($viaFile, $this->accounting);
        $this->service->approve($viaFile, $this->lead);

        $this->actingAs($this->accounting)
            ->post(route('servicedesk.approvals.decide', $this->step($viaInbox, 1)), ['decision' => 'approved'])
            ->assertRedirect(route('servicedesk.approvals.index'))
            ->assertSessionHas('success', __('Schritt genehmigt.'));
        $this->assertSame(ApplicationContractNegotiationStatus::InReview, $viaInbox->fresh()->status);
        $this->assertSame(['step' => 1, 'round' => 1, 'result' => 'pending'], $this->lastAudit($viaInbox, 'contract.approved_step'));

        // Die nächste Stufe steht jetzt bei der Teamleitung.
        $this->actingAs($this->lead)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertSee('Vertragsverhandlung: Über den Eingang');
        $this->actingAs($this->lead)
            ->post(route('servicedesk.approvals.decide', $this->step($viaInbox, 2)), ['decision' => 'approved'])
            ->assertRedirect(route('servicedesk.approvals.index'));

        foreach ([$viaInbox, $viaFile] as $negotiation) {
            $this->assertSame(ApplicationContractNegotiationStatus::Approved, $negotiation->fresh()->status);
            $this->assertSame(['step' => 2, 'round' => 1, 'result' => 'approved_all'], $this->lastAudit($negotiation, 'contract.approved_step'));
            $this->assertSame(['approved', 'approved'], $negotiation->approvals()->orderBy('step')->pluck('decision')->all());
        }
        $this->actingAs($this->lead)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertDontSee('Vertragsverhandlung: Über den Eingang');

        // Abschluss an der Akte wie gewohnt.
        $this->service->conclude($viaInbox, 'concluded', null, $this->creator);
        $this->assertSame(ApplicationContractNegotiationStatus::Concluded, $viaInbox->fresh()->status);
    }

    public function test_organization_setting_moves_the_step_to_another_role(): void {
        $negotiation = $this->tenderNegotiation();
        $step = $this->step($negotiation, 1);

        Setting::set('approvals.step_role.commercial', UserRole::Teamleitung->value, SettingScope::Organization, $this->organization);

        $this->actingAs($this->accounting)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertDontSee('Vertragsverhandlung: Rahmenvertrag 2027');
        $this->actingAs($this->accounting)->get(route('servicedesk.approvals.decide-form', $step))->assertForbidden();
        $this->actingAs($this->accounting)
            ->post(route('servicedesk.approvals.decide', $step), ['decision' => 'approved'])
            ->assertForbidden();

        $this->actingAs($this->lead)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertSee('Vertragsverhandlung: Rahmenvertrag 2027')
            ->assertSee($this->responsibility(ApprovalStepKind::Commercial, UserRole::Teamleitung));
        $this->actingAs($this->lead)->get(route('servicedesk.approvals.decide-form', $step))->assertOk()
            ->assertSee('Vertragsverhandlung: Rahmenvertrag 2027');
        $this->assertNull($step->fresh()->decision);
    }

    public function test_an_emptied_setting_falls_back_to_the_default_role(): void {
        $this->tenderNegotiation();
        // So legt die Einstellungsseite ein geleertes Feld ab.
        $settings = (array) ($this->organization->settings ?? []);
        data_set($settings, 'approvals.step_role.commercial', null);
        $this->organization->forceFill(['settings' => $settings])->save();

        $this->actingAs($this->accounting)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertSee('Vertragsverhandlung: Rahmenvertrag 2027')
            ->assertSee($this->responsibility(ApprovalStepKind::Commercial, UserRole::Buchhaltung));
    }

    public function test_rejection_ends_the_round_and_a_new_version_restarts_it(): void {
        $negotiation = $this->tenderNegotiation();
        $step1 = $this->step($negotiation, 1);

        $this->actingAs($this->accounting)
            ->post(route('servicedesk.approvals.decide', $step1), ['decision' => 'rejected'])
            ->assertSessionHasErrors('reason');
        $this->actingAs($this->accounting)
            ->post(route('servicedesk.approvals.decide', $step1), ['decision' => 'rejected', 'reason' => 'Zahlungsziel zu lang'])
            ->assertRedirect(route('servicedesk.approvals.index'));

        $this->assertSame('rejected', $step1->fresh()->decision);
        $this->assertSame(ApplicationContractNegotiationStatus::InReview, $negotiation->fresh()->status);
        $this->assertSame(['step' => 1, 'round' => 1, 'result' => 'rejected'], $this->lastAudit($negotiation, 'contract.rejected_step'));

        // Die Kette ist beendet — weder Eingang noch Akte entscheiden Stufe 2.
        $this->actingAs($this->lead)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertDontSee('Vertragsverhandlung: Rahmenvertrag 2027');
        try {
            $this->service->approve($negotiation, $this->lead);
            $this->fail('Freigabe nach Ablehnung.');
        } catch (\RuntimeException $e) {
            $this->assertSame(__('Die Genehmigung wurde bereits abgelehnt.'), $e->getMessage());
        }

        // Eine neue Version startet die nächste Runde wieder bei der Buchhaltung.
        $this->service->addVersion($negotiation, 'counter', 'Zahlungsziel 30 Tage', [], $this->creator);
        $this->actingAs($this->accounting)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertSee('Vertragsverhandlung: Rahmenvertrag 2027');
        $this->assertNull($this->step($negotiation, 1, 2)->decision);
    }

    public function test_question_keeps_the_step_open_and_is_audited(): void {
        $negotiation = $this->tenderNegotiation();
        $step1 = $this->step($negotiation, 1);

        $this->actingAs($this->accounting)
            ->post(route('servicedesk.approvals.decide', $step1), ['decision' => 'question', 'reason' => 'Skonto vereinbart?'])
            ->assertRedirect(route('servicedesk.approvals.index'));

        $this->assertSame('question', $step1->fresh()->decision);
        $this->assertSame(['step' => 1, 'round' => 1, 'result' => 'pending'], $this->lastAudit($negotiation, 'contract.question_step'));
        $this->actingAs($this->accounting)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertSee('Vertragsverhandlung: Rahmenvertrag 2027')
            ->assertSee(__('Rückfrage offen'));

        // Die Akte gibt die Stufe mit der Rückfrage frei.
        $this->assertSame('pending', $this->service->approve($negotiation, $this->accounting));
        $this->assertSame('approved', $step1->fresh()->decision);
    }

    public function test_self_approval_stays_blocked_in_the_inbox(): void {
        $creator = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $negotiation = $this->tenderNegotiation('Eigene Verhandlung', $creator);
        $step1 = $this->step($negotiation, 1);

        $this->actingAs($creator)
            ->post(route('servicedesk.approvals.decide', $step1), ['decision' => 'approved'])
            ->assertSessionHas('error', __('Selbstfreigabe ist nicht zulässig.'));
        $this->assertNull($step1->fresh()->decision);
    }

    public function test_users_without_the_mapped_role_see_nothing(): void {
        $hr = $this->hrNegotiation();
        $step1 = $this->step($hr, 1);

        // Mit Eingangsrecht, aber ohne zugeordnete Rolle.
        $this->actingAs($this->lead)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertDontSee('Vertragsverhandlung: Arbeitsvertrag Kim');
        $this->actingAs($this->lead)
            ->post(route('servicedesk.approvals.decide', $step1), ['decision' => 'approved'])
            ->assertForbidden();

        // Ohne Eingangsrecht kein Eingang.
        $member = $this->orgUser();
        $this->actingAs($member)->get(route('servicedesk.approvals.index'))->assertForbidden();
        $this->assertNull($step1->fresh()->decision);
    }

    public function test_decided_negotiation_leaves_the_inbox(): void {
        $negotiation = $this->tenderNegotiation();
        $this->service->conclude($negotiation, 'declined', 'Kunde abgesprungen', $this->creator);

        $this->actingAs($this->accounting)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertDontSee('Vertragsverhandlung: Rahmenvertrag 2027');
        $this->actingAs($this->accounting)
            ->post(route('servicedesk.approvals.decide', $this->step($negotiation, 1)), ['decision' => 'approved'])
            ->assertSessionHas('error', __('Die Verhandlung ist abgeschlossen.'));
        $this->assertNull($this->step($negotiation, 1)->decision);
    }

    public function test_each_organization_keeps_its_own_mapping(): void {
        $own = $this->tenderNegotiation();
        $ownStep = $this->step($own, 1);

        $other = Organization::factory()->create();
        Setting::set('approvals.step_role.commercial', UserRole::Teamleitung->value, SettingScope::Organization, $other);
        app()->instance('currentOrganization', $other);
        app(PermissionRegistrar::class)->setPermissionsTeamId($other->id);
        $otherCreator = User::factory()->admin()->create(['organization_id' => $other->id]);
        $otherAccounting = User::factory()->buchhaltung()->create(['organization_id' => $other->id]);
        $otherLead = User::factory()->teamleitung()->create(['organization_id' => $other->id]);
        $foreign = $this->tenderNegotiation('Fremdvertrag', $otherCreator);

        // Die Abbildung folgt der Organisation der Stufe, nicht der gerade gewählten.
        $responsibility = app(ApprovalResponsibility::class);
        $this->assertSame(UserRole::Buchhaltung, $responsibility->mappedRole($ownStep));
        $this->assertSame(UserRole::Teamleitung, $responsibility->mappedRole($this->step($foreign, 1)));

        $this->actingAs($otherAccounting)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertDontSee('Vertragsverhandlung:');
        $this->actingAs($otherLead)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertSee('Vertragsverhandlung: Fremdvertrag')
            ->assertDontSee('Vertragsverhandlung: Rahmenvertrag 2027');

        $this->actingAs($this->accounting)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertSee('Vertragsverhandlung: Rahmenvertrag 2027')
            ->assertDontSee('Vertragsverhandlung: Fremdvertrag');
        $this->actingAs($this->lead)->get(route('servicedesk.approvals.index'))->assertOk()
            ->assertDontSee('Vertragsverhandlung:');
    }
}
