<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningDueWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Enums\Learning\LearningEnrollmentStatus;
use App\Models\Learning\LearningEnrollment;
use App\Models\User;
use Illuminate\Contracts\View\View;

/**
 * Die eigenen offenen Schulungen der Lernplattform (Feature 149, MVP-789) —
 * überfällige zuerst. Kein Permission-Gate: es sind die eigenen
 * Einschreibungen; ohne Einschreibung erscheint die Kachel nicht.
 */
class LearningDueWidget extends Widget {
    public function key(): string {
        return 'learning-due';
    }

    public function label(): string {
        return (string) __('Meine Schulungen');
    }

    public function icon(): string {
        return 'school';
    }

    public function defaultOrder(): int {
        return 177;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Deadlines;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.learning_due.description');
    }

    public function requiredModule(): ?string {
        return 'module.lms';
    }

    public function availableFor(User $user): bool {
        return parent::availableFor($user)
            && LearningEnrollment::query()->where('user_id', $user->id)->exists();
    }

    public function render(User $user): View|string {
        $enrollments = LearningEnrollment::query()
            ->with('course:id,title')
            ->where('user_id', $user->id)
            ->whereIn('status', [LearningEnrollmentStatus::Assigned->value, LearningEnrollmentStatus::InProgress->value])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->limit(5)
            ->get();

        return view('dashboard.widgets.learning-due', [
            'enrollments' => $enrollments,
        ]);
    }
}
