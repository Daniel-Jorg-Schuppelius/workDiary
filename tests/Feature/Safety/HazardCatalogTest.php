<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HazardCatalogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Safety;

use App\Enums\Safety\{HazardAssessmentStatus, SafetyEventKind, SafetyEventSeverity};
use App\Models\Platform\User;
use App\Models\Safety\{HazardAssessment, HazardCatalogItem, SafetyEvent};
use App\Services\Classification\BranchProfileInstaller;
use App\Services\Safety\HazardAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1002: Gefährdungskatalog, Anhänge an GBU und Unterweisung, Ereignis stößt die Überprüfung an. */
final class HazardCatalogTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $lead;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
        $this->lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    public function test_profile_seeds_the_catalog_and_hazards_are_taken_into_a_draft_assessment(): void {
        app(BranchProfileInstaller::class)->install($this->organization, 'bau-ausbau', $this->lead);
        app(BranchProfileInstaller::class)->install($this->organization, 'bau-ausbau', $this->lead, force: true);
        $this->assertSame(5, HazardCatalogItem::query()->count(), 'erneute Installation legt nichts doppelt an');
        $fall = HazardCatalogItem::query()->where('code', 'bau-ausbau/absturz')->sole();
        $this->assertSame('bau-ausbau', $fall->source_profile);

        $this->actingAs($this->lead)->post(route('safety.hazard-catalog.store'), [
            'category' => 'Organisation', 'hazard' => 'Alleinarbeit auf der Baustelle', 'severity' => '3', 'likelihood' => '2',
        ])->assertRedirect(route('safety.hazard-catalog.index'));
        $this->actingAs($this->lead)->get(route('safety.hazard-catalog.index'))->assertOk()->assertSee('Alleinarbeit auf der Baustelle');

        $assessment = app(HazardAssessmentService::class)->create($this->organization, $this->lead, ['area' => 'Rohbau']);
        $this->actingAs($this->lead)->get(route('safety.assessments.catalog.create', $assessment))->assertOk()->assertSee($fall->hazard);
        $this->actingAs($this->lead)->post(route('safety.assessments.catalog.store', $assessment), ['catalog_items' => [$fall->sqid]])->assertRedirect();
        $this->actingAs($this->lead)->post(route('safety.assessments.catalog.store', $assessment), ['catalog_items' => [$fall->sqid]])->assertRedirect();

        $items = $assessment->items()->get();
        $this->assertCount(1, $items, 'bereits enthaltene Gefährdung bleibt aus');
        $this->assertSame(15, $items[0]->risk_before);
        $this->assertSame($fall->measure, $items[0]->measure);
    }

    public function test_evidence_attaches_to_assessment_and_instruction(): void {
        $assessment = app(HazardAssessmentService::class)->create($this->organization, $this->lead, ['area' => 'Werkstatt']);
        $this->actingAs($this->lead)->post(route('attachments.store', ['type' => 'hazard-assessment', 'id' => $assessment->sqid]), [
            'file' => UploadedFile::fake()->create('begehung.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $this->assertSame(1, $assessment->attachments()->count());
        $this->actingAs($this->lead)->get(route('safety.assessments.show', $assessment))->assertOk()->assertSee('begehung.pdf');

        $field = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($field)->post(route('attachments.store', ['type' => 'hazard-assessment', 'id' => $assessment->sqid]), [
            'file' => UploadedFile::fake()->create('fremd.pdf', 20, 'application/pdf'),
        ])->assertForbidden();
    }

    public function test_safety_event_triggers_the_review_of_the_approved_assessment(): void {
        $service = app(HazardAssessmentService::class);
        $assessment = $service->create($this->organization, $this->lead, ['area' => 'Lager']);
        $service->addItem($assessment, ['hazard' => 'Stapler', 'severity_before' => 4, 'likelihood_before' => 3]);
        $service->transition($assessment, HazardAssessmentStatus::InReview, $this->lead);
        $assessment = $service->transition($assessment->refresh(), HazardAssessmentStatus::Approved, $this->lead);
        $this->travel(1)->hours();

        $this->actingAs($this->lead)->get(route('safety-events.create'))->assertOk()->assertSee($assessment->displayNo());
        $this->actingAs($this->lead)->post(route('safety-events.store'), [
            'kind' => SafetyEventKind::NearMiss->value, 'severity' => SafetyEventSeverity::Medium->value,
            'occurred_at' => now()->orgTz()->format('Y-m-d\TH:i'), 'description' => 'Stapler kippt fast', 'hazard_assessment' => $assessment->sqid,
        ])->assertRedirect();
        $event = SafetyEvent::query()->sole();
        $this->assertSame($assessment->id, $event->hazard_assessment_id);

        $this->assertCount(1, $assessment->refresh()->reviewTriggers());
        $this->actingAs($this->lead)->get(route('safety.assessments.show', $assessment))->assertOk()
            ->assertSee(__('safety.catalog.events.review_triggered'))->assertSee($event->displayNo());

        $draft = $service->create($this->organization, $this->lead, ['area' => 'Entwurf']);
        $this->actingAs($this->lead)->post(route('safety-events.store'), [
            'kind' => SafetyEventKind::NearMiss->value, 'severity' => SafetyEventSeverity::Low->value,
            'occurred_at' => now()->orgTz()->format('Y-m-d\TH:i'), 'description' => 'Nur freigegebene Stände', 'hazard_assessment' => $draft->sqid,
        ])->assertRedirect();
        $this->assertNull(SafetyEvent::query()->where('description', 'Nur freigegebene Stände')->sole()->hazard_assessment_id);
        $this->assertInstanceOf(HazardAssessment::class, $event->hazardAssessment);
    }
}
