<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningGradingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{Attachment, User};
use App\Models\Learning\{LearningAnswer, LearningSubmission, LearningTimeSession};
use App\Services\Learning\{LearningAssignmentService, LearningQuizService, LearningTimeService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Storage};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Betreuer-Cockpit (Feature 149, MVP-739): offene Bewertungen an einer
 * Stelle — Aufgaben-Abgaben und Aufsätze aus Prüfungen.
 *
 * Gate ist `learning.grade`; die Sichtbarkeit folgt der Organisation
 * (globaler Scope), eine Team-Einschränkung kommt mit der Auswertung.
 */
class LearningGradingController extends Controller {
    public function __construct(
        private readonly LearningAssignmentService $assignments,
        private readonly LearningQuizService $quizzes,
        private readonly LearningTimeService $time,
    ) {}

    public function index(): View {
        Gate::authorize(Permission::LearningGrade->value);

        return view('learning.grading.index', [
            'submissions' => $this->assignments->pendingQuery()->paginate(25),
            'essays' => LearningAnswer::query()
                ->with(['attempt.enrollment.user', 'attempt.quiz', 'question'])
                ->whereNull('is_correct')
                ->whereNull('corrected_points')
                ->whereHas('attempt', fn ($q) => $q->whereNotNull('submitted_at'))
                ->orderBy('created_at')
                ->limit(50)
                ->get(),
        ]);
    }

    public function showSubmission(LearningSubmission $submission): View {
        Gate::authorize(Permission::LearningGrade->value);

        $submission->load(['assignment.unit.course', 'enrollment.user', 'attachments']);

        return view('learning.grading.submission', [
            'submission' => $submission,
            'criteria' => $submission->assignment?->criteria() ?? [],
        ]);
    }

    /**
     * Freigabe von Lernzeit außerhalb der Arbeitszeit (MVP-749).
     *
     * Bei der Zeitpolitik „Freigabe nötig" entsteht die Anwesenheitsspanne
     * **erst mit der Zusage** — vorher zu buchen und später zurückzunehmen
     * wäre ein Eingriff in die Zeitkonten für etwas, das noch niemand
     * entschieden hat.
     */
    public function timeApprovals(): View {
        Gate::authorize(Permission::LearningManage->value);

        return view('learning.grading.time-approvals', [
            'sessions' => LearningTimeSession::query()
                ->with(['user:id,name', 'enrollment.course:id,title'])
                ->where('approval_status', LearningTimeSession::APPROVAL_PENDING)
                ->orderBy('started_at')
                ->paginate(25),
        ]);
    }

    public function approveTime(LearningTimeSession $session): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);

        $this->time->approve($session, $this->actor());

        return redirect()->route('learning.time-approvals.index')
            ->with('success', __('learning.flash.time_approved'));
    }

    public function rejectTime(Request $request, LearningTimeSession $session): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $this->time->reject($session, $this->actor(), $data['reason']);

        return redirect()->route('learning.time-approvals.index')
            ->with('success', __('learning.flash.time_rejected'));
    }

    /**
     * Abgabedatei für die Bewertung herunterladen.
     *
     * Ohne diesen Weg sieht die bewertende Person nur den Dateinamen — eine
     * Bewertung ohne Einsicht in die Abgabe ist keine.
     */
    public function submissionFile(LearningSubmission $submission, Attachment $attachment): StreamedResponse {
        Gate::authorize(Permission::LearningGrade->value);

        // Der Anhang muss zu DIESER Abgabe gehören, sonst wäre die Route ein
        // Leseschlüssel auf jede Datei der Anwendung.
        abort_unless(
            $attachment->attachable_type === $submission->getMorphClass()
            && (int) $attachment->attachable_id === (int) $submission->id,
            404
        );

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function gradeSubmission(Request $request, LearningSubmission $submission): RedirectResponse {
        Gate::authorize(Permission::LearningGrade->value);

        $data = $request->validate([
            'rubric_scores' => ['nullable', 'array'],
            'rubric_scores.*' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'points' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        /** @var array<string, int> $scores */
        $scores = array_map(static fn (mixed $v): int => (int) $v, $data['rubric_scores'] ?? []);

        $this->assignments->grade(
            $submission,
            $scores,
            $data['points'] ?? null,
            $data['feedback'] ?? null,
            $this->actor(),
        );

        return redirect()
            ->route('learning.grading.index')
            ->with('success', __('learning.flash.submission_graded'));
    }

    public function returnSubmission(Request $request, LearningSubmission $submission): RedirectResponse {
        Gate::authorize(Permission::LearningGrade->value);

        $data = $request->validate([
            'feedback' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        $this->assignments->returnForRevision($submission, $data['feedback'], $this->actor());

        return redirect()
            ->route('learning.grading.index')
            ->with('success', __('learning.flash.submission_returned'));
    }

    /** Vier-Augen-Bestätigung einer Bewertung. */
    public function confirmSubmission(LearningSubmission $submission): RedirectResponse {
        Gate::authorize(Permission::LearningGrade->value);

        $this->assignments->secondOpinion($submission, $this->actor());

        return redirect()
            ->route('learning.grading.index')
            ->with('success', __('learning.flash.second_opinion_recorded'));
    }

    /** Aufsatz aus einer Prüfung bewerten (der offene Rest aus MVP-738). */
    public function gradeEssay(Request $request, LearningAnswer $answer): RedirectResponse {
        Gate::authorize(Permission::LearningGrade->value);

        $data = $request->validate([
            'points' => ['required', 'integer', 'min:0', 'max:1000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->quizzes->correctAnswer($answer, (int) $data['points'], $data['note'] ?? null, $this->actor());

        return redirect()
            ->route('learning.grading.index')
            ->with('success', __('learning.flash.answer_graded'));
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
