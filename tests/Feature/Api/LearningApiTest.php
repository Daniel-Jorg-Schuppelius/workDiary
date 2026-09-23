<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Learning\{LearningCourse, LearningEnrollment};
use App\Models\Platform\{Organization, User};
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Learning-API (Feature 149, MVP-791): freigegebene Kurse lesen, eigene
 * Einschreibungen und Zertifikate (mit learning.manage alle), Selbst-
 * einschreibung — Abilities `learning:read`/`learning:write`, Sqids,
 * Mandantengrenze.
 */
final class LearningApiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $learner;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id, 'name' => 'Lisa Lernend']);
    }

    private function course(string $title, bool $release = true, array $attributes = []): LearningCourse {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, array_merge(['title' => $title], $attributes));
        $courses->addUnit($course, ['title' => 'Grundlagen']);
        if ($release) {
            $courses->release($course->refresh(), null);
        }

        return $course->refresh();
    }

    public function test_missing_ability_is_forbidden_and_guest_unauthorized(): void {
        $this->getJson(route('api.learning.courses.index'))->assertUnauthorized();

        Sanctum::actingAs($this->learner, ['diary:read']);
        $this->getJson(route('api.learning.courses.index'))->assertForbidden();
    }

    public function test_courses_list_only_released_and_show_has_units_without_content(): void {
        $released = $this->course('Brandschutz kompakt');
        $this->course('Entwurf', release: false);
        Sanctum::actingAs($this->learner, ['learning:read']);

        $list = $this->getJson(route('api.learning.courses.index'))->assertOk();
        $this->assertSame(['Brandschutz kompakt'], array_column($list->json('data'), 'title'));
        $this->assertSame($released->sqid, $list->json('data.0.id'));
        $this->assertSame(1, $list->json('data.0.units_count'));

        $show = $this->getJson(route('api.learning.courses.show', $released))->assertOk();
        $this->assertSame('Grundlagen', $show->json('data.units.0.title'));
        $this->assertArrayNotHasKey('content', $show->json('data.units.0'));

        $draft = LearningCourse::query()->where('title', 'Entwurf')->firstOrFail();
        $this->getJson(route('api.learning.courses.show', $draft))->assertNotFound();
    }

    public function test_enrollments_are_own_unless_manage_and_show_lists_unit_progress(): void {
        $course = $this->course('Brandschutz kompakt');
        $own = app(LearningEnrollmentService::class)->enroll($course, $this->learner);
        $foreign = app(LearningEnrollmentService::class)->enroll($course, $this->orgUser());
        app(LearningEnrollmentService::class)->completeUnit($own, $course->units()->firstOrFail());

        Sanctum::actingAs($this->learner, ['learning:read']);
        $list = $this->getJson(route('api.learning.enrollments.index'))->assertOk();
        $this->assertSame([$own->sqid], array_column($list->json('data'), 'id'));

        $show = $this->getJson(route('api.learning.enrollments.show', $own))->assertOk();
        $this->assertSame('completed', $show->json('data.status'));
        $this->assertSame('completed', $show->json('data.units.0.status'));
        $this->getJson(route('api.learning.enrollments.show', $foreign))->assertNotFound();

        Sanctum::actingAs($this->admin, ['learning:read']);
        $all = $this->getJson(route('api.learning.enrollments.index', ['status' => 'assigned']))->assertOk();
        $this->assertSame([$foreign->sqid], array_column($all->json('data'), 'id'));
    }

    public function test_certificates_and_tenant_boundary(): void {
        $course = $this->course('Brandschutz kompakt', attributes: ['certificate_enabled' => true]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $this->learner);
        app(LearningEnrollmentService::class)->completeUnit($enrollment, $course->units()->firstOrFail());

        Sanctum::actingAs($this->learner, ['learning:read']);
        $list = $this->getJson(route('api.learning.certificates.index'))->assertOk();
        $this->assertCount(1, $list->json('data'));
        $this->assertSame('Brandschutz kompakt', $list->json('data.0.course.title'));
        $this->assertStringContainsString('/zertifikat/', (string) $list->json('data.0.verify_url'));

        // Fremder Mandant: nichts davon ist sichtbar.
        $otherOrg = Organization::factory()->create();
        $stranger = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        Sanctum::actingAs($stranger, ['learning:read']);
        $this->assertSame([], $this->getJson(route('api.learning.certificates.index'))->assertOk()->json('data'));
        $this->assertSame([], $this->getJson(route('api.learning.courses.index'))->assertOk()->json('data'));
        $this->getJson(route('api.learning.enrollments.show', $enrollment))->assertNotFound();
    }

    public function test_self_enrollment_needs_write_ability_and_respects_course_rules(): void {
        $course = $this->course('Brandschutz kompakt');
        $closed = $this->course('Geschlossen', attributes: ['available_until' => now()->subDay()->toDateString()]);

        Sanctum::actingAs($this->learner, ['learning:read']);
        $this->postJson(route('api.learning.courses.enroll', $course))->assertForbidden();

        Sanctum::actingAs($this->learner, ['learning:read', 'learning:write']);
        $created = $this->postJson(route('api.learning.courses.enroll', $course))->assertCreated();
        $this->assertSame('self', $created->json('data.source'));
        $this->assertSame(1, LearningEnrollment::query()->count());

        // Zweiter Aufruf ist idempotent — 200 mit derselben Einschreibung.
        $again = $this->postJson(route('api.learning.courses.enroll', $course))->assertOk();
        $this->assertSame($created->json('data.id'), $again->json('data.id'));

        $this->postJson(route('api.learning.courses.enroll', $closed))->assertUnprocessable();
    }
}
