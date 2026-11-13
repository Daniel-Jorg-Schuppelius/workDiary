<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntakeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Sales\{LeadIntakeDecision, LeadIntakeStage};
use App\Models\Notification\NotificationDispatchLog;
use App\Models\Platform\User;
use App\Models\Sales\{Lead, LeadIntake};
use App\Services\Classification\BranchProfileInstaller;
use App\Services\Demo\Contracts\DemoSeedContext;
use App\Services\Notification\DeadlineScans\DeadlineScanOptions;
use App\Services\Notification\NotificationDispatcher;
use App\Services\Sales\Intake\DeadlineScans\LeadIntakeRefreshScan;
use App\Services\Sales\Intake\Demo\LeadIntakeDemoBlock;
use App\Services\Sales\Intake\{IntakePackages, LeadIntakeService};
use App\Services\WorkQueue\WorkQueue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Feature\Sales\Concerns\BuildsLeadIntakes;
use Tests\TestCase;

/**
 * Aufnahmeakte am Lead (Feature 200, MVP-1641–1644): Öffnen, Bogen mit
 * Speichern je Feld (Feature 203), Stufen, Entscheidung, Aktualisierung als
 * neue Fassung mit Meldung und Arbeitsvorrat, Übersicht, Kachel, Cockpit,
 * Einstellungen, PDF, Branchenprofile und Demo.
 */
final class LeadIntakeTest extends TestCase {
    use BuildsLeadIntakes;
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $clerk;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-03-02 09:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = $this->orgAdmin();
        $this->clerk = $this->userWithRole('buchhaltung');
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_open_fill_and_move_through_the_stages(): void {
        $this->enableGwg();
        $lead = $this->makeLead(['responsible_user_id' => $this->clerk->id]);

        $this->actingAs($this->clerk)->get(route('leads.show', $lead))->assertOk()->assertSee(__('lead_intake.action.open'));
        $this->actingAs($this->clerk)->post(route('leads.intakes.store', $lead))->assertRedirect();
        $intake = LeadIntake::query()->where('lead_id', $lead->id)->sole();
        $this->assertSame('Laufende Buchhaltung', $intake->fieldDocumentValues()->get('request'), 'Anliegen aus dem Interesse des Leads.');
        $this->assertSame(['gwg'], array_map(static fn ($p): string => $p->key, $intake->packageSnapshots()));

        $this->actingAs($this->clerk)->get(route('leads.intakes.show', [$lead, $intake]))
            ->assertOk()
            ->assertSee(__('lead_intake.gwg.section.identification'))
            ->assertSee('data-autosave', false)
            ->assertSee('lead_intakes', false)
            ->assertDontSee(__('lead_intake.action.decide'));

        $this->actingAs($this->clerk)->patchJson(route('field-documents.autosave', ['type' => 'lead_intakes', 'document' => $intake->sqid, 'field' => 'interview_notes']), ['version' => 1, 'value' => 'Erstgespräch (Test)'])
            ->assertOk()->assertJsonPath('version', 2);
        $this->actingAs($this->clerk)->patchJson(route('field-documents.autosave', ['type' => 'lead_intakes', 'document' => $intake->sqid, 'field' => 'interview_notes']), ['version' => 1, 'value' => 'Veraltet'])
            ->assertStatus(409);
        $this->actingAs($this->clerk)->post(route('leads.intakes.save', [$lead, $intake]), ['values' => ['request' => 'Neues Anliegen (Test)', 'interview_notes' => 'Erstgespräch (Test)']])->assertSessionHasNoErrors();
        $this->assertSame('Neues Anliegen (Test)', $intake->refresh()->fieldDocumentValues()->get('request'));

        $this->actingAs($this->clerk)->post(route('leads.intakes.stage', [$lead, $intake]), ['stage' => 'interview'])->assertSessionHasNoErrors();
        $this->assertSame(LeadIntakeStage::Interview, $intake->refresh()->stage);
        $this->actingAs($this->clerk)->post(route('leads.intakes.stage', [$lead, $intake]), ['stage' => 'setup'])->assertSessionHas('error');

        // Entscheiden nur mit Recht: Buchhaltung darf ausfüllen, nicht entscheiden.
        $this->actingAs($this->clerk)->get(route('leads.intakes.decision', [$lead, $intake]))->assertForbidden();
        $this->actingAs($this->admin)->get(route('leads.intakes.decision', [$lead, $intake]).'?dialog=1')->assertOk()->assertSee(__('lead_intake.decision.legend'));
        $this->actingAs($this->admin)->post(route('leads.intakes.decide', [$lead, $intake]), ['decision' => 'accepted', 'reason' => 'Test'])
            ->assertSessionHasErrors(['gwg__party_kind']);
    }

    public function test_index_widget_cockpit_and_settings(): void {
        $partner = $this->userWithRole('geschaeftsfuehrung');
        $lead = $this->makeLead();
        $this->enableGwg();
        $intake = $this->openIntake($lead, $this->admin);

        $this->actingAs($this->clerk)->get(route('leads.intakes.index'))->assertOk()->assertSee($lead->displayName())->assertDontSee(__('lead_intake.settings.title'));
        $this->actingAs($this->clerk)->get(route('leads.intakes.index', ['stage' => 'decision']))->assertOk()->assertDontSee($lead->displayName());
        $this->actingAs($this->clerk)->get(route('leads.show', $lead))->assertOk()->assertSee(__('lead_intake.revision', ['revision' => 1]));
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
        $this->assertTrue(app(\App\Dashboard\Widgets\LeadIntakesWidget::class)->availableFor($this->clerk));

        $this->actingAs($partner)->get(route('leads.intakes.settings.edit').'?dialog=1')->assertOk()->assertSee(__('lead_intake.gwg.label'));
        $this->actingAs($this->clerk)->get(route('leads.intakes.settings.edit'))->assertForbidden();
        $this->actingAs($partner)->post(route('leads.intakes.settings.update'), [
            'packages' => [],
            'refresh_months' => ['low' => 24, 'normal' => 12, 'high' => 6],
            'second_review' => '1',
        ])->assertSessionHasNoErrors();
        $packages = app(IntakePackages::class);
        $this->assertSame([], $packages->enabledKeys($this->organization->refresh()));
        $this->assertSame(['low' => 24, 'normal' => 12, 'high' => 6], $packages->riskRefreshMonths($this->organization));
        $this->assertTrue($packages->secondReview($this->organization));
        // Die eingefrorene Akte behält ihr Paket.
        $this->assertTrue($intake->refresh()->requiresDecision());
    }

    /** MVP-1643: Aktualisierung als neue Fassung, Meldung der zuständigen Person, Arbeitsvorrat. */
    public function test_refresh_creates_a_new_version_with_notification_and_work_item(): void {
        $this->enableGwg();
        $lead = $this->makeLead(['responsible_user_id' => $this->clerk->id]);
        $intake = $this->fill($this->openIntake($lead, $this->admin), $this->gwgValues());
        app(LeadIntakeService::class)->decide($intake, LeadIntakeDecision::Accepted, 'Annahme (Test).', $this->admin);
        app(\App\Services\Sales\LeadService::class)->convert($lead->refresh(), $this->admin);
        $intake->refresh();
        $this->assertSame('2027-03-02', $intake->refresh_due_on?->toDateString());

        Carbon::setTestNow('2027-02-20 08:00:00');
        $scan = app(LeadIntakeRefreshScan::class);
        $this->assertSame(1, $scan->run(app(NotificationDispatcher::class), new DeadlineScanOptions(7, 30)));
        $this->assertSame(1, NotificationDispatchLog::query()->where('event', NotificationEvent::LeadIntakeRefreshDue->value)->count());
        $this->assertSame(0, $scan->run(app(NotificationDispatcher::class), new DeadlineScanOptions(7, 30)), 'nur einmal je Frist');

        $queue = app(WorkQueue::class);
        $queue->forget($this->clerk, $this->organization);
        $items = collect($queue->itemsFor($this->clerk, $this->organization))->where('source', 'lead_intake');
        $this->assertCount(1, $items);

        $this->actingAs($this->clerk)->post(route('leads.intakes.refresh', [$lead, $intake]))->assertRedirect();
        $new = LeadIntake::query()->where('lead_id', $lead->id)->where('revision', 2)->sole();
        $this->assertSame($intake->id, $new->supersedes_id);
        $this->assertNotNull($intake->refresh()->superseded_at);
        $this->assertSame('Erika', $new->fieldDocumentValues()->get('gwg__first_name'), 'Angaben werden übernommen.');
        $this->assertSame(LeadIntakeStage::Interview, $new->stage);

        // Nach der Umwandlung endet die neue Fassung mit der Annahme direkt in „eingerichtet“.
        app(LeadIntakeService::class)->decide($new, LeadIntakeDecision::Accepted, 'Re-Identifizierung (Test).', $this->admin);
        $this->assertSame(LeadIntakeStage::Completed, $new->refresh()->stage);
        $this->assertSame('2028-02-20', $new->refresh_due_on?->toDateString());
        $this->actingAs($this->clerk)->get(route('leads.intakes.show', [$lead, $new]))->assertOk()->assertSee(__('lead_intake.versions.title'));
    }

    public function test_pdf_download(): void {
        $this->enableGwg();
        $lead = $this->makeLead();
        $intake = $this->fill($this->openIntake($lead, $this->admin), $this->gwgValues());

        $response = $this->actingAs($this->clerk)->get(route('leads.intakes.pdf', [$lead, $intake]));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('aufnahme-gwg-', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    /** Branchenprofile: Steuerberatung schaltet GwG ein, Pflege die Erstaufnahme, IT übernimmt B2B ausgeschaltet. */
    public function test_branch_profiles_install_their_packages(): void {
        $installer = app(BranchProfileInstaller::class);
        $packages = app(IntakePackages::class);

        $installer->install($this->organization, 'steuerberater', $this->admin);
        $this->assertContains('gwg', $packages->enabledKeys($this->organization->refresh()));
        $this->assertTrue($packages->requiresDecision($this->organization));

        $installer->install($this->organization->refresh(), 'pflege', $this->admin);
        $this->assertContains('care_admission', $packages->enabledKeys($this->organization->refresh()));

        $installer->install($this->organization->refresh(), 'it', $this->admin);
        $this->organization->refresh();
        $this->assertArrayHasKey('b2b_new_customer', $packages->available($this->organization));
        $this->assertNotContains('b2b_new_customer', $packages->enabledKeys($this->organization));

        // Ergänzt nur: ein abgeschaltetes Paket bleibt bei erneuter Installation aus.
        $packages->setEnabled($this->organization, ['care_admission']);
        $installer->install($this->organization->refresh(), 'it', $this->admin);
        $this->assertSame(['care_admission'], $packages->enabledKeys($this->organization->refresh()));
    }

    public function test_demo_block_seeds_an_intake_with_invented_data(): void {
        $this->enableGwg();
        $lead = $this->makeLead(['company' => LeadIntakeDemoBlock::DEMO_LEAD]);
        $context = new DemoSeedContext($this->organization, $this->admin, collect([$this->admin]), [], null, null, null);

        $this->assertSame(['lead_intakes' => 1], app(LeadIntakeDemoBlock::class)->seed($context));
        $intake = LeadIntake::query()->where('lead_id', $lead->id)->sole();
        $this->assertSame('legal', $intake->fieldDocumentValues()->get('gwg__party_kind'));
        $this->assertSame(LeadIntakeStage::Documents, $intake->stage);
        $this->assertSame(['lead_intakes' => 0], app(LeadIntakeDemoBlock::class)->seed($context), 'kein zweites Mal');

        app(LeadIntakeDemoBlock::class)->purge($this->organization);
        $this->assertSame(0, LeadIntake::query()->count());
        $this->assertInstanceOf(Lead::class, $lead->fresh());
    }
}
