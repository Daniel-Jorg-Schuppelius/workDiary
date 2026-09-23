<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningApiController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Learning\{LearningCourseStatus, LearningEnrollmentSource, LearningEnrollmentStatus};
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\{LearningCertificateResource, LearningCourseResource, LearningEnrollmentResource};
use App\Models\Learning\{LearningCertificate, LearningCourse, LearningCourseCategory, LearningEnrollment};
use App\Models\Platform\User;
use App\Services\Learning\LearningEnrollmentService;
use App\Support\Sqid;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

/**
 * Learning-API (Feature 149, MVP-791) — lesend plus Selbsteinschreibung.
 * Sichtbar sind freigegebene Kurse; Einschreibungen und Zertifikate die
 * eigenen, mit `learning.manage` alle der Organisation. Abschluss, Bewertung
 * und Nachweis laufen weiter über die Web-Workflows — die API meldet sie
 * nur, sie erzeugt sie nicht.
 */
class LearningApiController extends Controller {
    public function __construct(private readonly LearningEnrollmentService $enrollments) {}

    #[OA\Get(
        path: '/learning/courses',
        summary: 'Freigegebene Lernkurse auflisten',
        tags: ['Learning'],
        security: [['bearerAuth' => ['learning:read']]],
        parameters: [
            new OA\Parameter(name: 'category', in: 'query', required: false, description: 'Kategorie (Sqid)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Titel (Teilstring)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function courses(Request $request): AnonymousResourceCollection {
        $categoryId = $request->filled('category') ? Sqid::decodeOrNumeric(LearningCourseCategory::class, (string) $request->query('category')) : null;

        $query = LearningCourse::query()
            ->with('category')
            ->withCount('units')
            ->where('status', LearningCourseStatus::Released->value)
            ->when($categoryId !== null, fn ($q) => $q->where('category_id', $categoryId))
            ->when($request->filled('search'), fn ($q) => $q->whereLikeEscaped('title', (string) $request->query('search')))
            ->orderBy('title');

        return LearningCourseResource::collection($query->paginate(ArticleApiController::perPage($request)));
    }

    #[OA\Get(
        path: '/learning/courses/{course}',
        summary: 'Lernkurs mit Einheiten anzeigen',
        tags: ['Learning'],
        security: [['bearerAuth' => ['learning:read']]],
        parameters: [new OA\Parameter(name: 'course', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function course(LearningCourse $course): LearningCourseResource {
        abort_unless($course->status === LearningCourseStatus::Released, 404);

        return new LearningCourseResource($course->load(['category', 'units.section'])->loadCount('units'));
    }

    #[OA\Get(
        path: '/learning/enrollments',
        summary: 'Einschreibungen auflisten (eigene; mit learning.manage alle)',
        tags: ['Learning'],
        security: [['bearerAuth' => ['learning:read']]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['assigned', 'in_progress', 'completed', 'failed', 'expired', 'cancelled'])),
            new OA\Parameter(name: 'course', in: 'query', required: false, description: 'Kurs (Sqid)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function enrollments(Request $request): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $courseId = $request->filled('course') ? Sqid::decodeOrNumeric(LearningCourse::class, (string) $request->query('course')) : null;

        $query = LearningEnrollment::query()
            ->with(['course', 'user', 'externalParticipant'])
            ->withCount('progress')
            ->when(! $this->managesAll($user), fn ($q) => $q->where('user_id', $user->id))
            ->when(LearningEnrollmentStatus::tryFrom((string) $request->query('status', '')) !== null, fn ($q) => $q->where('status', (string) $request->query('status')))
            ->when($courseId !== null, fn ($q) => $q->where('learning_course_id', $courseId))
            ->orderByDesc('id');

        return LearningEnrollmentResource::collection($query->paginate(ArticleApiController::perPage($request)));
    }

    #[OA\Get(
        path: '/learning/enrollments/{enrollment}',
        summary: 'Einschreibung mit Fortschritt je Einheit',
        tags: ['Learning'],
        security: [['bearerAuth' => ['learning:read']]],
        parameters: [new OA\Parameter(name: 'enrollment', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function enrollment(Request $request, LearningEnrollment $enrollment): LearningEnrollmentResource {
        /** @var User $user */
        $user = $request->user();
        // Fremde Einschreibungen sind 404, nicht 403 — sonst verrät die API,
        // dass es sie gibt.
        abort_unless($this->managesAll($user) || (int) $enrollment->user_id === (int) $user->id, 404);

        return new LearningEnrollmentResource($enrollment->load(['course.units.section', 'progress', 'user', 'externalParticipant']));
    }

    #[OA\Get(
        path: '/learning/certificates',
        summary: 'Zertifikate auflisten (eigene; mit learning.manage alle)',
        tags: ['Learning'],
        security: [['bearerAuth' => ['learning:read']]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function certificates(Request $request): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();

        $query = LearningCertificate::query()
            ->with('course')
            ->when(! $this->managesAll($user), fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('issued_on')
            ->orderByDesc('id');

        return LearningCertificateResource::collection($query->paginate(ArticleApiController::perPage($request)));
    }

    #[OA\Post(
        path: '/learning/courses/{course}/enroll',
        summary: 'Selbst in einen freigegebenen Kurs einschreiben',
        tags: ['Learning'],
        security: [['bearerAuth' => ['learning:write']]],
        parameters: [new OA\Parameter(name: 'course', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 200, description: 'Bereits eingeschrieben'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 422, description: 'Kurs nicht verfügbar, voll oder Voraussetzungen fehlen'),
        ],
    )]
    public function enroll(Request $request, LearningCourse $course): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        abort_unless($course->status === LearningCourseStatus::Released, 404);

        $existing = LearningEnrollment::query()
            ->where('learning_course_id', $course->id)
            ->where('user_id', $user->id)
            ->first();

        $enrollment = $this->enrollments->enroll($course, $user, ['source' => LearningEnrollmentSource::Self->value]);

        return (new LearningEnrollmentResource($enrollment->load(['course', 'user'])))
            ->response()
            ->setStatusCode($existing !== null ? 200 : 201);
    }

    private function managesAll(User $user): bool {
        return $user->isAdmin() || $user->can(Permission::LearningManage->value);
    }
}
