<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MyLearningController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Enums\Learning\LearningProgressStatus;
use App\Http\Controllers\Controller;
use App\Models\{Attachment, User};
use App\Models\Learning\{LearningAssignment, LearningCertificate, LearningEnrollment, LearningQuestion, LearningQuiz, LearningQuizAttempt, LearningSubmission, LearningUnit};
use App\Models\Media\MediaRendition;
use App\Services\Attachments\FileAttacher;
use App\Services\Learning\{LearningAssignmentService, LearningCertificatePdfRenderer, LearningEnrollmentService, LearningEventService, LearningQuizService, LearningTimeService, LearningTranslationService};
use App\Services\Media\{MediaPresenter, MediaResponder};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request, Response, UploadedFile};
use Illuminate\Support\Facades\{Auth, Storage};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * „Meine Schulungen" und der Lern-Player (Feature 149, MVP-737).
 *
 * Bewusst OHNE Plan-Gate: eine Pflichtunterweisung darf nie an der
 * Lizenzstufe scheitern (siehe Konzept, Abschnitt „Zuschnitt"). Der Zugriff
 * hängt an der eigenen Einschreibung, nicht an einem Recht — deshalb gibt
 * es hier keine Gate-Prüfung gegen ein Permission, sondern die
 * Eigentümer-Prüfung.
 */
class MyLearningController extends Controller {
    public function __construct(
        private readonly LearningEnrollmentService $enrollments,
        private readonly LearningTimeService $time,
        private readonly LearningQuizService $quizzes,
        private readonly LearningAssignmentService $assignments,
        private readonly LearningEventService $events,
    ) {}

    public function index(): View {
        $enrollments = LearningEnrollment::query()
            ->with(['course', 'progress'])
            ->where('user_id', $this->actor()->id)
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->get();

        return view('learning.my.index', [
            'enrollments' => $enrollments,
        ]);
    }

    public function show(LearningEnrollment $enrollment): View {
        $this->authorizeOwn($enrollment);

        $enrollment->load(['course.units.section', 'course.units.quiz', 'course.units.assignment', 'course.units.event', 'course.units.scormPackage', 'course.units.attachments', 'course.sections', 'progress']);

        $completedUnitIds = $enrollment->progress
            ->where('status', LearningProgressStatus::Completed)
            ->pluck('learning_unit_id')
            ->all();

        // Übersetzte Fassung, falls freigegeben UND zum aktuellen Stoffstand
        // (MVP-748). Sonst die Ausgangssprache — lieber verständlich in der
        // falschen Sprache als falsch in der richtigen.
        $translations = app(LearningTranslationService::class);
        $locale = app()->getLocale();

        $translated = [];
        foreach ($enrollment->course->units ?? [] as $unit) {
            $fields = $translations->fieldsFor($unit, $locale);

            if ($fields !== null) {
                $translated[$unit->id] = $fields;
            }
        }

        return view('learning.my.show', [
            'enrollment' => $enrollment,
            'course' => $enrollment->course,
            'completedUnitIds' => $completedUnitIds,
            'openSession' => $this->time->openSessionFor($enrollment),
            'eventParticipations' => \App\Models\EventParticipant::query()
                ->where('user_id', $enrollment->user_id)
                ->whereIn('event_id', $enrollment->course?->units->pluck('event_id')->filter()->all() ?? [])
                ->get()
                ->keyBy('event_id'),
            'submissions' => \App\Models\Learning\LearningSubmission::query()
                ->with('attachments')
                ->where('learning_enrollment_id', $enrollment->id)
                ->get()
                ->keyBy('learning_assignment_id'),
            'timeTotals' => $this->time->secondsByClassification($enrollment),
            'translated' => $translated,
            // Videozustand und Ableitungen je Einheit (Feature 150).
            'mediaState' => $this->mediaStateFor($enrollment),
        ]);
    }

    /**
     * Videozustand je Anhang der Kurseinheiten.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mediaStateFor(LearningEnrollment $enrollment): array {
        $attachments = [];
        $unitOf = [];

        foreach ($enrollment->course->units ?? [] as $unit) {
            foreach ($unit->attachments as $attachment) {
                $attachments[] = $attachment;
                $unitOf[(int) $attachment->id] = $unit;
            }
        }

        return app(MediaPresenter::class)->forAttachments(
            $attachments,
            fn (MediaRendition $rendition): string => route('learning.my.units.rendition', [
                'enrollment' => $enrollment->sqid,
                'unit' => $unitOf[(int) $rendition->attachment_id]->sqid,
                'rendition' => $rendition->sqid,
            ]),
        );
    }

    /** Lernzeit starten — hier greift die Zeitpolitik des Kurses. */
    public function startTime(LearningEnrollment $enrollment): RedirectResponse {
        $this->authorizeOwn($enrollment);

        $this->time->start($enrollment);

        return redirect()
            ->route('learning.my.show', $enrollment)
            ->with('success', __('learning.flash.time_started'));
    }

    /** Lernzeit beenden; außerhalb der Arbeitszeit entsteht ein Nachweis. */
    /**
     * Lebenszeichen des Players (MVP-749).
     *
     * Ohne dieses Signal zählte ein offener Tab, den niemand benutzt, als
     * gearbeitete Zeit — bei Lernzeit außerhalb der Arbeitszeit wäre das
     * eine falsche Angabe in den Zeitkonten.
     */
    public function heartbeat(LearningEnrollment $enrollment): JsonResponse {
        $this->authorizeOwn($enrollment);

        $session = $this->time->openSessionFor($enrollment);

        if ($session === null) {
            return response()->json(['ok' => false], 409);
        }

        $this->time->heartbeat($session);

        return response()->json(['ok' => true]);
    }

    public function stopTime(LearningEnrollment $enrollment): RedirectResponse {
        $this->authorizeOwn($enrollment);

        $session = $this->time->openSessionFor($enrollment);

        if ($session !== null) {
            $this->time->stop($session);
        }

        return redirect()
            ->route('learning.my.show', $enrollment)
            ->with('success', __('learning.flash.time_stopped'));
    }

    /** Einheit als abgeschlossen melden (Abschlusskriterium „bestätigt"). */
    public function completeUnit(Request $request, LearningEnrollment $enrollment, LearningUnit $unit): RedirectResponse {
        $this->authorizeOwn($enrollment);

        $percent = (int) $request->integer('progress_percent', 100);
        $this->enrollments->completeUnit($enrollment, $unit, $percent);

        // Eine laufende Lernzeit endet mit der Einheit — sonst liefe sie
        // unbemerkt weiter und würde Arbeitszeit erfinden.
        $session = $this->time->openSessionFor($enrollment);
        if ($session !== null) {
            $this->time->stop($session);
        }

        return redirect()
            ->route('learning.my.show', $enrollment)
            ->with('success', __('learning.flash.unit_completed'));
    }

    /** Prüfungsversuch starten — Versuchsgrenze und Sperrfrist gelten hier. */
    public function startQuiz(Request $request, LearningEnrollment $enrollment, LearningQuiz $quiz): RedirectResponse {
        $this->authorizeOwn($enrollment);
        $this->guardQuizBelongsToCourse($enrollment, $quiz);

        $attempt = $this->quizzes->startAttempt($enrollment, $quiz, [
            'client_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('learning.my.quiz.show', [$enrollment, $attempt])
            ->with('success', __('learning.flash.attempt_started'));
    }

    /** Laufender Versuch oder Ergebnis — je nach Zustand. */
    public function showQuiz(LearningEnrollment $enrollment, LearningQuizAttempt $attempt): View {
        $this->authorizeOwn($enrollment);
        abort_unless($attempt->learning_enrollment_id === $enrollment->id, 404);

        $quiz = $attempt->quiz;
        abort_if($quiz === null, 404);

        if ($attempt->isOpen()) {
            return view('learning.my.quiz_attempt', [
                'enrollment' => $enrollment,
                'attempt' => $attempt,
                'quiz' => $quiz,
            ]);
        }

        return view('learning.my.quiz_result', [
            'enrollment' => $enrollment,
            'attempt' => $attempt,
            'quiz' => $quiz,
            'answers' => $attempt->answers()->get()->keyBy('learning_question_id'),
        ]);
    }

    public function submitQuiz(Request $request, LearningEnrollment $enrollment, LearningQuizAttempt $attempt): RedirectResponse {
        $this->authorizeOwn($enrollment);
        abort_unless($attempt->learning_enrollment_id === $enrollment->id, 404);

        // Der Docblock bliebe eine Behauptung — die Laufzeitprüfung ist die
        // eigentliche Absicherung gegen manipulierte Eingaben.
        $answers = $request->input('answers', []);
        $answers = is_array($answers) ? $answers : [];

        $this->quizzes->submitAttempt($attempt, $answers);

        return redirect()
            ->route('learning.my.quiz.show', [$enrollment, $attempt])
            ->with('success', __('learning.flash.attempt_submitted'));
    }

    /** Die Prüfung muss zum Kurs der Einschreibung gehören. */
    private function guardQuizBelongsToCourse(LearningEnrollment $enrollment, LearningQuiz $quiz): void {
        abort_unless($quiz->unit?->learning_course_id === $enrollment->learning_course_id, 404);
    }

    /** Aufgabe abgeben (MVP-739). Dateien folgen mit der DMS-Anbindung. */
    public function submitAssignment(Request $request, LearningEnrollment $enrollment, LearningAssignment $assignment): RedirectResponse {
        $this->authorizeOwn($enrollment);
        abort_unless($assignment->unit?->learning_course_id === $enrollment->learning_course_id, 404);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:20000'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => FileAttacher::rule(),
        ]);

        // Dateien hängen am Entwurf, bevor abgegeben wird — sonst prüft der
        // Dienst auf einen Anhang, den es noch nicht gibt.
        $submission = $this->assignments->draftFor($enrollment, $assignment);

        foreach ((array) ($data['files'] ?? []) as $file) {
            if ($file instanceof UploadedFile) {
                app(FileAttacher::class)->store(
                    $submission,
                    $file,
                    $this->actor()->id,
                    ['organization_id' => $enrollment->organization_id],
                    'learning-submissions',
                );
            }
        }

        $this->assignments->submit($enrollment, $assignment, $data['body'] ?? null);

        return redirect()
            ->route('learning.my.show', $enrollment)
            ->with('success', __('learning.flash.submission_sent'));
    }

    /**
     * Kursinhalt zum Offline-Lesen ausliefern (MVP-748).
     *
     * **Warum nicht über den Seiten-Cache:** der Service Worker cacht
     * ausdrücklich keine angemeldeten Seiten (`public/sw.js`) — Kursstoff
     * samt Kopfzeile mit dem Namen der lernenden Person hätte auf einem
     * womöglich geteilten Gerät nichts verloren. Stattdessen liefert dieser
     * Endpunkt die **Inhalte allein**, die der Browser auf ausdrückliche
     * Anforderung in IndexedDB legt und beim Abmelden wieder löscht.
     *
     * **Was NICHT mitgeht:** Prüfungsfragen und Aufgaben. Sie sind
     * online-pflichtig; eine Frage im Gerätespeicher wäre die Lösung gleich
     * mitgeliefert.
     */
    public function offlineBundle(LearningEnrollment $enrollment): JsonResponse {
        $this->authorizeOwn($enrollment);

        $enrollment->load(['course.units.section']);
        $course = $enrollment->course;

        abort_if($course === null, 404);

        $translations = app(LearningTranslationService::class);
        $locale = app()->getLocale();

        $units = [];

        foreach ($course->units as $unit) {
            // Online-Pflicht heißt: nicht ins Gerät.
            if ($unit->kind->requiresOnline()) {
                continue;
            }

            $fields = $translations->fieldsFor($unit, $locale);
            $blocks = $unit->blocks();

            foreach (($fields['blocks'] ?? []) as $translated) {
                $index = (int) ($translated['index'] ?? -1);

                if (isset($blocks[$index])) {
                    $blocks[$index] = array_replace(
                        $blocks[$index],
                        array_intersect_key($translated, ['text' => 1, 'items' => 1])
                    );
                }
            }

            $units[] = [
                'sqid' => $unit->sqid,
                'title' => $fields['title'] ?? $unit->title,
                'section' => $unit->section?->title,
                'kind' => $unit->kind->value,
                // Medienblöcke tragen nur ihre Beschriftung: die Dateien
                // selbst bleiben online, sonst läge Bildmaterial im Gerät.
                'blocks' => array_map(
                    static fn (array $block): array => array_diff_key($block, ['attachment_id' => 1]),
                    $blocks
                ),
            ];
        }

        return response()->json([
            'enrollment' => $enrollment->sqid,
            'course' => [
                'title' => $course->title,
                'subtitle' => $course->subtitle,
                'objectives' => $course->objectives,
            ],
            'locale' => $locale,
            'units' => $units,
            'stored_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Abgeleitete Mediendatei ausliefern (Feature 150).
     *
     * Geprüft gegen **Einheit und eigene Einschreibung** — wie beim
     * Original. Eine Ableitung ist derselbe Inhalt in anderer Auflösung und
     * darf deshalb nicht leichter zugänglich sein als die Quelle.
     */
    public function renditionMedia(LearningEnrollment $enrollment, LearningUnit $unit, MediaRendition $rendition): SymfonyResponse {
        $this->authorizeOwn($enrollment);

        abort_unless($unit->learning_course_id === $enrollment->learning_course_id, 404);

        $attachment = $rendition->attachment;

        abort_if($attachment === null, 404);
        abort_unless(
            $attachment->attachable_type === $unit->getMorphClass()
            && (int) $attachment->attachable_id === (int) $unit->id,
            404
        );

        return app(MediaResponder::class)->rendition($rendition);
    }

    /**
     * Bild einer Bildmarkierungsfrage ausliefern (MVP-738).
     *
     * Geprüft wird gegen den **Versuch**, nicht gegen die Frage allein:
     * sonst wäre die Route ein Leseschlüssel auf jedes Prüfungsbild der
     * Organisation.
     */
    public function questionImage(LearningEnrollment $enrollment, LearningQuizAttempt $attempt, int $question): SymfonyResponse {
        $this->authorizeOwn($enrollment);

        abort_unless($attempt->learning_enrollment_id === $enrollment->id, 404);

        $snapshot = collect($attempt->questions())->firstWhere('id', $question);

        abort_if($snapshot === null, 404);

        $attachmentId = $snapshot['settings']['image_attachment_id'] ?? null;

        abort_if($attachmentId === null, 404);

        $attachment = Attachment::query()->findOrFail((int) $attachmentId);

        // Der Anhang muss zu genau DIESER Frage gehören.
        abort_unless(
            $attachment->attachable_type === (new LearningQuestion())->getMorphClass()
            && (int) $attachment->attachable_id === $question,
            404
        );

        return app(MediaResponder::class)->attachment($attachment);
    }

    /**
     * Medien eines Inhaltsblocks ausliefern (Bild, Datei, Video).
     *
     * Der Anhang muss an **dieser** Lerneinheit hängen und die Einheit zum
     * Kurs der eigenen Einschreibung gehören — sonst wäre die Route ein
     * Leseschlüssel auf jede Datei der Anwendung.
     */
    public function unitMedia(LearningEnrollment $enrollment, LearningUnit $unit, Attachment $attachment): SymfonyResponse {
        $this->authorizeOwn($enrollment);

        abort_unless($unit->learning_course_id === $enrollment->learning_course_id, 404);
        abort_unless(
            $attachment->attachable_type === $unit->getMorphClass()
            && (int) $attachment->attachable_id === (int) $unit->id,
            404
        );

        // Inline: Bilder und Videos sollen im Kurs erscheinen, nicht als
        // Download-Dialog.
        return app(MediaResponder::class)->attachment($attachment);
    }

    /**
     * Eigene Abgabedatei herunterladen — nur aus der eigenen Einschreibung.
     *
     * Der Anhang muss zu **dieser** Abgabe gehören: sonst wäre die Route ein
     * Leseschlüssel auf jede Datei der Anwendung.
     */
    public function submissionFile(LearningEnrollment $enrollment, LearningSubmission $submission, Attachment $attachment): SymfonyResponse {
        $this->authorizeOwn($enrollment);

        abort_unless($submission->learning_enrollment_id === $enrollment->id, 404);
        abort_unless(
            $attachment->attachable_type === $submission->getMorphClass()
            && (int) $attachment->attachable_id === (int) $submission->id,
            404
        );

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    /** Zu einem Präsenztermin anmelden (oder auf die Warteliste). */
    public function registerEvent(LearningEnrollment $enrollment, LearningUnit $unit): RedirectResponse {
        $this->authorizeOwn($enrollment);
        abort_unless($unit->learning_course_id === $enrollment->learning_course_id, 404);

        $participant = $this->events->register($enrollment, $unit);
        $waitlisted = $participant->status === \App\Enums\Event\ParticipantStatus::Waitlisted;

        return redirect()
            ->route('learning.my.show', $enrollment)
            ->with('success', __($waitlisted ? 'learning.flash.event_waitlisted' : 'learning.flash.event_registered'));
    }

    public function cancelEvent(LearningEnrollment $enrollment, LearningUnit $unit): RedirectResponse {
        $this->authorizeOwn($enrollment);
        abort_unless($unit->learning_course_id === $enrollment->learning_course_id, 404);

        $this->events->cancel($enrollment, $unit);

        return redirect()
            ->route('learning.my.show', $enrollment)
            ->with('success', __('learning.flash.event_cancelled'));
    }

    /**
     * Zertifikat der eigenen Einschreibung als PDF.
     *
     * Der Ausdruck ist eine **Kopie** — maßgeblich bleibt der Datensatz mit
     * seinem Prüfcode; deshalb trägt jedes Blatt die Prüfadresse.
     */
    public function certificate(LearningEnrollment $enrollment): Response {
        $this->authorizeOwn($enrollment);

        $certificate = LearningCertificate::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->latest('issued_on')
            ->first();

        abort_if($certificate === null, 404);

        $renderer = app(LearningCertificatePdfRenderer::class);

        return response($renderer->output($certificate), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $renderer->filename($certificate) . '"',
        ]);
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /** Eigene Einschreibung — fremde sind nicht sichtbar, auch nicht für Admins. */
    private function authorizeOwn(LearningEnrollment $enrollment): void {
        abort_unless($enrollment->user_id === $this->actor()->id, 404);
    }
}
