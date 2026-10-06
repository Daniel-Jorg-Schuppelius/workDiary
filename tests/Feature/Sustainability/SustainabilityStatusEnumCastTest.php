<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityStatusEnumCastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sustainability;

use App\Enums\Sustainability\{SustainabilityAssessmentStatus, SustainabilityMeasureStatus};
use App\Models\Platform\User;
use App\Models\Sustainability\{SustainabilityAssessment, SustainabilityCriterion, SustainabilityMeasure};
use App\Policies\Sustainability\SustainabilityAssessmentPolicy;
use App\Services\Sustainability\SustainabilityAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k3-10 (Welle 7): ESG-Bewertung und
 * Verbesserungsmaßnahme führen ihren Status als Enum. Ein Vergleich gegen die
 * frühere Zeichenkette ist danach still falsch — eine finale Bewertung wäre
 * wieder bearbeitbar, die Statusauswahl der Maßnahme stünde immer auf
 * „Vorgeschlagen“.
 */
final class SustainabilityStatusEnumCastTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-10-05 12:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function assessment(SustainabilityAssessmentStatus $status, array $attributes = []): SustainabilityAssessment {
        return SustainabilityAssessment::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'subject_label' => 'Fuhrpark ' . $status->value,
            'version' => 1,
            'status' => $status,
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function measure(SustainabilityMeasureStatus $status, array $attributes = []): SustainabilityMeasure {
        return SustainabilityMeasure::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'title' => 'Maßnahme ' . $status->value,
            'effort' => 'low',
            'status' => $status,
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    /** Bewertung mit einem bewerteten Kriterium über den Dienst. */
    private function scoredDraft(string $label = 'Akkuschrauber'): SustainabilityAssessment {
        SustainabilityCriterion::query()->firstOrCreate(
            ['organization_id' => $this->organization->id, 'dimension' => 'environment', 'label' => 'Energieeffizienz'],
            ['weight' => 2, 'active' => true],
        );
        $assessment = app(SustainabilityAssessmentService::class)->createDraft($this->organization->id, null, null, $label, $this->admin);
        $assessment->items()->update(['score' => 4, 'data_quality' => 'measured']);

        return $assessment;
    }

    private function page(string $url): string {
        return (string) $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
    }

    private function assertPageHas(string $needle, string $html): void {
        $this->assertTrue(str_contains($html, $needle), "Auf der Seite fehlt: {$needle}");
    }

    private function assertPageLacks(string $needle, string $html): void {
        $this->assertFalse(str_contains($html, $needle), "Auf der Seite steht unerwartet: {$needle}");
    }

    private function assertBadge(string $label, int $times, string $html): void {
        $this->assertSame($times, preg_match_all('/badge[^>]*>\s*' . preg_quote(e($label), '/') . '\s*</u', $html), "Abzeichen „{$label}“");
    }

    // ── Dashboard ────────────────────────────────────────────────────────

    public function test_dashboard_counts_final_critical_assessments_and_open_measures(): void {
        $this->assessment(SustainabilityAssessmentStatus::Final, ['rating' => 'red', 'total_score' => '1.50']);
        $this->assessment(SustainabilityAssessmentStatus::Final, ['rating' => 'green', 'total_score' => '4.50']);
        $this->assessment(SustainabilityAssessmentStatus::Draft, ['rating' => 'red']);
        foreach (SustainabilityMeasureStatus::cases() as $status) {
            $this->measure($status);
        }

        $response = $this->actingAs($this->admin)->get(route('sustainability.index'))->assertOk()
            ->assertViewHas('critical', 1)
            ->assertViewHas('openMeasures', 3);
        $html = (string) $response->getContent();

        $this->assertBadge(SustainabilityAssessmentStatus::Final->label(), 2, $html);
        $this->assertBadge(SustainabilityAssessmentStatus::Draft->label(), 1, $html);
        foreach (SustainabilityMeasureStatus::cases() as $status) {
            $this->assertBadge($status->label(), 1, $html);
        }
    }

    public function test_measure_form_preselects_the_status_and_asks_for_effectiveness_after_completion(): void {
        $this->measure(SustainabilityMeasureStatus::InProgress);
        $this->measure(SustainabilityMeasureStatus::Done);
        $this->measure(SustainabilityMeasureStatus::Done, ['effectiveness' => 'effective']);

        $html = $this->page(route('sustainability.index'));

        $this->assertSame(1, substr_count($html, '<option value="in_progress" selected>' . e(SustainabilityMeasureStatus::InProgress->label()) . '</option>'));
        $this->assertSame(2, substr_count($html, '<option value="done" selected>'));
        $this->assertPageLacks('<option value="proposed" selected>', $html);
        // Nur die erledigte Maßnahme ohne Prüfung fragt nach der Wirksamkeit.
        $this->assertSame(1, substr_count($html, '<select name="effectiveness"'));
    }

    public function test_measure_update_accepts_every_status_and_nothing_else(): void {
        $measure = $this->measure(SustainabilityMeasureStatus::Approved);

        foreach (['erledigt', 'open', 'final', 'DONE'] as $rejected) {
            $this->actingAs($this->admin)->put(route('sustainability.measures.update', $measure), ['status' => $rejected])->assertSessionHasErrors('status');
        }
        $this->assertSame(SustainabilityMeasureStatus::Approved, $measure->fresh()->status);
        $this->assertSame(['proposed', 'approved', 'in_progress', 'done', 'discarded'], SustainabilityMeasureStatus::values());
        foreach (SustainabilityMeasureStatus::cases() as $status) {
            $this->actingAs($this->admin)->put(route('sustainability.measures.update', $measure), ['status' => $status->value])->assertSessionHasNoErrors();
            $this->assertSame($status, $measure->fresh()->status);
        }

        // Wirksamkeit erst nach der Umsetzung.
        $this->actingAs($this->admin)->put(route('sustainability.measures.update', $measure), ['status' => 'in_progress', 'effectiveness' => 'partly'])->assertSessionHas('error');
        $this->assertSame(SustainabilityMeasureStatus::Discarded, $measure->fresh()->status);
        $this->actingAs($this->admin)->put(route('sustainability.measures.update', $measure), ['status' => 'done', 'effectiveness' => 'partly'])->assertSessionHas('status');
        $this->assertSame('partly', $measure->fresh()->effectiveness);
    }

    public function test_new_measure_starts_as_proposed(): void {
        $this->actingAs($this->admin)->post(route('sustainability.measures.store'), ['title' => 'LED-Umrüstung', 'effort' => 'low'])->assertSessionHasNoErrors();

        $this->assertSame(SustainabilityMeasureStatus::Proposed, SustainabilityMeasure::query()->sole()->status);
    }

    // ── Bewertung ────────────────────────────────────────────────────────

    public function test_assessment_page_offers_what_fits_the_status(): void {
        $draft = $this->scoredDraft();
        $item = $draft->items()->sole();
        $other = $this->assessment(SustainabilityAssessmentStatus::Final, ['subject_label' => 'Altgerät']);

        $html = $this->page(route('sustainability.assessments.show', $draft));
        $this->assertPageHas('action="' . route('sustainability.assessments.finalize', $draft) . '"', $html);
        $this->assertPageLacks('action="' . route('sustainability.assessments.new-version', $draft) . '"', $html);
        $this->assertPageHas('action="' . route('sustainability.assessments.items.update', [$draft, $item]) . '"', $html);
        $this->assertPageHas(e('Altgerät (V1, ' . SustainabilityAssessmentStatus::Final->label() . ')'), $html);
        $this->assertBadge(SustainabilityAssessmentStatus::Draft->label(), 1, $html);

        app(SustainabilityAssessmentService::class)->finalize($draft, $this->admin);
        $html = $this->page(route('sustainability.assessments.show', $draft));
        $this->assertPageLacks('action="' . route('sustainability.assessments.finalize', $draft) . '"', $html);
        $this->assertPageHas('action="' . route('sustainability.assessments.new-version', $draft) . '"', $html);
        $this->assertPageLacks('action="' . route('sustainability.assessments.items.update', [$draft, $item]) . '"', $html);
        $this->assertBadge(SustainabilityAssessmentStatus::Final->label(), 1, $html);

        // In der Vergleichsauswahl einer anderen Bewertung steht der neue Stand.
        $this->assertPageHas(e('Akkuschrauber (V1, ' . SustainabilityAssessmentStatus::Final->label() . ')'), $this->page(route('sustainability.assessments.show', $other)));
    }

    public function test_finalizing_and_versioning_follow_the_status(): void {
        $assessment = $this->scoredDraft();
        $item = $assessment->items()->sole();
        $policy = new SustainabilityAssessmentPolicy;

        // Versioniert wird nur ein finaler Stand.
        $this->actingAs($this->admin)->post(route('sustainability.assessments.new-version', $assessment))->assertSessionHas('error');
        $this->assertTrue($policy->update($this->admin, $assessment->fresh()));

        $this->actingAs($this->admin)->post(route('sustainability.assessments.finalize', $assessment))->assertSessionHas('status');
        $final = $assessment->fresh();
        $this->assertSame(SustainabilityAssessmentStatus::Final, $final->status);
        $this->assertTrue($final->isFinal());
        $this->assertFalse($policy->update($this->admin, $final));

        // Final ist eingefroren: kein zweites Finalisieren, kein Bewerten.
        $this->actingAs($this->admin)->post(route('sustainability.assessments.finalize', $assessment))
            ->assertSessionHas('error', (string) __('Die Bewertung ist bereits final — Änderungen laufen über eine neue Version.'));
        $this->actingAs($this->admin)->put(route('sustainability.assessments.items.update', [$assessment, $item]), ['score' => 1, 'data_quality' => 'measured'])->assertForbidden();
        $this->assertSame(4, $item->fresh()->score);

        $this->actingAs($this->admin)->post(route('sustainability.assessments.new-version', $assessment))->assertSessionHas('status');
        $next = SustainabilityAssessment::query()->where('version', 2)->sole();
        $this->assertSame(SustainabilityAssessmentStatus::Draft, $next->status);
        $this->assertFalse($next->isFinal());
        $this->assertSame(SustainabilityAssessmentStatus::Final, $assessment->fresh()->status);
    }
}
