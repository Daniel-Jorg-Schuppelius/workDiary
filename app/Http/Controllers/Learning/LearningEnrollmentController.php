<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningEnrollmentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Enums\ExternalParticipant\ExternalParty;
use App\Enums\Learning\{LearningCourseStatus, LearningEnrollmentSource, LearningEnrollmentStatus, LearningProgressStatus};
use App\Http\Controllers\Controller;
use App\Models\{ExternalParticipant, User};
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningQuiz};
use App\Services\Learning\{LearningAccessService, LearningEnrollmentService};
use App\Support\Sqid;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

/**
 * Teilnehmerverwaltung je Kurs (Feature 149, MVP-778): wer ist eingeschrieben,
 * manuell einschreiben, Frist/Zugang verlängern, stornieren, Einstiegslink
 * für Externe erzeugen.
 *
 * Fachlich entscheidet der {@see LearningEnrollmentService} (nur freigegebene
 * Kurse, Pflicht-Einschreibungen nicht stornierbar) und der
 * {@see LearningAccessService} (Token nur gehasht, neuer Link entwertet den
 * alten). Der Zugriff hängt am Kurs (`updateMeta`), eine Einschreibung eines
 * fremden Kurses ist 404.
 */
class LearningEnrollmentController extends Controller {
    private const EXTERNALS_LIMIT = 500;

    public function __construct(
        private readonly LearningEnrollmentService $enrollments,
        private readonly LearningAccessService $access,
    ) {}

    public function index(Request $request, LearningCourse $course): View {
        Gate::authorize('updateMeta', $course);

        $status = $request->query('status');
        $status = is_string($status) ? LearningEnrollmentStatus::tryFrom($status) : null;
        $source = $request->query('source');
        $source = is_string($source) ? LearningEnrollmentSource::tryFrom($source) : null;
        $search = trim((string) $request->query('q', ''));

        $query = LearningEnrollment::query()
            ->where('learning_course_id', $course->id)
            ->with(['user', 'externalParticipant', 'courseVersion'])
            ->withCount([
                'progress as completed_units_count' => fn (Builder $q) => $q->where('status', LearningProgressStatus::Completed->value),
            ])
            ->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('status', $status->value);
        }
        if ($source !== null) {
            $query->where('source', $source->value);
        }
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->whereHas('user', fn (Builder $u) => $u->whereLikeEscaped('name', $search))
                    ->orWhereHas('externalParticipant', fn (Builder $e) => $e->whereLikeEscaped('name', $search));
            });
        }

        $counts = LearningEnrollment::query()
            ->where('learning_course_id', $course->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('learning.enrollments.index', [
            'course' => $course,
            'enrollments' => $query->paginate(50)->withQueryString(),
            'status' => $status,
            'source' => $source,
            'search' => $search,
            'counts' => $counts,
            'mandatoryUnits' => $course->units()->where('is_mandatory', true)->count(),
            'canEnroll' => $course->status === LearningCourseStatus::Released,
            'hasQuizzes' => LearningQuiz::query()->whereIn('learning_unit_id', $course->units()->select('id'))->exists(),
        ]);
    }

    public function create(LearningCourse $course): View {
        Gate::authorize('updateMeta', $course);

        return view('learning.enrollments._enroll_dialog', [
            'course' => $course,
            'users' => User::query()
                ->inCurrentOrganization()
                ->where('organization_id', $course->organization_id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'externals' => ExternalParticipant::query()
                ->where('organization_id', $course->organization_id)
                ->whereNull('revoked_at')
                ->whereNotNull('email')
                ->orderBy('name')
                ->limit(self::EXTERNALS_LIMIT)
                ->get(['id', 'name', 'email']),
            'parties' => ExternalParty::cases(),
        ]);
    }

    public function store(Request $request, LearningCourse $course): RedirectResponse {
        Gate::authorize('updateMeta', $course);

        $data = $request->validate([
            'learner_kind' => ['required', Rule::in(['user', 'external', 'external_new'])],
            'user_id' => ['nullable', 'required_if:learner_kind,user', 'string', 'max:64'],
            'external_participant_id' => ['nullable', 'required_if:learner_kind,external', 'string', 'max:64'],
            'name' => ['nullable', 'required_if:learner_kind,external_new', 'string', 'min:2', 'max:160'],
            'email' => ['nullable', 'required_if:learner_kind,external_new', 'email', 'max:190'],
            'party' => ['nullable', Rule::enum(ExternalParty::class)],
            'due_at' => ['nullable', 'date'],
            'access_until' => ['nullable', 'date', 'after_or_equal:due_at'],
            'reason' => ['nullable', 'string', 'max:255'],
            'send_link' => ['nullable', 'boolean'],
        ]);

        $learner = $this->resolveLearner($course, $data);

        $attributes = [
            'source' => LearningEnrollmentSource::Manual->value,
            'assigned_by_user_id' => $this->actor()->id,
            'due_at' => $data['due_at'] ?? null,
            'reason' => $data['reason'] ?? null,
        ];
        // Nur ein gesetzter Wert überstimmt die Kursvorgabe (`access_days`).
        if (! empty($data['access_until'])) {
            $attributes['access_until'] = $data['access_until'];
        }

        $enrollment = $this->enrollments->enroll($course, $learner, $attributes);

        $mailed = false;
        if ($learner instanceof ExternalParticipant && (bool) ($data['send_link'] ?? false)) {
            $mailed = $this->access->deliver($enrollment, $this->actor());
        }

        return redirect()
            ->route('learning.courses.enrollments.index', $course)
            ->with('success', __($mailed ? 'learning.flash.participant_enrolled_link_sent' : 'learning.flash.participant_enrolled', [
                'name' => $enrollment->learnerName(),
            ]));
    }

    public function edit(LearningCourse $course, LearningEnrollment $enrollment): View {
        Gate::authorize('updateMeta', $course);
        $this->guardBelongsToCourse($course, $enrollment);

        return view('learning.enrollments._extend_dialog', [
            'course' => $course,
            'enrollment' => $enrollment,
        ]);
    }

    public function update(Request $request, LearningCourse $course, LearningEnrollment $enrollment): RedirectResponse {
        Gate::authorize('updateMeta', $course);
        $this->guardBelongsToCourse($course, $enrollment);

        $data = $request->validate([
            'due_at' => ['nullable', 'date'],
            'access_until' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $this->enrollments->extendAccess(
            $enrollment,
            $data['due_at'] ?? null,
            $data['access_until'] ?? null,
            $this->actor(),
            $data['reason'],
        );

        return redirect()
            ->route('learning.courses.enrollments.index', $course)
            ->with('success', __('learning.flash.access_extended', ['name' => $enrollment->learnerName()]));
    }

    public function cancel(Request $request, LearningCourse $course, LearningEnrollment $enrollment): RedirectResponse {
        Gate::authorize('updateMeta', $course);
        $this->guardBelongsToCourse($course, $enrollment);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $this->enrollments->cancel($enrollment, $this->actor(), $data['reason']);

        return redirect()
            ->route('learning.courses.enrollments.index', $course)
            ->with('success', __('learning.flash.enrollment_cancelled', ['name' => $enrollment->learnerName()]));
    }

    /** Versuchsfreigabe (MVP-785): Dialog mit Prüfung und Begründung. */
    public function waiverDialog(LearningCourse $course, LearningEnrollment $enrollment): View {
        Gate::authorize('updateMeta', $course);
        $this->guardBelongsToCourse($course, $enrollment);

        return view('learning.enrollments._waiver_dialog', [
            'course' => $course,
            'enrollment' => $enrollment,
            'quizzes' => LearningQuiz::query()
                ->whereIn('learning_unit_id', $course->units()->select('id'))
                ->orderBy('title')
                ->get(['id', 'title']),
        ]);
    }

    public function grantWaiver(Request $request, LearningCourse $course, LearningEnrollment $enrollment): RedirectResponse {
        Gate::authorize('updateMeta', $course);
        $this->guardBelongsToCourse($course, $enrollment);

        $data = $request->validate([
            'quiz_id' => ['required', 'string', 'max:64'],
            'reason' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $quizId = Sqid::decode(LearningQuiz::class, (string) $data['quiz_id']);
        $quiz = $quizId !== null
            ? LearningQuiz::query()->whereIn('learning_unit_id', $course->units()->select('id'))->find($quizId)
            : null;
        if ($quiz === null) {
            throw ValidationException::withMessages(['quiz_id' => (string) __('learning.errors.quiz_foreign')]);
        }

        app(\App\Services\Learning\LearningQuizService::class)->grantWaiver($enrollment, $quiz, $this->actor(), $data['reason']);

        return redirect()
            ->route('learning.courses.enrollments.index', $course)
            ->with('success', __('learning.flash.waiver_granted', ['name' => $enrollment->learnerName()]));
    }

    /** Einstiegslink für eine externe Person erzeugen und mailen. */
    public function accessLink(LearningCourse $course, LearningEnrollment $enrollment): RedirectResponse {
        Gate::authorize('updateMeta', $course);
        $this->guardBelongsToCourse($course, $enrollment);

        $mailed = $this->access->deliver($enrollment, $this->actor());

        return redirect()
            ->route('learning.courses.enrollments.index', $course)
            ->with($mailed ? 'success' : 'error', __($mailed ? 'learning.flash.access_link_sent' : 'learning.flash.access_link_no_email', [
                'name' => $enrollment->learnerName(),
            ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveLearner(LearningCourse $course, array $data): User|ExternalParticipant {
        switch ($data['learner_kind']) {
            case 'user':
                $id = Sqid::decode(User::class, (string) $data['user_id']);
                $user = $id !== null
                    ? User::query()->inCurrentOrganization()->where('organization_id', $course->organization_id)->find($id)
                    : null;
                if ($user === null) {
                    throw ValidationException::withMessages(['user_id' => (string) __('learning.errors.learner_required')]);
                }

                return $user;

            case 'external':
                $id = Sqid::decode(ExternalParticipant::class, (string) $data['external_participant_id']);
                $participant = $id !== null
                    ? ExternalParticipant::query()->where('organization_id', $course->organization_id)->whereNull('revoked_at')->find($id)
                    : null;
                if ($participant === null) {
                    throw ValidationException::withMessages(['external_participant_id' => (string) __('learning.errors.learner_required')]);
                }

                return $participant;

            default:
                // Neue externe Person: der Beteiligten-Datensatz hängt am Kurs,
                // trägt aber keine Diary-Fähigkeiten — der Lernzugang läuft
                // ausschließlich über den Einmal-Link der Einschreibung.
                return ExternalParticipant::query()->create([
                    'organization_id' => $course->organization_id,
                    'subject_type' => $course->getMorphClass(),
                    'subject_id' => $course->id,
                    'name' => trim((string) $data['name']),
                    'email' => Str::lower(trim((string) $data['email'])),
                    'party' => ($data['party'] ?? null) ?: ExternalParty::Other->value,
                    'token_hash' => CryptoHelper::hash(Str::random(64)),
                    'abilities' => [],
                    'expires_at' => ! empty($data['access_until'])
                        ? Carbon::parse((string) $data['access_until'])->endOfDay()
                        : now()->addYear(),
                    'invited_by_user_id' => $this->actor()->id,
                    'created_at' => now(),
                ]);
        }
    }

    private function guardBelongsToCourse(LearningCourse $course, LearningEnrollment $enrollment): void {
        abort_unless($enrollment->learning_course_id === $course->id, 404);
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
