<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityListsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sustainability;

use App\Enums\Sustainability\{SustainabilityAssessmentStatus, SustainabilityMeasureStatus};
use App\Enums\User\Permission as P;
use App\Models\Platform\{Organization, User};
use App\Models\Sustainability\{SustainabilityAssessment, SustainabilityMeasure};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Entscheidung 2026-10-05: Bewertungen und Maßnahmen haben je eine blätternde
 * Liste. Vorher lud das Dashboard zehn Einträge, zählte dieses Fenster und bot
 * die Statuspflege nur dort an — die elfte Maßnahme war nicht mehr erreichbar.
 */
final class SustainabilityListsTest extends TestCase {
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

    /** @return list<SustainabilityAssessment> älteste zuerst */
    private function assessments(int $count, ?Organization $organization = null): array {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = SustainabilityAssessment::query()->create([
                'organization_id' => ($organization ?? $this->organization)->id,
                'subject_label' => sprintf('Gerät %02d', $i),
                'version' => 1,
                'status' => $i % 2 === 0 ? SustainabilityAssessmentStatus::Final : SustainabilityAssessmentStatus::Draft,
            ]);
        }

        return $rows;
    }

    /** @return list<SustainabilityMeasure> älteste zuerst */
    private function measures(int $count, SustainabilityMeasureStatus $status = SustainabilityMeasureStatus::Proposed, string $prefix = 'Maßnahme'): array {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = SustainabilityMeasure::query()->create([
                'organization_id' => $this->organization->id,
                'title' => sprintf('%s %02d', $prefix, $i),
                'effort' => 'low',
                'status' => $status,
                'created_by' => $this->admin->id,
            ]);
        }

        return $rows;
    }

    /** @return list<int> */
    private function ids(string $route, string $key, array $query = []): array {
        return $this->actingAs($this->admin)->get(route($route, $query))->assertOk()->viewData($key)->pluck('id')->all();
    }

    public function test_assessment_list_pages_through_every_assessment(): void {
        $rows = $this->assessments(27);
        $this->assessments(1, Organization::factory()->create());
        $newestFirst = array_reverse(array_map(static fn (SustainabilityAssessment $a): int => $a->id, $rows));

        $first = $this->actingAs($this->admin)->get(route('sustainability.assessments.index'))->assertOk();
        $this->assertSame(27, $first->viewData('assessments')->total());
        $this->assertSame(array_slice($newestFirst, 0, 25), $first->viewData('assessments')->pluck('id')->all());
        $first->assertSee('Gerät 27')->assertDontSee('Gerät 02')
            ->assertSee(route('sustainability.assessments.show', $rows[26]), false);

        $this->assertSame(array_slice($newestFirst, 25), $this->ids('sustainability.assessments.index', 'assessments', ['page' => 2]));

        // Sortierung über den Index-Parser: bekannter Schlüssel greift, unbekannter fällt auf den Standard.
        $bySubject = $this->ids('sustainability.assessments.index', 'assessments', ['sort' => 'subject', 'dir' => 'asc']);
        $this->assertSame([$rows[0]->id, $rows[1]->id], array_slice($bySubject, 0, 2));
        $this->assertSame(array_slice($newestFirst, 0, 25), $this->ids('sustainability.assessments.index', 'assessments', ['sort' => 'snapshot', 'dir' => 'asc']));
    }

    public function test_measure_list_pages_and_filters_by_status(): void {
        $proposed = $this->measures(26);
        $done = $this->measures(2, SustainabilityMeasureStatus::Done, 'Erledigt');
        $running = $this->measures(1, SustainabilityMeasureStatus::InProgress, 'Laufend');
        $all = array_reverse(array_map(static fn (SustainabilityMeasure $m): int => $m->id, [...$proposed, ...$done, ...$running]));

        $first = $this->actingAs($this->admin)->get(route('sustainability.measures.index'))->assertOk();
        $this->assertSame(29, $first->viewData('measures')->total());
        $this->assertSame(array_slice($all, 0, 25), $first->viewData('measures')->pluck('id')->all());
        $this->assertSame(array_slice($all, 25), $this->ids('sustainability.measures.index', 'measures', ['page' => 2]));

        $ids = static fn (array $rows): array => array_reverse(array_map(static fn (SustainabilityMeasure $m): int => $m->id, $rows));
        $this->assertSame($ids($done), $this->ids('sustainability.measures.index', 'measures', ['status' => 'done']));
        // „Offen" fasst zusammen, was die Kennzahl des Dashboards zählt.
        $open = $this->actingAs($this->admin)->get(route('sustainability.measures.index', ['status' => 'open']))->assertOk();
        $this->assertSame(27, $open->viewData('measures')->total());
        $this->assertSame($running[0]->id, $open->viewData('measures')->first()->id);
        // Unbekannter Status filtert nicht.
        $bogus = $this->actingAs($this->admin)->get(route('sustainability.measures.index', ['status' => 'erledigt']))->assertOk();
        $this->assertSame(29, $bogus->viewData('measures')->total());
        $this->assertSame('', $bogus->viewData('status'));
    }

    public function test_dashboard_shows_the_latest_ten_and_counts_everything(): void {
        $assessments = $this->assessments(12);
        $measures = $this->measures(13);

        $response = $this->actingAs($this->admin)->get(route('sustainability.index'))->assertOk()
            ->assertViewHas('assessmentCount', 12)
            ->assertViewHas('measureCount', 13)
            ->assertViewMissing('records');

        $this->assertCount(10, $response->viewData('assessments'));
        $this->assertCount(10, $response->viewData('measures'));
        $this->assertSame($assessments[11]->id, $response->viewData('assessments')->first()->id);
        $html = (string) $response->getContent();
        // Kartenzähler: die ganze Menge, nicht das Fenster.
        $this->assertMatchesRegularExpression('/' . preg_quote(e((string) __('Bewertungen (versioniert)')), '/') . '<\/span>\s*<span[^>]*>\(12\)/', $html);
        $this->assertMatchesRegularExpression('/' . preg_quote(e((string) __('Maßnahmenregister')), '/') . '<\/span>\s*<span[^>]*>\(13\)/', $html);
        $response->assertSee('href="' . route('sustainability.assessments.index') . '"', false)
            ->assertSee('href="' . route('sustainability.measures.index') . '"', false)
            // Die ältesten drei Maßnahmen stehen nicht mehr im Dashboard.
            ->assertDontSee(route('sustainability.measures.update', $measures[0]), false)
            ->assertSee(route('sustainability.measures.update', $measures[12]), false);
    }

    public function test_an_old_measure_is_maintained_from_the_list_and_returns_to_the_filtered_list(): void {
        $measures = $this->measures(12);
        $old = $measures[0];
        $filters = ['dir' => 'asc', 'sort' => 'title', 'status' => 'proposed'];

        $list = $this->actingAs($this->admin)->get(route('sustainability.measures.index', $filters))->assertOk();
        $list->assertSee('action="' . route('sustainability.measures.update', $old) . '"', false)
            ->assertSee('name="origin" value="list"', false);

        // Der gemerkte Listenzustand zählt, nicht die Herkunftsseite der Anfrage.
        $this->actingAs($this->admin)->from(route('sustainability.index'))
            ->put(route('sustainability.measures.update', $old), ['status' => 'done', 'origin' => 'list'])
            ->assertRedirect(route('sustainability.measures.index', $filters))
            ->assertSessionHas('status');
        $this->assertSame(SustainabilityMeasureStatus::Done, $old->fresh()->status);

        // Die Wirksamkeitsregel meldet ihren Fehler ebenfalls in der Liste.
        $this->actingAs($this->admin)->from(route('sustainability.index'))
            ->put(route('sustainability.measures.update', $measures[1]), ['status' => 'approved', 'effectiveness' => 'partly', 'origin' => 'list'])
            ->assertRedirect(route('sustainability.measures.index', $filters))
            ->assertSessionHas('error');
        $this->assertSame(SustainabilityMeasureStatus::Proposed, $measures[1]->fresh()->status);

        // Aus dem Dashboard führt die Statuspflege dorthin zurück.
        $this->actingAs($this->admin)->from(route('sustainability.index'))
            ->put(route('sustainability.measures.update', $measures[11]), ['status' => 'approved'])
            ->assertRedirect(route('sustainability.index'));
        $this->assertSame(SustainabilityMeasureStatus::Approved, $measures[11]->fresh()->status);
    }

    public function test_lists_follow_the_rights_of_the_dashboard(): void {
        $measure = $this->measures(1)[0];
        $this->assessments(1);
        $stranger = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($stranger)->get(route('sustainability.assessments.index'))->assertForbidden();
        $this->actingAs($stranger)->get(route('sustainability.measures.index'))->assertForbidden();

        // Lesen ohne Pflegerecht: Liste ja, Statuspflege nein.
        $reader = User::factory()->create(['organization_id' => $this->organization->id]);
        SpatiePermission::findOrCreate(P::SustainabilityViewAny->value, 'web');
        $reader->givePermissionTo(P::SustainabilityViewAny->value);
        $this->actingAs($reader)->get(route('sustainability.assessments.index'))->assertOk()->assertSee('Gerät 01');
        $this->actingAs($reader)->get(route('sustainability.measures.index'))->assertOk()
            ->assertSee('Maßnahme 01')
            ->assertDontSee(route('sustainability.measures.update', $measure), false);
        $this->actingAs($reader)->put(route('sustainability.measures.update', $measure), ['status' => 'done', 'origin' => 'list'])->assertForbidden();
        $this->assertSame(SustainabilityMeasureStatus::Proposed, $measure->fresh()->status);
    }
}
