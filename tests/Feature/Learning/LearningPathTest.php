<?php
/*
 * Created on   : Sat Aug 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningPathTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\LearningEnrollmentSource;
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningPath, LearningPathItem};
use App\Models\User;
use App\Services\Learning\{LearningCourseService, LearningPathService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lernpfade (Feature 149, MVP-745).
 *
 * Ein Lernpfad ist eine **Reihenfolge von Kursen mit Fristen** — die
 * Einarbeitung, nicht ein zweiter Pflichtkatalog. Zugewiesen wird über die
 * reguläre Einschreibung; ein eigener Weg wäre eine zweite Wahrheit über
 * den Lernstand.
 */
class LearningPathTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Carbon::setTestNow(Carbon::parse('2026-09-01 09:00:00'));
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function paths(): LearningPathService {
        return app(LearningPathService::class);
    }

    private function releasedCourse(string $title): LearningCourse {
        $service = app(LearningCourseService::class);
        $course = $service->createCourse($this->organization, null, ['title' => $title]);
        $service->addUnit($course, ['title' => 'Einführung']);
        $service->release($course->refresh(), null);

        return $course->refresh();
    }

    private function draftCourse(string $title): LearningCourse {
        return app(LearningCourseService::class)->createCourse($this->organization, null, ['title' => $title]);
    }

    private function path(array $courses, ?string $targetRole = null): LearningPath {
        $path = LearningPath::query()->create([
            'organization_id' => $this->organization->id,
            'code' => 'EINARB',
            'title' => 'Einarbeitung Montage',
            'target_role' => $targetRole,
            'is_active' => true,
        ]);

        foreach ($courses as $index => [$course, $dueDays]) {
            LearningPathItem::query()->create([
                'organization_id' => $this->organization->id,
                'learning_path_id' => $path->id,
                'learning_course_id' => $course->id,
                'position' => $index + 1,
                'is_mandatory' => true,
                'due_days' => $dueDays,
            ]);
        }

        return $path->refresh();
    }

    public function test_zuweisung_legt_einschreibungen_mit_fristen_an(): void {
        $first = $this->releasedCourse('Sicherheitsunterweisung');
        $second = $this->releasedCourse('Maschinenkunde');
        $path = $this->path([[$first, 7], [$second, 30]]);
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $created = $this->paths()->assign($path, $user);

        $this->assertCount(2, $created);
        $this->assertSame(LearningEnrollmentSource::Path, $created[0]->source);

        // Die Frist rechnet ab heute, nicht ab dem Ende der vorherigen
        // Station — sonst verschöbe sich der ganze Pfad, sobald jemand eine
        // Station liegen lässt.
        $this->assertSame('2026-09-08', $created[0]->due_at?->toDateString());
        $this->assertSame('2026-10-01', $created[1]->due_at?->toDateString());
    }

    public function test_entwurf_wird_uebersprungen_statt_den_pfad_zu_kippen(): void {
        $released = $this->releasedCourse('Sicherheitsunterweisung');
        $draft = $this->draftCourse('Noch in Arbeit');
        $path = $this->path([[$released, 7], [$draft, 14]]);
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $created = $this->paths()->assign($path, $user);

        $this->assertCount(1, $created);
        $this->assertSame($released->id, $created[0]->learning_course_id);
    }

    public function test_doppelte_zuweisung_erzeugt_keine_zweite_einschreibung(): void {
        $course = $this->releasedCourse('Sicherheitsunterweisung');
        $path = $this->path([[$course, 7]]);
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $this->paths()->assign($path, $user);
        $this->paths()->assign($path, $user);

        $this->assertSame(1, LearningEnrollment::query()->where('user_id', $user->id)->count());
    }

    public function test_automatik_weist_nach_zielrolle_zu(): void {
        $course = $this->releasedCourse('Sicherheitsunterweisung');
        $path = $this->path([[$course, 7]], targetRole: 'aussendienst');

        $matching = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $other = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        $result = $this->paths()->assignByRole($this->organization);

        $this->assertSame(1, $result['paths']);
        $this->assertGreaterThanOrEqual(1, $result['enrollments']);
        $this->assertTrue(LearningEnrollment::query()->where('user_id', $matching->id)->exists());
        $this->assertFalse(LearningEnrollment::query()->where('user_id', $other->id)->exists());

        unset($path);
    }

    public function test_fortschritt_zeigt_die_stationen_in_reihenfolge(): void {
        $first = $this->releasedCourse('Sicherheitsunterweisung');
        $second = $this->releasedCourse('Maschinenkunde');
        $path = $this->path([[$first, 7], [$second, 30]]);
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $this->paths()->assign($path, $user);

        $rows = $this->paths()->progressFor($path, $user);

        $this->assertCount(2, $rows);
        $this->assertSame($first->id, $rows[0]['item']->learning_course_id);
        $this->assertFalse($rows[0]['done']);
        $this->assertNotNull($rows[0]['enrollment']);
    }

    public function test_oberflaeche_legt_pfad_und_station_an(): void {
        $course = $this->releasedCourse('Sicherheitsunterweisung');
        $manager = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($manager)
            ->post(route('learning.paths.store'), [
                'code' => 'ONBOARD',
                'title' => 'Einarbeitung Büro',
                'duration_days' => 60,
            ])
            ->assertRedirect();

        $path = LearningPath::query()->where('code', 'ONBOARD')->firstOrFail();

        $this->actingAs($manager)
            ->post(route('learning.paths.items.store', $path->sqid), [
                'learning_course_id' => $course->sqid,
                'due_days' => 14,
            ])
            ->assertRedirect();

        $this->assertSame(1, $path->refresh()->items()->count());
    }

    public function test_ohne_verwaltungsrecht_kein_zugriff(): void {
        $outsider = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($outsider)
            ->get(route('learning.paths.index'))
            ->assertForbidden();
    }

    public function test_auswahlliste_zeigt_keine_fremde_belegschaft(): void {
        $course = $this->releasedCourse('Sicherheitsunterweisung');
        $path = $this->path([[$course, 7]]);
        $manager = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        $foreign = \App\Models\Organization::factory()->create();
        User::factory()->aussendienst()->create([
            'organization_id' => $foreign->id,
            'name' => 'Fremder Kollege',
        ]);

        // Eine Auswahlliste mit fremder Belegschaft wäre ein Datenabfluss.
        $this->actingAs($manager)
            ->get(route('learning.paths.show', $path->sqid))
            ->assertOk()
            ->assertDontSee('Fremder Kollege');
    }
}
