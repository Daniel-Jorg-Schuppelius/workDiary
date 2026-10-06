<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractNegotiationApprovalRoundTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Applications;

use App\Enums\Applications\{ApplicationContractNegotiationStatus, ApplicationOpportunityStatus};
use App\Models\Applications\{ApplicationContractNegotiation, ApplicationOpportunity};
use App\Models\Approval\Approval;
use App\Models\Platform\User;
use App\Services\Applications\ContractNegotiationService;
use App\Services\Approval\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Eine Freigabe gilt einer Version (Entscheidung 2026-10-05): eine neue
 * Version nach einem Urteil startet eine neue Freigaberunde, die alte bleibt
 * Historie, dieselben Personen entscheiden die neue Runde wieder.
 */
final class ContractNegotiationApprovalRoundTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $creator;

    private User $commercial;

    private User $technical;

    private ContractNegotiationService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->creator = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->commercial = User::factory()->admin()->create(['organization_id' => $this->organization->id, 'name' => 'Karla Kaufmann']);
        $this->technical = User::factory()->admin()->create(['organization_id' => $this->organization->id, 'name' => 'Theo Technik']);
        $this->service = app(ContractNegotiationService::class);
    }

    private function negotiationWithFirstVersion(): ApplicationContractNegotiation {
        $opportunity = ApplicationOpportunity::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Gewonnene Ausschreibung',
            'kind' => 'tender',
            'status' => ApplicationOpportunityStatus::Won,
            'created_by' => $this->creator->id,
        ]);
        $negotiation = $this->service->open($opportunity, 'Rahmenvertrag 2027', null, $this->creator);
        $this->service->addVersion($negotiation, 'draft', 'Erstentwurf', [], $this->creator);

        return $negotiation->refresh();
    }

    /** @return array<int, string|null> Stufe → Entscheidung */
    private function decisions(ApplicationContractNegotiation $negotiation, int $round): array {
        return $negotiation->approvals()->where('round', $round)->orderBy('step')->pluck('decision', 'step')->all();
    }

    public function test_new_version_after_full_approval_restarts_approval_and_can_be_concluded(): void {
        $negotiation = $this->negotiationWithFirstVersion();
        $this->service->approve($negotiation, $this->commercial);
        $this->service->approve($negotiation, $this->technical);
        $this->assertSame(ApplicationContractNegotiationStatus::Approved, $negotiation->fresh()->status);

        $version = $this->service->addVersion($negotiation, 'final', 'Nachverhandelt', [], $this->creator);

        $this->assertSame(ApplicationContractNegotiationStatus::InReview, $negotiation->fresh()->status);
        $this->assertSame(2, $version->approval_round);
        // Alte Runde bleibt Historie, neue Runde ist offen.
        $this->assertSame([1 => 'approved', 2 => 'approved'], $this->decisions($negotiation, 1));
        $this->assertSame([1 => null, 2 => null], $this->decisions($negotiation, 2));
        $restart = $negotiation->auditLogs()->where('event', 'contract.approval_restarted')->firstOrFail();
        $this->assertSame(['round' => 2, 'version' => 2], $restart->changes);

        // Ohne neue Freigabe kein Abschluss.
        try {
            $this->service->conclude($negotiation, 'concluded', null, $this->commercial);
            $this->fail('Abschluss ohne Freigabe der neuen Version.');
        } catch (\RuntimeException) {
        }

        // Dieselben Personen entscheiden dieselben Stufen erneut.
        $this->assertSame('pending', $this->service->approve($negotiation, $this->commercial));
        $this->assertSame('approved_all', $this->service->approve($negotiation, $this->technical));
        $this->assertSame(ApplicationContractNegotiationStatus::Approved, $negotiation->fresh()->status);

        $this->service->conclude($negotiation, 'concluded', null, $this->commercial);
        $this->assertSame(ApplicationContractNegotiationStatus::Concluded, $negotiation->fresh()->status);
    }

    public function test_counter_version_after_approval_restarts_as_counter(): void {
        $negotiation = $this->negotiationWithFirstVersion();
        $this->service->approve($negotiation, $this->commercial);
        $this->service->approve($negotiation, $this->technical);

        $this->service->addVersion($negotiation, 'counter', 'Gegenentwurf Kunde', [], $this->creator);

        $this->assertSame(ApplicationContractNegotiationStatus::Counter, $negotiation->fresh()->status);
        $this->assertSame(2, app(ApprovalService::class)->currentRound($negotiation));
    }

    public function test_partial_approval_belongs_to_the_old_version(): void {
        $negotiation = $this->negotiationWithFirstVersion();
        $this->service->approve($negotiation, $this->commercial);
        $staleStep = $negotiation->approvals()->where('round', 1)->where('step', 2)->firstOrFail();

        $this->service->addVersion($negotiation, 'draft', 'Überarbeitet', [], $this->creator);

        $this->assertSame([1 => 'approved', 2 => null], $this->decisions($negotiation, 1));
        $this->assertSame([1 => null, 2 => null], $this->decisions($negotiation, 2));

        // Die offene Stufe der abgelösten Runde ist auch direkt nicht mehr entscheidbar.
        try {
            app(ApprovalService::class)->decide($staleStep, $this->technical, 'approved', null, (int) $this->creator->id);
            $this->fail('Stufe einer abgelösten Runde wurde entschieden.');
        } catch (\RuntimeException) {
        }
        // Auch der Genehmigungs-Eingang lehnt sie ab — selbst für die zuständige Rolle.
        $this->technical->assignRole('teamleitung');
        $this->actingAs($this->technical)
            ->post(route('servicedesk.approvals.decide', $staleStep), ['decision' => 'approved'])
            ->assertSessionHas('error', __('Diese Freigaberunde wurde durch eine neue abgelöst.'));
        $this->assertNull($staleStep->fresh()->decision);

        // Die Genehmigung der alten Stufe 1 zählt nicht: die neue Runde braucht beide Stufen.
        $this->assertSame('pending', $this->service->approve($negotiation, $this->technical));
        $this->assertSame(ApplicationContractNegotiationStatus::InReview, $negotiation->fresh()->status);
        $this->assertSame('approved_all', $this->service->approve($negotiation, $this->commercial));
        $this->assertSame([1 => 'approved', 2 => 'approved'], $this->decisions($negotiation, 2));
        $this->assertNull($staleStep->fresh()->decision);
    }

    public function test_version_without_any_verdict_keeps_the_running_round(): void {
        $negotiation = $this->negotiationWithFirstVersion();

        $version = $this->service->addVersion($negotiation, 'counter', 'Gegenentwurf', [], $this->creator);

        $this->assertSame(1, $version->approval_round);
        $this->assertSame(2, $negotiation->approvals()->count());
        $this->assertSame(1, app(ApprovalService::class)->currentRound($negotiation));
        $this->assertFalse($negotiation->auditLogs()->where('event', 'contract.approval_restarted')->exists());
    }

    public function test_four_eyes_hold_within_a_round_but_not_across_rounds(): void {
        $negotiation = $this->negotiationWithFirstVersion();
        $this->service->approve($negotiation, $this->commercial);
        $this->service->approve($negotiation, $this->technical);
        $this->service->addVersion($negotiation, 'final', null, [], $this->creator);

        // Die Selbstfreigabe-Sperre des Erstellers gilt in jeder Runde.
        try {
            $this->service->approve($negotiation, $this->creator);
            $this->fail('Selbstfreigabe in der neuen Runde.');
        } catch (\RuntimeException) {
        }

        // Stufe 1 der neuen Runde darf entscheiden, wer in Runde 1 schon entschieden hat …
        $this->service->approve($negotiation, $this->commercial);
        // … aber nicht zwei Stufen derselben Runde.
        try {
            $this->service->approve($negotiation, $this->commercial);
            $this->fail('Dieselbe Person hat beide Stufen einer Runde entschieden.');
        } catch (\RuntimeException) {
        }
        $this->assertSame([1 => 'approved', 2 => null], $this->decisions($negotiation, 2));

        $this->assertSame('approved_all', $this->service->approve($negotiation, $this->technical));
    }

    public function test_file_shows_current_round_and_earlier_rounds_as_history(): void {
        $negotiation = $this->negotiationWithFirstVersion();

        // Eine Runde: Anzeige wie bisher, keine Historie.
        $this->actingAs($this->creator)->get(route('tenders.show', $negotiation->negotiable))->assertOk()
            ->assertSee('Freigaben:')
            ->assertDontSee('Frühere Freigaberunden');

        $this->service->approve($negotiation, $this->commercial);
        $this->actingAs($this->creator)->from(route('tenders.show', $negotiation->negotiable))
            ->post(route('applications.negotiations.versions.store', $negotiation), ['kind' => 'counter', 'summary' => 'Gegenentwurf'])
            ->assertRedirect(route('tenders.show', $negotiation->negotiable))
            ->assertSessionHas('success', __('Vertragsversion abgelegt — die Freigabe beginnt neu (Runde :round).', ['round' => 2]));

        $response = $this->actingAs($this->creator)->get(route('tenders.show', $negotiation->negotiable))->assertOk()
            ->assertSee('Frühere Freigaberunden')
            ->assertSee('Runde 1 – abgelöst durch Version 2')
            ->assertSeeInOrder(['Freigaben (Runde 2):', 'Stufe 1: offen', 'Stufe 2: offen', 'Frühere Freigaberunden', 'Stufe 1: Genehmigt', 'Karla Kaufmann', 'Stufe 2: nicht entschieden']);
        // Die Entscheidung der abgelösten Runde steht nur in der Historie.
        $this->assertStringNotContainsString('Genehmigt', Str::between((string) $response->getContent(), 'Freigaben (Runde 2):', 'Frühere Freigaberunden'));

        // Eine Version ohne Urteil in der laufenden Runde meldet keinen Neustart.
        $this->actingAs($this->creator)->from(route('tenders.show', $negotiation->negotiable))
            ->post(route('applications.negotiations.versions.store', $negotiation), ['kind' => 'draft'])
            ->assertSessionHas('success', __('Vertragsversion abgelegt.'));
        $this->assertSame(2, Approval::query()->where('approvable_id', $negotiation->id)->where('approvable_type', $negotiation->getMorphClass())->max('round'));
    }
}
