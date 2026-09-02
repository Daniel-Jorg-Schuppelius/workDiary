<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyDueWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Enums\Safety\HazardAssessmentStatus;
use App\Enums\User\Permission;
use App\Models\Safety\{HazardAssessment, MedicalCheckup};
use App\Models\User;
use App\Support\Query\DateRange;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Arbeitsschutz-Fristen: Gefährdungsbeurteilungen, deren Prüfung ansteht,
 * und fällige arbeitsmedizinische Vorsorge.
 *
 * Bewusst nur Zähler und Fristdaten — Gesundheitsdaten gehören nicht aufs
 * Dashboard (Feature 132).
 */
class SafetyDueWidget extends Widget {
    private const WINDOW_DAYS = 60;

    public function key(): string {
        return 'safety-due';
    }

    public function label(): string {
        return (string) __('Arbeitsschutz-Fristen');
    }

    public function icon(): string {
        return 'health_and_safety';
    }

    public function defaultOrder(): int {
        return 175;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Deadlines;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.safety_due.description');
    }

    public function availableFor(User $user): bool {
        return Gate::forUser($user)->allows(Permission::SafetyViewAny->value);
    }

    public function render(User $user): View|string {
        $horizon = now()->addDays(self::WINDOW_DAYS);

        $assessments = HazardAssessment::query()
            ->where('status', HazardAssessmentStatus::Approved)
            ->whereNotNull('review_due_on')
            ->where('review_due_on', '<', DateRange::dayAfter($horizon));

        $checkups = MedicalCheckup::query()
            ->whereNotNull('next_due_on')
            ->where('next_due_on', '<', DateRange::dayAfter($horizon));

        return view('dashboard.widgets.safety-due', [
            'assessmentsDue' => (clone $assessments)->count(),
            'assessmentsOverdue' => (clone $assessments)->where('review_due_on', '<', DateRange::day(now()))->count(),
            'checkupsDue' => (clone $checkups)->count(),
            'checkupsOverdue' => (clone $checkups)->where('next_due_on', '<', DateRange::day(now()))->count(),
        ]);
    }
}
