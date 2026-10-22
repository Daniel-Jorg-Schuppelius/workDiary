<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningStreakConfigurationAndQrTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Audit\AuditLog;
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningUnitProgress};
use App\Models\Platform\User;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use App\Services\Learning\{LearningCertificatePdfRenderer, LearningCompletionService, LearningCourseService, LearningEnrollmentService, LearningGamificationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Lernserie (MVP-1264), Konfigurationsausdruck (MVP-1271), QR auf dem Zertifikat (MVP-1263). */
final class LearningStreakConfigurationAndQrTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{0: LearningCourse, 1: LearningEnrollment} */
    private function enrolled(User $user, int $units = 1): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Brandschutz kompakt']);
        for ($i = 1; $i <= $units; $i++) {
            $courses->addUnit($course, ['title' => 'Teil ' . $i, 'points' => 5]);
        }
        $courses->release($course->refresh(), null);

        return [$course->refresh(), app(LearningEnrollmentService::class)->enroll($course->refresh(), $user)];
    }

    public function test_streak_counts_consecutive_days_and_survives_until_the_day_ends(): void {
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        [$course, $enrollment] = $this->enrolled($learner, 4);
        $units = $course->units()->orderBy('position')->get();
        foreach (['2026-10-18 09:00:00', '2026-10-20 09:00:00', '2026-10-21 18:00:00', '2026-10-21 19:00:00'] as $i => $at) {
            LearningUnitProgress::query()->create([
                'organization_id' => $this->organization->id, 'learning_enrollment_id' => $enrollment->id, 'learning_unit_id' => $units[$i]->id,
                'status' => 'completed', 'started_at' => $at, 'completed_at' => $at, 'attempts' => 1, 'progress_percent' => 100,
            ]);
        }
        $gamification = app(LearningGamificationService::class);

        Carbon::setTestNow('2026-10-22 08:00:00');
        $this->assertSame(2, $gamification->streakFor($learner));
        Carbon::setTestNow('2026-10-23 08:00:00');
        $this->assertSame(0, $gamification->streakFor($learner));
    }

    public function test_switch_shows_streak_and_every_change_raises_the_configuration_version(): void {
        $admin = $this->orgAdmin();
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        [$course, $enrollment] = $this->enrolled($learner);
        app(LearningEnrollmentService::class)->completeUnit($enrollment, $course->units()->firstOrFail());

        $this->actingAs($admin)->put(route('learning.settings.update'), ['gamification_enabled' => '1', 'streak_enabled' => '1'])->assertSessionHas('success');
        $this->assertSame(1, $this->organization->fresh()->settings['learning']['version']);
        // Unverändert gespeichert zählt nicht.
        $this->actingAs($admin)->put(route('learning.settings.update'), ['gamification_enabled' => '1', 'streak_enabled' => '1']);
        $this->assertSame(1, $this->organization->fresh()->settings['learning']['version']);
        $audit = AuditLog::query()->where('event', 'learning.settingsChanged')->sole();
        $this->assertTrue((bool) ($audit->changes['after']['gamification']['streak'] ?? false));

        $this->actingAs($learner)->get(route('learning.my.index'))
            ->assertOk()
            ->assertSee(trans_choice('learning.badge.streak_days', 1, ['count' => 1]));

        $this->actingAs($admin)->get(route('learning.courses.index'))->assertSee(route('learning.settings.print'), false);
        $this->mock(DocumentDesignRenderer::class, function ($mock): void {
            $mock->shouldReceive('renderPdf')->once()->withArgs(fn (RenderDocumentKind $kind, string $view, array $data): bool => $view === 'learning.pdf.configuration'
                && $data['version'] === 1
                && view($view, $data)->render() !== '')->andReturn('%PDF-1.4');
        });
        $this->actingAs($admin)->get(route('learning.settings.print'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($learner)->get(route('learning.settings.print'))->assertForbidden();
    }

    public function test_certificate_carries_a_qr_code_to_the_verification_page(): void {
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        [, $enrollment] = $this->enrolled($learner);
        $certificate = app(LearningCompletionService::class)->issueCertificate($enrollment);

        $this->mock(DocumentDesignRenderer::class, function ($mock): void {
            $mock->shouldReceive('renderPdf')->once()->withArgs(function (RenderDocumentKind $kind, string $view, array $data): bool {
                $html = view($view, $data)->render();

                return str_starts_with($data['qrImage'], 'data:image/svg+xml') && str_contains($html, 'src="' . $data['qrImage'] . '"');
            })->andReturn('%PDF-1.4');
        });

        $this->assertSame('%PDF-1.4', app(LearningCertificatePdfRenderer::class)->output($certificate));
    }
}
