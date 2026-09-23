<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningGradingQueueWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Enums\User\Permission;
use App\Models\Learning\{LearningAnswer, LearningCourse, LearningTimeSession};
use App\Models\Platform\User;
use App\Services\Learning\LearningAssignmentService;
use Illuminate\Contracts\View\View;

/**
 * Bewertungs-Rückstand (Feature 149, MVP-789): offene Abgaben, Aufsätze und
 * Lernzeit-Freigaben — mit Trainer-Scoping nur die eigenen Kurse.
 */
class LearningGradingQueueWidget extends Widget {
    public function key(): string {
        return 'learning-grading-queue';
    }

    public function label(): string {
        return (string) __('Bewertungen offen');
    }

    public function icon(): string {
        return 'grading';
    }

    public function defaultOrder(): int {
        return 178;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Deadlines;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.learning_grading_queue.description');
    }

    public function requiredModule(): ?string {
        return 'module.lms';
    }

    public function requiredAbility(): ?string {
        return Permission::LearningGrade->value;
    }

    public function render(User $user): View|string {
        // Trainer-Scoping (MVP-786): derselbe Scope wie im Bewertungscockpit.
        $courseIds = LearningCourse::query()->visibleTo($user)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $submissions = app(LearningAssignmentService::class)->pendingQuery()
            ->whereHas('enrollment', fn ($e) => $e->whereIn('learning_course_id', $courseIds))
            ->count();
        $essays = LearningAnswer::query()
            ->whereNull('is_correct')
            ->whereNull('corrected_points')
            ->whereHas('attempt', fn ($q) => $q->whereNotNull('submitted_at')
                ->whereHas('enrollment', fn ($e) => $e->whereIn('learning_course_id', $courseIds)))
            ->count();
        $timeApprovals = LearningTimeSession::query()
            ->where('approval_status', LearningTimeSession::APPROVAL_PENDING)
            ->whereHas('enrollment', fn ($e) => $e->whereIn('learning_course_id', $courseIds))
            ->count();

        return view('dashboard.widgets.learning-grading-queue', [
            'submissions' => $submissions,
            'essays' => $essays,
            'timeApprovals' => $timeApprovals,
            'total' => $submissions + $essays + $timeApprovals,
        ]);
    }
}
