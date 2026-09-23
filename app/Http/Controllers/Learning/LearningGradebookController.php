<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningGradebookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningGradebookComponent, LearningManualGrade};
use App\Models\Platform\User;
use App\Services\Learning\{LearningGradebookService, LearningReportCardPdfRenderer};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Notenbuch je Kurs (Feature 149, MVP-790): Lernende × Komponenten,
 * Komponenten mit Gewichten festlegen, manuelle Noten eintragen, Zeugnis
 * als PDF und die Übersicht als CSV. Recht `learning.grade`; mit
 * Trainer-Scoping nur sichtbare Kurse.
 */
class LearningGradebookController extends Controller {
    public function __construct(private readonly LearningGradebookService $gradebook) {}

    public function show(LearningCourse $course): View {
        $this->guard($course);
        $matrix = $this->gradebook->forCourse($course);

        return view('learning.gradebook.show', [
            'course' => $course,
            'components' => $matrix['components'],
            'rows' => $matrix['rows'],
        ]);
    }

    public function editComponents(LearningCourse $course): View {
        $this->guard($course);

        return view('learning.gradebook._components_dialog', [
            'course' => $course,
            'units' => $course->units()->with(['quiz', 'assignment'])->orderBy('position')->get()
                ->filter(static fn ($u): bool => $u->quiz !== null || $u->assignment !== null)->values(),
            'components' => $this->gradebook->componentsFor($course),
        ]);
    }

    public function updateComponents(Request $request, LearningCourse $course): RedirectResponse {
        $this->guard($course);

        $data = $request->validate([
            'components' => ['nullable', 'array', 'max:50'],
            'components.*.kind' => ['required', 'string', 'in:quiz,assignment,manual'],
            'components.*.enabled' => ['nullable', 'boolean'],
            'components.*.id' => ['nullable', 'string', 'max:40'],
            'components.*.unit' => ['nullable', 'string', 'max:40'],
            'components.*.title' => ['nullable', 'string', 'max:180'],
            'components.*.weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'components.*.max_points' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $rows = [];
        foreach ((array) ($data['components'] ?? []) as $row) {
            $isManual = $row['kind'] === LearningGradebookComponent::KIND_MANUAL;
            // Nicht angehakte Einheiten und leere manuelle Zeilen fallen weg.
            if (! $isManual && ! ($row['enabled'] ?? false)) {
                continue;
            }
            if ($isManual && trim((string) ($row['title'] ?? '')) === '') {
                continue;
            }
            $rows[] = [
                'id' => ! empty($row['id']) ? Sqid::decodeOrNumeric(LearningGradebookComponent::class, (string) $row['id']) : null,
                'kind' => $row['kind'],
                'unit_id' => ! empty($row['unit']) ? Sqid::decodeOrNumeric(\App\Models\Learning\LearningUnit::class, (string) $row['unit']) : null,
                'title' => $row['title'] ?? null,
                'weight' => $row['weight'] ?? null,
                'max_points' => $row['max_points'] ?? null,
            ];
        }

        $this->gradebook->saveComponents($course, $rows);

        return redirect()
            ->route('learning.courses.gradebook.show', $course)
            ->with('success', __('learning.flash.components_saved'));
    }

    public function gradeDialog(LearningCourse $course, LearningEnrollment $enrollment, LearningGradebookComponent $component): View {
        $this->guard($course);
        $this->guardBelongs($course, $enrollment, $component);

        return view('learning.gradebook._manual_grade_dialog', [
            'course' => $course,
            'enrollment' => $enrollment,
            // `component` ist in Blade reserviert — daher der längere Name.
            'gradebookComponent' => $component,
            'history' => LearningManualGrade::query()
                ->with('gradedBy:id,name')
                ->where('learning_enrollment_id', $enrollment->id)
                ->where('learning_gradebook_component_id', $component->id)
                ->orderByDesc('graded_at')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function storeManualGrade(Request $request, LearningCourse $course, LearningEnrollment $enrollment, LearningGradebookComponent $component): RedirectResponse {
        $this->guard($course);
        $this->guardBelongs($course, $enrollment, $component);

        $data = $request->validate([
            'points' => ['required', 'integer', 'min:0', 'max:10000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $this->gradebook->recordManualGrade($enrollment, $component, (int) $data['points'], $data['note'] ?? null, $actor);

        return redirect()
            ->route('learning.courses.gradebook.show', $course)
            ->with('success', __('learning.flash.grade_recorded'));
    }

    public function reportCard(LearningCourse $course, LearningEnrollment $enrollment): Response {
        $this->guard($course);
        abort_unless($enrollment->learning_course_id === $course->id, 404);

        $renderer = app(LearningReportCardPdfRenderer::class);

        return response($renderer->output($enrollment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $renderer->filename($enrollment) . '"',
        ]);
    }

    public function csv(LearningCourse $course): Response {
        $this->guard($course);

        return response($this->gradebook->csvFor($course), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="notenbuch-' . $course->code . '.csv"',
        ]);
    }

    private function guard(LearningCourse $course): void {
        Gate::authorize(Permission::LearningGrade->value);
        /** @var User $user */
        $user = Auth::user();
        abort_unless($course->isVisibleTo($user), 404);
    }

    private function guardBelongs(LearningCourse $course, LearningEnrollment $enrollment, LearningGradebookComponent $component): void {
        abort_unless($enrollment->learning_course_id === $course->id && $component->learning_course_id === $course->id, 404);
    }
}
