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
use App\Models\Attachments\Attachment;
use App\Models\Communication\ExternalParticipant;
use App\Models\Learning\{LearningAccessToken, LearningCertificate, LearningCourse, LearningEnrollment, LearningQuiz, LearningUnitProgress};
use App\Models\Platform\User;
use App\Services\Learning\{LearningAccessService, LearningCompletionService, LearningEnrollmentService, LearningQuizService, LearningVersionMigrationService};
use App\Support\Sqid;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\{Gate, Storage};
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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
    private const int EXTERNALS_LIMIT = 500;

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

        $enrollments = $query->paginate(50)->withQueryString();
        $pageIds = $enrollments->getCollection()->pluck('id')->all();

        return view('learning.enrollments.index', [
            'course' => $course,
            'enrollments' => $enrollments,
            // Zertifikat je Einschreibung (MVP-1599): das jüngste, auch ein widerrufenes.
            'certificates' => LearningCertificate::query()
                ->whereIn('learning_enrollment_id', $pageIds)
                ->orderBy('id')
                ->get()
                ->keyBy('learning_enrollment_id'),
            // Nachweise externer Einheiten (MVP-1600), je Einschreibung.
            'proofs' => LearningUnitProgress::query()
                ->whereIn('learning_enrollment_id', $pageIds)
                ->whereHas('attachments')
                ->with(['attachments', 'unit:id,title'])
                ->get()
                ->groupBy('learning_enrollment_id'),
            'canRevokeCertificate' => Gate::allows('revokeCertificate', $course),
            // Einschreibungen mit gültigem Einstiegslink — nur dort lohnt „entwerten".
            'activeLinkIds' => LearningAccessToken::query()
                ->whereIn('learning_enrollment_id', $pageIds)
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->pluck('learning_enrollment_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all(),
            'status' => $status,
            'source' => $source,
            'search' => $search,
            'counts' => $counts,
            'mandatoryUnits' => $course->units()->where('is_mandatory', true)->count(),
            'canEnroll' => $course->status === LearningCourseStatus::Released,
            'hasQuizzes' => LearningQuiz::query()->whereIn('learning_unit_id', $course->units()->select('id'))->exists(),
            'currentVersion' => $course->currentVersion(),
            'behindCount' => app(LearningVersionMigrationService::class)->behind($course)->count(),
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
            'assigned_by_user_id' => $this->authUser()->id,
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
            $mailed = $this->access->deliver($enrollment, $this->authUser());
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
            $this->authUser(),
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

        $this->enrollments->cancel($enrollment, $this->authUser(), $data['reason']);

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

        app(\App\Services\Learning\LearningQuizService::class)->grantWaiver($enrollment, $quiz, $this->authUser(), $data['reason']);

        return redirect()
            ->route('learning.courses.enrollments.index', $course)
            ->with('success', __('learning.flash.waiver_granted', ['name' => $enrollment->learnerName()]));
    }

    /** Nachteilsausgleich (MVP-1267): Zeitverlängerung je Person mit Begründung. */
    public function timeExtensionDialog(LearningCourse $course, LearningEnrollment $enrollment): View {
        Gate::authorize('updateMeta', $course);
        $this->guardBelongsToCourse($course, $enrollment);

        return view('learning.enrollments._time_extension_dialog', ['course' => $course, 'enrollment' => $enrollment]);
    }

    public function updateTimeExtension(Request $request, LearningCourse $course, LearningEnrollment $enrollment): RedirectResponse {
        Gate::authorize('updateMeta', $course);
        $this->guardBelongsToCourse($course, $enrollment);

        $data = $request->validate([
            'time_extension_percent' => ['nullable', 'integer', 'min:0', 'max:200'],
            'reason' => ['required', 'string', 'min:2', 'max:255'],
        ]);
        $percent = isset($data['time_extension_percent']) ? (int) $data['time_extension_percent'] : null;
        app(LearningQuizService::class)->setTimeExtension($enrollment, $percent, $this->authUser(), $data['reason']);

        return redirect()
            ->route('learning.courses.enrollments.index', $course)
            ->with('success', __('learning.flash.time_extension_saved', ['name' => $enrollment->learnerName()]));
    }

    /** Versionswechsel (MVP-1272): Vorschau je Ausgangsversion, dann mit Grund heben. */
    public function versionDialog(LearningCourse $course, LearningVersionMigrationService $versions): View {
        Gate::authorize('updateMeta', $course);
        $current = $course->currentVersion();
        abort_if($current === null, 404);

        $groups = $versions->behind($course)
            ->groupBy(fn (LearningEnrollment $enrollment): int => (int) $enrollment->learning_course_version_id)
            ->map(fn ($group) => [
                'version' => $group->first()?->courseVersion?->version,
                'count' => $group->count(),
                'diff' => $versions->diff($group->first()?->courseVersion, $current),
            ])
            ->values();

        return view('learning.enrollments._version_dialog', ['course' => $course, 'current' => $current, 'groups' => $groups]);
    }

    public function moveToVersion(Request $request, LearningCourse $course, LearningVersionMigrationService $versions): RedirectResponse {
        Gate::authorize('updateMeta', $course);
        $data = $request->validate(['reason' => ['required', 'string', 'min:2', 'max:255']]);
        $current = $course->currentVersion();
        abort_if($current === null, 404);

        $moved = $versions->moveAllToCurrent($course, $this->authUser(), $data['reason']);

        return redirect()
            ->route('learning.courses.enrollments.index', $course)
            ->with('success', __('learning.flash.version_moved', ['count' => $moved, 'version' => $current->version]));
    }

    /** Einstiegslink für eine externe Person erzeugen und mailen. */
    public function accessLink(LearningCourse $course, LearningEnrollment $enrollment): RedirectResponse {
        Gate::authorize('updateMeta', $course);
        $this->guardBelongsToCourse($course, $enrollment);

        $mailed = $this->access->deliver($enrollment, $this->authUser());

        return redirect()
            ->route('learning.courses.enrollments.index', $course)
            ->with($mailed ? 'success' : 'error', __($mailed ? 'learning.flash.access_link_sent' : 'learning.flash.access_link_no_email', [
                'name' => $enrollment->learnerName(),
            ]));
    }

    /** Nachweis einer externen Einheit (MVP-1600) — nur aus einer Einschreibung dieses Kurses. */
    public function proof(LearningCourse $course, LearningEnrollment $enrollment, Attachment $attachment): SymfonyResponse {
        Gate::authorize('updateMeta', $course);
        $this->guardBelongsToCourse($course, $enrollment);

        $carrier = LearningUnitProgress::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->whereKey((int) $attachment->attachable_id)
            ->first();
        abort_unless($carrier !== null && $attachment->attachable_type === $carrier->getMorphClass(), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    /** Einstiegslink entwerten (MVP-1599): der Link eines Externen gilt ab sofort nicht mehr. */
    public function revokeAccessLink(LearningCourse $course, LearningEnrollment $enrollment): RedirectResponse {
        Gate::authorize('updateMeta', $course);
        $this->guardBelongsToCourse($course, $enrollment);
        abort_if($enrollment->external_participant_id === null, 404);

        $revoked = $this->access->revoke($enrollment);

        return redirect()
            ->route('learning.courses.enrollments.index', $course)
            ->with('success', __($revoked > 0 ? 'learning.flash.access_link_revoked' : 'learning.flash.access_link_none', [
                'name' => $enrollment->learnerName(),
            ]));
    }

    /** Zertifikat widerrufen (MVP-1599): Dialog mit Pflichtgrund. */
    public function revokeCertificateDialog(LearningCourse $course, LearningEnrollment $enrollment): View {
        Gate::authorize('revokeCertificate', $course);
        $this->guardBelongsToCourse($course, $enrollment);

        return view('learning.enrollments._revoke_certificate_dialog', [
            'course' => $course,
            'enrollment' => $enrollment,
            'certificate' => $this->activeCertificate($enrollment),
        ]);
    }

    public function revokeCertificate(Request $request, LearningCourse $course, LearningEnrollment $enrollment, LearningCompletionService $completion): RedirectResponse {
        Gate::authorize('revokeCertificate', $course);
        $this->guardBelongsToCourse($course, $enrollment);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);
        $certificate = $completion->revoke($this->activeCertificate($enrollment), $data['reason'], $this->authUser()->id);

        return redirect()
            ->route('learning.courses.enrollments.index', $course)
            ->with('success', __('learning.flash.certificate_revoked', ['number' => $certificate->number, 'name' => $enrollment->learnerName()]));
    }

    private function activeCertificate(LearningEnrollment $enrollment): LearningCertificate {
        return LearningCertificate::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->whereNull('revoked_at')
            ->firstOrFail();
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
                    'invited_by_user_id' => $this->authUser()->id,
                    'created_at' => now(),
                ]);
        }
    }

    private function guardBelongsToCourse(LearningCourse $course, LearningEnrollment $enrollment): void {
        abort_unless($enrollment->learning_course_id === $course->id, 404);
    }
}
