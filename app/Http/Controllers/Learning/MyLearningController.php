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
use App\Models\{Attachment, CommunicationNote, User};
use App\Models\Learning\{LearningAssignment, LearningCertificate, LearningEnrollment, LearningQuestion, LearningQuiz, LearningQuizAttempt, LearningSubmission, LearningUnit};
use App\Models\Media\MediaRendition;
use App\Services\Ai\Exceptions\AiException;
use App\Services\Attachments\FileAttacher;
use App\Services\Communication\CommunicationNoteService;
use App\Services\Learning\{LearningAiSuggestionService, LearningAssignmentService, LearningCertificatePdfRenderer, LearningEnrollmentService, LearningEventService, LearningGamificationService, LearningQuestionService, LearningQuizService, LearningReportCardPdfRenderer, LearningTimeService, LearningTranslationService};
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

        // Punkte und Abzeichen (MVP-781): nur bei Org-Schalter, Bestenliste
        // zusätzlich nur mit persönlichem Opt-in.
        $gamification = app(LearningGamificationService::class);
        $organization = $this->actor()->organization;
        $gamificationOn = $gamification->isEnabled($organization);

        return view('learning.my.index', [
            'enrollments' => $enrollments,
            // Meine Notizen (MVP-789): private Lernnotizen quer über alle Kurse.
            'notes' => $this->ownNotes()->with('notable.course:id,title')->limit(20)->get(),
            'gamification' => $gamificationOn ? [
                'points' => $gamification->pointsFor($this->actor()),
                'completed' => $gamification->completedCoursesFor($this->actor()),
                'badges' => $gamification->badgesFor($this->actor()),
                'leaderboard' => $gamification->isLeaderboardEnabled($organization),
                'optedIn' => $gamification->hasOptedIn($this->actor()),
            ] : null,
        ]);
    }

    /**
     * Lerntutor (MVP-781): Antwort aus dem freigegebenen Kursinhalt, Fundstelle
     * benannt. Er erklärt — er bewertet nichts und schaltet nichts frei.
     */
    public function tutor(Request $request, LearningEnrollment $enrollment): RedirectResponse {
        $this->authorizeOwn($enrollment);
        $ai = app(LearningAiSuggestionService::class);
        abort_unless($ai->isAvailable($enrollment->organization, LearningAiSuggestionService::CAPABILITY_TUTOR), 404);

        $data = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $answer = $ai->answerLearnerQuestion($enrollment->course()->firstOrFail(), $data['question']);
        } catch (AiException $e) {
            return redirect()->route('learning.my.show', $enrollment)->with('error', $e->getMessage());
        }

        return redirect()
            ->route('learning.my.show', $enrollment)
            ->with('tutorQuestion', $data['question'])
            ->with('tutorAnswer', $answer);
    }

    /** Bestenliste (MVP-781): nur Personen mit Opt-in, nur bei Org-Schalter. */
    public function leaderboard(): View {
        $gamification = app(LearningGamificationService::class);
        $organization = $this->actor()->organization;
        abort_unless($gamification->isLeaderboardEnabled($organization), 404);

        return view('learning.my.leaderboard', [
            'board' => $gamification->leaderboard($organization, 25),
            'optedIn' => $gamification->hasOptedIn($this->actor()),
            'ownPoints' => $gamification->pointsFor($this->actor()),
        ]);
    }

    public function toggleLeaderboard(Request $request): RedirectResponse {
        $gamification = app(LearningGamificationService::class);
        abort_unless($gamification->isLeaderboardEnabled($this->actor()->organization), 404);

        $user = $this->actor();
        $preferences = (array) ($user->preferences ?? []);
        $optIn = (bool) $request->boolean('opt_in');
        $preferences['learning'] = array_merge((array) ($preferences['learning'] ?? []), ['leaderboard_opt_in' => $optIn]);
        $user->forceFill(['preferences' => $preferences])->save();

        return redirect()
            ->route('learning.my.leaderboard')
            ->with('success', __($optIn ? 'learning.flash.leaderboard_opted_in' : 'learning.flash.leaderboard_opted_out'));
    }

    public function show(LearningEnrollment $enrollment): View {
        $this->authorizeOwn($enrollment);

        $enrollment->load(['course.units.section', 'course.units.quiz', 'course.units.assignment', 'course.units.event', 'course.units.scormPackage', 'course.units.cmi5Package.units', 'course.units.ltiLink', 'course.units.attachments', 'course.sections', 'progress']);

        // Mindestverweildauer (MVP-788): das erste Öffnen zählt ab jetzt.
        $this->enrollments->markSeen($enrollment, $enrollment->course->units ?? []);
        $enrollment->load('progress');

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
            'tutorEnabled' => app(LearningAiSuggestionService::class)->isAvailable($enrollment->organization, LearningAiSuggestionService::CAPABILITY_TUTOR),
            // Voraussetzungen (MVP-784): der Start bleibt gesperrt, solange sie fehlen.
            'missingPrerequisites' => $this->enrollments->missingPrerequisites($enrollment),
            'openSession' => $this->time->openSessionFor($enrollment),
            // cmi5: Stand je AU aus der Registrierung dieser Einschreibung.
            'cmi5States' => \App\Models\Learning\LearningCmi5AuState::query()
                ->whereIn('learning_cmi5_registration_id', \App\Models\Learning\LearningCmi5Registration::query()
                    ->where('learning_enrollment_id', $enrollment->id)
                    ->select('id'))
                ->get()
                ->keyBy('learning_cmi5_unit_id'),
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
            // Private Lernnotizen dieser Einschreibung (MVP-789).
            'notes' => $this->ownNotes()->where('notable_id', $enrollment->id)->get(),
            'askRecipients' => app(LearningQuestionService::class)->recipients($enrollment),
            'focusMode' => (bool) ($this->actor()->preferences['learning']['focus_mode'] ?? false),
        ]);
    }

    /**
     * Private Lernnotiz (MVP-789): hängt an der Einschreibung, sieht nur die
     * verfassende Person; die Einheit steht als Betreff — so bleibt die Notiz
     * beim Stoff, ohne dass es eine zweite Notizwelt gibt.
     */
    public function storeNote(Request $request, LearningEnrollment $enrollment): RedirectResponse {
        $this->authorizeOwn($enrollment);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:8000'],
            'unit' => ['nullable', 'string', 'max:40'],
        ]);

        $unit = null;
        if (! empty($data['unit'])) {
            $unitId = \App\Support\Sqid::decodeOrNumeric(LearningUnit::class, (string) $data['unit']);
            $unit = $unitId !== null ? LearningUnit::query()->where('learning_course_id', $enrollment->learning_course_id)->find($unitId) : null;
            abort_if($unit === null, 404);
        }

        app(CommunicationNoteService::class)->create($enrollment, $this->actor(), [
            'type' => \App\Enums\Communication\CommunicationNoteType::Internal->value,
            'direction' => \App\Enums\Communication\CommunicationDirection::Internal->value,
            'occurred_at' => now()->toDateTimeString(),
            'subject' => (string) ($unit->title ?? $enrollment->course->title ?? __('learning.field.note')),
            'body' => $data['body'],
            'visibility' => \App\Enums\Communication\CommunicationVisibility::Private->value,
        ]);

        return redirect()
            ->to(route('learning.my.show', $enrollment) . '#learning-notes')
            ->with('success', __('learning.flash.note_saved'));
    }

    public function destroyNote(LearningEnrollment $enrollment, CommunicationNote $note): RedirectResponse {
        $this->authorizeOwn($enrollment);
        abort_unless(
            $note->notable_type === $enrollment->getMorphClass()
            && (int) $note->notable_id === (int) $enrollment->id
            && (int) $note->created_by_user_id === (int) $this->actor()->id,
            404,
        );

        app(CommunicationNoteService::class)->delete($note, $this->actor());

        return redirect()
            ->to(route('learning.my.show', $enrollment) . '#learning-notes')
            ->with('success', __('learning.flash.note_deleted'));
    }

    /** Fokusmodus (MVP-794): Seitenleiste des Players aus — als Nutzerpräferenz. */
    public function toggleFocus(LearningEnrollment $enrollment): RedirectResponse {
        $this->authorizeOwn($enrollment);

        $user = $this->actor();
        $preferences = (array) ($user->preferences ?? []);
        $current = (bool) ($preferences['learning']['focus_mode'] ?? false);
        data_set($preferences, 'learning.focus_mode', ! $current);
        $user->forceFill(['preferences' => $preferences])->save();

        return redirect()->route('learning.my.show', $enrollment);
    }

    /** Frage an den Trainer (MVP-789): Dialog. */
    public function askCreate(LearningEnrollment $enrollment): View {
        $this->authorizeOwn($enrollment);

        return view('learning.my._ask_dialog', [
            'enrollment' => $enrollment,
            'recipients' => app(LearningQuestionService::class)->recipients($enrollment),
            'viaTicket' => app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.helpdesk'),
        ]);
    }

    public function ask(Request $request, LearningEnrollment $enrollment): RedirectResponse {
        $this->authorizeOwn($enrollment);

        $data = $request->validate([
            'question' => ['required', 'string', 'min:5', 'max:4000'],
        ]);

        $result = app(LearningQuestionService::class)->ask($enrollment, $this->actor(), (string) $data['question']);
        $ticket = $result['ticket'];

        return redirect()
            ->route('learning.my.show', $enrollment)
            ->with('success', $ticket !== null
                ? __('learning.flash.question_ticket', ['number' => (string) $ticket->ticket_no])
                : __('learning.flash.question_sent'));
    }

    /** @return \Illuminate\Database\Eloquent\Builder<CommunicationNote> */
    private function ownNotes() {
        return CommunicationNote::query()
            ->where('notable_type', (new LearningEnrollment)->getMorphClass())
            ->where('created_by_user_id', $this->actor()->id)
            ->where('visibility', \App\Enums\Communication\CommunicationVisibility::Private->value)
            ->orderByDesc('occurred_at');
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
        // Die Ansicht blendet den Knopf für diese Einheiten aus; ohne diese Sperre
        // schlösse ein gezielter POST trotzdem eine Prüfung oder ein Kurspaket ab.
        abort_if($unit->reportsOwnResult(), 403);

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
                // Zwischengespeicherte Antworten (MVP-783) — Wiederaufnahme
                // nach Verbindungsabbruch.
                'drafts' => $attempt->answers()->get()->keyBy('learning_question_id'),
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

        // Aufsatz-Upload (MVP-793): die Datei wird geprüft, der Dateiname wandert
        // in die Antwort (damit „alle Fragen beantwortet" greift), die Datei
        // selbst hängt nach der Abgabe an der Antwort.
        $files = [];
        foreach ($attempt->questions() as $question) {
            $questionId = (int) ($question['id'] ?? 0);
            $file = $request->file('answers.' . $questionId . '.file');
            if ($file instanceof UploadedFile && ($question['kind'] ?? '') === \App\Enums\Learning\LearningQuestionKind::Essay->value) {
                $request->validate(['answers.' . $questionId . '.file' => FileAttacher::rule()]);
                $files[$questionId] = $file;
                $answers[$questionId] = array_merge(is_array($answers[$questionId] ?? null) ? $answers[$questionId] : [], ['file' => $file->getClientOriginalName()]);
            }
        }

        try {
            $this->quizzes->submitAttempt($attempt, $answers);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('learning.my.quiz.show', [$enrollment, $attempt])
                ->withErrors($e->errors());
        }

        foreach ($files as $questionId => $file) {
            $answer = $attempt->answers()->where('learning_question_id', $questionId)->first();
            if ($answer !== null) {
                app(FileAttacher::class)->store($answer, $file, $this->actor()->id, ['organization_id' => $enrollment->organization_id], 'learning-essays');
            }
        }

        return redirect()
            ->route('learning.my.quiz.show', [$enrollment, $attempt])
            ->with('success', __('learning.flash.attempt_submitted'));
    }

    /**
     * Antwort zwischenspeichern (MVP-783) — JSON aus dem Player. Bewertet
     * wird nichts; nach Ablauf wird nichts mehr angenommen.
     */
    public function saveAnswer(Request $request, LearningEnrollment $enrollment, LearningQuizAttempt $attempt): JsonResponse {
        $this->authorizeOwn($enrollment);
        abort_unless($attempt->learning_enrollment_id === $enrollment->id, 404);

        $data = $request->validate([
            'question_id' => ['required', 'integer', 'min:1'],
            'payload' => ['nullable', 'array'],
            'flagged' => ['nullable', 'boolean'],
        ]);

        $answer = $this->quizzes->saveAnswer(
            $attempt,
            (int) $data['question_id'],
            $data['payload'] ?? null,
            $request->has('flagged') ? (bool) $data['flagged'] : null,
        );

        return response()->json([
            'saved' => true,
            'answered' => $answer->payload !== null,
            'flagged' => (bool) $answer->flagged,
        ]);
    }

    /** Die Prüfung muss zum Kurs der Einschreibung gehören. */
    private function guardQuizBelongsToCourse(LearningEnrollment $enrollment, LearningQuiz $quiz): void {
        abort_unless($quiz->unit?->learning_course_id === $enrollment->learning_course_id, 404);
    }

    /** Aufgabe abgeben (MVP-739). Dateien folgen mit der DMS-Anbindung. */
    public function submitAssignment(Request $request, LearningEnrollment $enrollment, LearningAssignment $assignment): RedirectResponse {
        $this->authorizeOwn($enrollment);
        abort_unless($assignment->unit?->learning_course_id === $enrollment->learning_course_id, 404);

        // Dateiregeln der Aufgabe (MVP-788) — nur ZUSÄTZLICH zur Systemregel,
        // nie lockerer: Endungen schneiden die Systemliste, Größe nur kleiner.
        $fileRule = FileAttacher::rule();
        $fileRule[1] = 'max:' . $assignment->maxFileKb(FileAttacher::maxKb());
        if ($assignment->allowedExtensions() !== []) {
            $fileRule[] = 'extensions:' . implode(',', $assignment->allowedExtensions());
        }

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:20000'],
            'files' => ['nullable', 'array', 'max:' . $assignment->maxFiles()],
            'files.*' => $fileRule,
        ], [
            'files.max' => (string) __('learning.errors.too_many_files', ['max' => $assignment->maxFiles()]),
            'files.*.extensions' => (string) __('learning.errors.file_extension_not_allowed', ['allowed' => implode(', ', $assignment->allowedExtensions())]),
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
                        array_intersect_key($translated, \App\Services\Learning\LearningTranslationService::TRANSLATED_BLOCK_KEYS)
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
                    static function (array $block): array {
                        // Galerie (MVP-806): die Alternativtexte dürfen mit, die Bildverweise nicht.
                        if (isset($block['images']) && is_array($block['images'])) {
                            $block['images'] = array_map(static fn (mixed $image): array => ['alt' => (string) (is_array($image) ? ($image['alt'] ?? '') : '')], $block['images']);
                        }

                        return array_diff_key($block, ['attachment_id' => 1]);
                    },
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
    /** Eigenes Zeugnis (MVP-790) — dieselbe Rechnung wie das Notenbuch der Betreuung. */
    public function reportCard(LearningEnrollment $enrollment): Response {
        $this->authorizeOwn($enrollment);

        $renderer = app(LearningReportCardPdfRenderer::class);

        return response($renderer->output($enrollment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $renderer->filename($enrollment) . '"',
        ]);
    }

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
