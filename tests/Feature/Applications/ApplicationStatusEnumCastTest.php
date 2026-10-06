<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationStatusEnumCastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Applications;

use App\Enums\Applications\{ApplicationContractReviewStatus, ApplicationOpportunityStatus, ApplicationRequirementStatus, EmployeeDraftStatus, JobApplicationInterviewStatus, JobApplicationStatus, JobPostingStatus, JobRequisitionStatus};
use App\Enums\Tenders\TenderNoticeMatchState;
use App\Models\Applications\{ApplicationOpportunity, JobApplication, JobPosting, JobRequisition};
use App\Models\Platform\User;
use App\Models\Tenders\{TenderNotice, TenderNoticeMatch};
use App\Services\Applications\{ContractNegotiationService, RecruitingService, TenderService};
use App\Services\Privacy\SubjectData\{ApplicationRecordsSection, JobApplicationMasterDataSection};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k3-10 (Welle 5): Die Status der Bewerbungs-
 * und Vergabeakten sind Enums. Ein Vergleich gegen die frühere Zeichenkette
 * ist danach still falsch — die Seite lädt weiter, nur die Aktion fehlt oder
 * die Auswahl steht auf dem ersten Eintrag. Jede Stelle, die das traf, hält
 * hier ein Test fest.
 */
final class ApplicationStatusEnumCastTest extends TestCase {
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
    private function opportunity(ApplicationOpportunityStatus $status, array $attributes = []): ApplicationOpportunity {
        return ApplicationOpportunity::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'title' => 'Neubau Kita',
            'kind' => 'tender',
            'status' => $status,
            'go_decision' => 'go',
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function application(JobApplicationStatus $status, array $attributes = []): JobApplication {
        return JobApplication::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'candidate_name' => 'Kim Beispiel',
            'source' => 'website',
            'status' => $status,
            'received_at' => now(),
        ], $attributes));
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

    // ── Ausschreibungsakte ───────────────────────────────────────────────

    public function test_tender_file_offers_transfer_and_negotiation_only_after_the_award(): void {
        $won = $this->opportunity(ApplicationOpportunityStatus::Won);
        $open = $this->opportunity(ApplicationOpportunityStatus::InProgress);

        $html = $this->page(route('tenders.show', $won));
        $this->assertPageHas(route('tenders.transfer', $won), $html);
        $this->assertPageHas(route('tenders.negotiations.store', $won), $html);

        $html = $this->page(route('tenders.show', $open));
        $this->assertPageLacks(route('tenders.transfer', $open), $html);
        $this->assertPageLacks(route('tenders.negotiations.store', $open), $html);
    }

    public function test_tender_file_preselects_the_stored_working_status(): void {
        $question = $this->opportunity(ApplicationOpportunityStatus::Question);
        $html = $this->page(route('tenders.show', $question));
        $this->assertPageHas('<option value="question" selected>', $html);
        $this->assertPageLacks('<option value="captured" selected>', $html);
        $this->assertPageLacks('<option value="" disabled selected>', $html);

        // „Eingereicht“ setzt nur die Abgabe: als Stand sichtbar, aber nicht wählbar.
        $submitted = $this->opportunity(ApplicationOpportunityStatus::Submitted);
        $this->assertPageHas(
            '<option value="" disabled selected>' . e(ApplicationOpportunityStatus::Submitted->label()) . '</option>',
            $this->page(route('tenders.show', $submitted)),
        );
    }

    public function test_working_status_accepts_exactly_the_manual_stages(): void {
        $opportunity = $this->opportunity(ApplicationOpportunityStatus::Question);

        // Den Stand erneut zu setzen bleibt erlaubt.
        $this->actingAs($this->admin)->post(route('tenders.status', $opportunity), ['status' => 'question'])
            ->assertSessionHas('success');
        foreach (['submitted', 'won', 'lost', 'withdrawn', 'archived', 'unbekannt'] as $rejected) {
            $this->actingAs($this->admin)->post(route('tenders.status', $opportunity), ['status' => $rejected])
                ->assertSessionHasErrors('status');
        }
        foreach (ApplicationOpportunityStatus::working() as $stage) {
            $this->actingAs($this->admin)->post(route('tenders.status', $opportunity), ['status' => $stage->value])
                ->assertSessionHas('success');
            $this->assertSame($stage, $opportunity->fresh()->status);
        }
    }

    public function test_requirement_checklist_reflects_the_stored_status(): void {
        $opportunity = $this->opportunity(ApplicationOpportunityStatus::InProgress);
        foreach ([[ApplicationRequirementStatus::Done, 'Referenzliste'], [ApplicationRequirementStatus::NotApplicable, 'Bürgschaft']] as $index => [$status, $label]) {
            $opportunity->requirements()->create([
                'organization_id' => $this->organization->id,
                'label' => $label,
                'kind' => 'proof',
                'required' => true,
                'status' => $status,
                'position' => $index + 1,
            ]);
        }

        $html = $this->page(route('tenders.show', $opportunity));
        $this->assertSame(1, substr_count($html, '<option value="done" selected>'));
        $this->assertSame(1, substr_count($html, '<option value="not_applicable" selected>'));
        $this->assertPageLacks('<option value="open" selected>', $html);
        // Nur die entfallene Anforderung ist durchgestrichen.
        $this->assertSame(1, substr_count($html, 'line-through opacity-60'));
    }

    public function test_requirement_update_accepts_every_status_and_nothing_else(): void {
        $opportunity = $this->opportunity(ApplicationOpportunityStatus::InProgress);
        $requirement = $opportunity->requirements()->create([
            'organization_id' => $this->organization->id, 'label' => 'Referenzliste', 'kind' => 'proof', 'required' => true, 'position' => 1,
        ]);

        foreach (ApplicationRequirementStatus::cases() as $status) {
            $this->actingAs($this->admin)->put(route('tenders.requirements.update', [$opportunity, $requirement]), ['status' => $status->value])
                ->assertSessionHasNoErrors();
            $this->assertSame($status, $requirement->fresh()->status);
        }
        $this->actingAs($this->admin)->put(route('tenders.requirements.update', [$opportunity, $requirement]), ['status' => 'erledigt'])
            ->assertSessionHasErrors('status');
    }

    public function test_submission_snapshot_keeps_the_raw_status_value(): void {
        $opportunity = $this->opportunity(ApplicationOpportunityStatus::InProgress);
        $opportunity->requirements()->create([
            'organization_id' => $this->organization->id, 'label' => 'Referenzliste', 'kind' => 'proof', 'required' => true,
            'status' => ApplicationRequirementStatus::Done, 'position' => 1,
        ]);

        $submission = app(TenderService::class)->submit($opportunity, 'portal', null, $this->admin);

        $this->assertSame('done', $submission->fresh()->snapshot['requirements'][0]['status']);
        $this->assertSame(hash('sha256', (string) json_encode($submission->fresh()->snapshot)), $submission->sha256);
        // Die erneute Abgabe einer eingereichten Akte bleibt möglich, eine entschiedene nicht.
        $this->assertSame(2, app(TenderService::class)->submit($opportunity->refresh(), 'portal', null, $this->admin)->version);
        app(TenderService::class)->decide($opportunity->refresh(), 'won', null, $this->admin);
        $this->expectException(\RuntimeException::class);
        app(TenderService::class)->submit($opportunity->refresh(), 'portal', null, $this->admin);
    }

    /** Die Farbe kam aus einem `match` über Zeichenketten am Modell — mit dem Cast fiele jede Zeile auf „ghost“. */
    private function assertBadge(string $tone, string $label, string $html): void {
        $this->assertSame(1, preg_match('/badge-' . $tone . '"[^>]*>\s*' . preg_quote(e($label), '/') . '\s*</u', $html), "Kein Abzeichen „{$label}“ im Ton {$tone}.");
    }

    public function test_tender_list_colours_the_badge_by_status(): void {
        $this->opportunity(ApplicationOpportunityStatus::Won);

        $this->assertBadge('success', ApplicationOpportunityStatus::Won->label(), $this->page(route('tenders.index')));
    }

    public function test_application_list_colours_the_badge_by_status(): void {
        $this->application(JobApplicationStatus::Accepted);

        $this->assertBadge('success', JobApplicationStatus::Accepted->label(), $this->page(route('recruiting.applications.index')));
    }

    public function test_requisition_list_colours_the_badge_by_status(): void {
        JobRequisition::query()->create(['organization_id' => $this->organization->id, 'title' => 'Servicetechniker:in', 'status' => JobRequisitionStatus::OnHold]);

        $this->assertBadge('warning', JobRequisitionStatus::OnHold->label(), $this->page(route('recruiting.requisitions.index')));
    }

    // ── Vertragsverhandlung ──────────────────────────────────────────────

    public function test_negotiation_marks_open_blockers_and_offers_their_decision(): void {
        $opportunity = $this->opportunity(ApplicationOpportunityStatus::Won);
        $service = app(ContractNegotiationService::class);
        $negotiation = $service->open($opportunity, 'Rahmenvertrag 2027', null, $this->admin);
        $service->addReviewItem($negotiation, 'Haftungsklausel', 'blocker', null, $this->admin);
        $service->addReviewItem($negotiation, 'Zahlungsziel', 'blocker', null, $this->admin);
        [$open, $resolved] = $negotiation->reviewItems()->orderBy('id')->get()->all();
        $service->resolveReviewItem($negotiation, (int) $resolved->id, 'accepted', null, $this->admin);
        $this->assertSame(ApplicationContractReviewStatus::Accepted, $resolved->fresh()->status);
        $this->assertTrue($negotiation->hasOpenBlockers());

        $html = $this->page(route('tenders.show', $opportunity));

        $this->assertPageHas(route('applications.negotiations.reviews.resolve', [$negotiation, $open->sqid]), $html);
        $this->assertPageLacks(route('applications.negotiations.reviews.resolve', [$negotiation, $resolved->sqid]), $html);
        // Rot ist nur der offene Blocker, durchgestrichen nur der entschiedene Punkt.
        $this->assertSame(1, preg_match_all('/badge-error"[^>]*>\s*' . preg_quote(e(__('values.blocker')), '/') . '/u', $html));
        $this->assertSame(1, substr_count($html, 'line-through opacity-60'));
    }

    // ── Bewerbungsakte ───────────────────────────────────────────────────

    public function test_application_file_offers_pipeline_actions_only_before_the_decision(): void {
        $screened = $this->application(JobApplicationStatus::Screened);
        $planned = $screened->interviews()->create([
            'organization_id' => $this->organization->id, 'scheduled_at' => now()->addDay(), 'mode' => 'onsite', 'status' => JobApplicationInterviewStatus::Planned,
        ]);
        $done = $screened->interviews()->create([
            'organization_id' => $this->organization->id, 'scheduled_at' => now()->subDay(), 'mode' => 'phone', 'status' => JobApplicationInterviewStatus::Done,
        ]);

        $html = $this->page(route('recruiting.applications.show', $screened));
        $this->assertPageHas(route('recruiting.applications.status', $screened), $html);
        $this->assertPageHas('<option value="screened" selected>', $html);
        $this->assertPageHas(route('recruiting.applications.decide', $screened), $html);
        // Nur das geplante Gespräch lässt sich als geführt dokumentieren.
        $this->assertPageHas(route('recruiting.applications.interviews.complete', [$screened, $planned]), $html);
        $this->assertPageLacks(route('recruiting.applications.interviews.complete', [$screened, $done]), $html);

        $rejected = $this->application(JobApplicationStatus::Rejected);
        $html = $this->page(route('recruiting.applications.show', $rejected));
        $this->assertPageLacks(route('recruiting.applications.status', $rejected), $html);
        $this->assertPageLacks(route('recruiting.applications.decide', $rejected), $html);
    }

    public function test_application_status_update_is_limited_to_the_pipeline(): void {
        $application = $this->application(JobApplicationStatus::Received);

        foreach (JobApplicationStatus::working() as $stage) {
            $this->actingAs($this->admin)->post(route('recruiting.applications.status', $application), ['status' => $stage->value])
                ->assertSessionHas('success');
            $this->assertSame($stage, $application->fresh()->status);
        }
        foreach (['received', 'offer', 'accepted', 'rejected', 'withdrawn', 'talent_pool', 'deleted', 'unbekannt'] as $rejected) {
            $this->actingAs($this->admin)->post(route('recruiting.applications.status', $application), ['status' => $rejected])
                ->assertSessionHasErrors('status');
        }

        $decided = $this->application(JobApplicationStatus::Rejected);
        $this->actingAs($this->admin)->post(route('recruiting.applications.status', $decided), ['status' => 'screened'])
            ->assertSessionHas('error');
        $this->assertSame(JobApplicationStatus::Rejected, $decided->fresh()->status);
    }

    public function test_onboarding_and_negotiation_follow_the_application_status(): void {
        $offer = $this->application(JobApplicationStatus::Offer);
        $html = $this->page(route('recruiting.applications.show', $offer));
        $this->assertPageHas(route('recruiting.applications.negotiations.store', $offer), $html);
        $this->assertPageLacks(route('recruiting.applications.draft.store', $offer), $html);

        $screened = $this->application(JobApplicationStatus::Screened);
        $this->assertPageLacks(route('recruiting.applications.negotiations.store', $screened), $this->page(route('recruiting.applications.show', $screened)));

        $accepted = $this->application(JobApplicationStatus::Accepted, ['email' => 'kim@example.test']);
        $html = $this->page(route('recruiting.applications.show', $accepted));
        $this->assertPageHas(route('recruiting.applications.draft.store', $accepted), $html);
        $this->assertPageHas(route('recruiting.applications.negotiations.store', $accepted), $html);

        // Einladen lässt sich nur der Entwurf — danach verschwindet die Aktion.
        $draft = app(RecruitingService::class)->createEmployeeDraft($accepted, $this->admin);
        $invite = route('recruiting.applications.draft.invite', [$accepted, $draft]);
        $this->assertPageHas($invite, $this->page(route('recruiting.applications.show', $accepted)));
        app(RecruitingService::class)->inviteFromDraft($draft, $this->admin);
        $this->assertSame(EmployeeDraftStatus::Invited, $draft->fresh()->status);
        $this->assertPageLacks($invite, $this->page(route('recruiting.applications.show', $accepted)));
    }

    // ── Stelle und Veröffentlichungen ────────────────────────────────────

    public function test_requisition_file_offers_pause_only_for_published_postings_and_close_until_closed(): void {
        $requisition = JobRequisition::query()->create(['organization_id' => $this->organization->id, 'title' => 'Servicetechniker:in', 'status' => JobRequisitionStatus::Open]);
        $career = JobPosting::query()->create([
            'organization_id' => $this->organization->id, 'job_requisition_id' => $requisition->id, 'channel' => 'website',
            'status' => JobPostingStatus::Published, 'public_slug' => 'servicetechniker', 'public_title' => 'Servicetechniker:in',
        ]);
        $closed = JobPosting::query()->create([
            'organization_id' => $this->organization->id, 'job_requisition_id' => $requisition->id, 'channel' => 'portal', 'status' => JobPostingStatus::Closed,
        ]);
        $pause = route('recruiting.requisitions.career.pause', $requisition);
        // Veröffentlicht wird über den Dialog: der Knopf ist ein Link, kein Formular.
        $publish = 'href="' . route('recruiting.requisitions.career.edit', $requisition) . '"';

        $html = $this->page(route('recruiting.requisitions.show', $requisition));
        $this->assertPageHas('<option value="open" selected>', $html);
        $this->assertPageHas('action="' . $pause . '"', $html);
        $this->assertPageLacks($publish, $html);
        $this->assertPageHas(route('recruiting.requisitions.postings.close', [$requisition, $career]), $html);
        $this->assertPageLacks(route('recruiting.requisitions.postings.close', [$requisition, $closed]), $html);

        $this->actingAs($this->admin)->post($pause)->assertRedirect();
        $this->assertSame(JobPostingStatus::Paused, $career->fresh()->status);
        $html = $this->page(route('recruiting.requisitions.show', $requisition));
        $this->assertPageHas($publish, $html);
        $this->assertPageLacks('action="' . $pause . '"', $html);
        // Auch eine pausierte Veröffentlichung lässt sich schließen.
        $this->assertPageHas(route('recruiting.requisitions.postings.close', [$requisition, $career]), $html);

        // Pausieren greift nur auf eine freigegebene Veröffentlichung.
        $career->update(['status' => JobPostingStatus::Closed]);
        $this->actingAs($this->admin)->post($pause)->assertRedirect();
        $this->assertSame(JobPostingStatus::Closed, $career->fresh()->status);
    }

    public function test_requisition_status_accepts_every_status_and_nothing_else(): void {
        $requisition = JobRequisition::query()->create(['organization_id' => $this->organization->id, 'title' => 'Servicetechniker:in', 'status' => JobRequisitionStatus::Draft]);

        foreach (JobRequisitionStatus::cases() as $status) {
            $this->actingAs($this->admin)->post(route('recruiting.requisitions.status', $requisition), ['status' => $status->value])
                ->assertSessionHasNoErrors();
            $this->assertSame($status, $requisition->fresh()->status);
        }
        $this->actingAs($this->admin)->post(route('recruiting.requisitions.status', $requisition), ['status' => 'published'])
            ->assertSessionHasErrors('status');
    }

    // ── Radar ────────────────────────────────────────────────────────────

    public function test_radar_inbox_offers_the_action_that_fits_the_match_state(): void {
        $notice = fn (): TenderNotice => TenderNotice::query()->create([
            'notice_id' => 'n-' . fake()->unique()->numerify('######'),
            'version' => '1',
            'title' => 'Neubau Kita — Rohbauarbeiten',
            'buyer_name' => 'Stadt Bonn',
            'published_on' => now()->subDay()->toDateString(),
        ]);
        $match = fn (TenderNoticeMatchState $state, ?int $opportunityId = null): TenderNoticeMatch => TenderNoticeMatch::query()->create([
            'organization_id' => $this->organization->id,
            'tender_notice_id' => $notice()->id,
            'state' => $state,
            'application_opportunity_id' => $opportunityId,
        ]);
        $opportunity = $this->opportunity(ApplicationOpportunityStatus::Captured);
        $converted = $match(TenderNoticeMatchState::Converted, $opportunity->id);
        $muted = $match(TenderNoticeMatchState::Muted);
        $new = $match(TenderNoticeMatchState::New);

        $html = $this->page(route('tender-radar.index', ['state' => 'converted']));
        $this->assertPageHas(route('tenders.show', $opportunity), $html);
        $this->assertPageLacks(route('tender-radar.convert', $converted), $html);

        $html = $this->page(route('tender-radar.index', ['state' => 'muted']));
        $this->assertPageHas(route('tender-radar.restore', $muted), $html);
        $this->assertPageLacks(route('tender-radar.convert', $muted), $html);

        // Unbekannter Filterwert fällt auf „neu“ zurück.
        $html = $this->page(route('tender-radar.index', ['state' => 'unbekannt']));
        $this->assertPageHas(route('tender-radar.convert', $new), $html);
        $this->assertPageHas(route('tender-radar.mute', $new), $html);
        $this->assertPageHas('<option value="new" selected>', $html);
    }

    // ── Bericht und Auskunft ─────────────────────────────────────────────

    public function test_report_measures_the_days_until_acceptance(): void {
        $this->travelTo('2026-10-05 12:00:00');
        $this->application(JobApplicationStatus::Accepted, ['received_at' => now()->subDays(10)]);
        $this->application(JobApplicationStatus::Rejected, ['received_at' => now()->subDays(30)]);

        $this->actingAs($this->admin)
            ->get(route('applications.report', ['from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()
            ->assertViewHas('recruiting', fn (array $recruiting): bool => $recruiting['avg_days_to_accept'] === 10.0
                && $recruiting['pipeline'] === ['accepted' => 1, 'rejected' => 1])
            ->assertViewHas('funnelSeries', [
                ['x' => JobApplicationStatus::Accepted->label(), 'y' => 1],
                ['x' => JobApplicationStatus::Rejected->label(), 'y' => 1],
            ]);

        // Der Statusfilter nimmt den gespeicherten Wert.
        $this->actingAs($this->admin)
            ->get(route('applications.report', ['from' => '2026-10-01', 'to' => '2026-10-31', 'status' => 'rejected']))
            ->assertOk()
            ->assertViewHas('recruiting', fn (array $recruiting): bool => $recruiting['pipeline'] === ['rejected' => 1]);
    }

    public function test_subject_access_shows_the_status_label_and_the_export_the_raw_value(): void {
        $application = $this->application(JobApplicationStatus::InterviewPlanned);
        $application->interviews()->create([
            'organization_id' => $this->organization->id, 'scheduled_at' => now()->addDay(), 'mode' => 'onsite', 'status' => JobApplicationInterviewStatus::Planned,
        ]);

        // Auskunftspaket (Art. 15): lesbar. Vor dem Enum stand hier der Rohwert.
        $master = (new JobApplicationMasterDataSection)->build($application);
        $this->assertSame(JobApplicationStatus::InterviewPlanned->label(), $master['fields']['status']['value']);
        $records = (new ApplicationRecordsSection)->build($application);
        $interviews = collect($records['families'])->firstWhere('table', 'job_application_interviews');
        $this->assertSame(JobApplicationInterviewStatus::Planned->label(), $interviews['rows'][0][2]);

        // JSON-Auskunft der Akte: maschinenlesbar, also der gespeicherte Wert.
        $export = app(RecruitingService::class)->export($application->load(['requisition', 'interviews', 'documents']));
        $this->assertSame('interview_planned', $export['status']);
        $this->assertSame('planned', $export['interviews'][0]['status']);
    }
}
