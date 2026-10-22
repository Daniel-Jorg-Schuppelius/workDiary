<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnrollInCourseAction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning\Automation;

use App\Automation\Actions\TargetedRuleAction;
use App\Automation\Triggers\UserCreatedTrigger;
use App\Enums\Learning\{LearningCourseStatus, LearningEnrollmentSource};
use App\Models\Asset\AssetAssignment;
use App\Models\Learning\LearningCourse;
use App\Models\Platform\User;
use App\Services\Asset\Automation\AssetCheckedOutTrigger;
use App\Services\Learning\LearningEnrollmentService;
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Zuweisungsweg 5 der Lernplattform (MVP-1273): eine Regel schreibt die
 * betroffene Person in einen Kurs ein — die neue Person selbst oder wer das
 * Gerät erhalten hat. Eine laufende Einschreibung bleibt, wie sie ist.
 */
final class EnrollInCourseAction implements TargetedRuleAction {
    public const TYPE = 'learning.enroll';

    public function __construct(private readonly LearningEnrollmentService $enrollments) {}

    public function type(): string {
        return self::TYPE;
    }

    public function label(): string {
        return (string) __('automation.action.learning_enroll');
    }

    /** @return list<string> */
    public function triggers(): array {
        return [UserCreatedTrigger::KEY, AssetCheckedOutTrigger::KEY];
    }

    public function targetLabel(): string {
        return (string) __('automation.form.target_course');
    }

    /** @return array<string, string> */
    public function targetOptions(int $organizationId): array {
        return LearningCourse::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('status', LearningCourseStatus::Released->value)
            ->orderBy('title')
            ->get(['id', 'title'])
            ->mapWithKeys(static fn (LearningCourse $course): array => [(string) $course->sqid => (string) $course->title])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(Model $subject, array $params): array {
        $learner = match (true) {
            $subject instanceof User => $subject,
            $subject instanceof AssetAssignment => $subject->assignedToUser,
            default => null,
        };
        if (! $learner instanceof User) {
            return ['skipped' => 'no_person'];
        }
        $organizationId = (int) $subject->getAttribute('organization_id');
        $courseId = Sqid::decode(LearningCourse::class, (string) ($params['target'] ?? ''));
        $course = $courseId !== null
            ? LearningCourse::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->find($courseId)
            : null;
        if ($course === null) {
            return ['skipped' => 'course_missing'];
        }

        try {
            $enrollment = $this->enrollments->enroll($course, $learner, ['source' => LearningEnrollmentSource::Rule->value]);
        } catch (ValidationException $e) {
            return ['skipped' => 'not_enrollable', 'message' => (string) collect($e->errors())->flatten()->first()];
        }

        return ['learning_enrollment_id' => (int) $enrollment->id, 'user_id' => (int) $learner->id];
    }
}
