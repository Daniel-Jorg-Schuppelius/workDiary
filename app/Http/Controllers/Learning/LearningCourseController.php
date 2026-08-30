<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCourseController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Enums\Learning\{LearningAccessKind, LearningAudience, LearningBlockKind, LearningCourseStatus, LearningFeedbackMode, LearningInstructionSuitability, LearningQuestionKind, LearningTimePolicy, LearningUnitKind};
use App\Enums\Media\MediaRenditionKind;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\{Attachment, User};
use App\Models\Learning\{LearningAssignment, LearningContentTranslation, LearningCourse, LearningQuestion, LearningQuiz, LearningSection, LearningUnit};
use App\Models\Media\MediaRendition;
use App\Models\Training\TrainingCourse;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Ai\Exceptions\AiException;
use App\Services\Attachments\FileAttacher;
use App\Services\Learning\{LearningAttendanceListPdfRenderer, LearningContentService, LearningCoursePortabilityService, LearningCourseService, LearningTranslationService};
use App\Services\Media\VideoTranscodingService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request, Response, UploadedFile};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Lernkurse (Feature 149, MVP-735): Katalogliste mit Modal-CRUD und die
 * Kursakte mit Struktur und Versionen.
 *
 * Der Controller entscheidet nichts fachlich — Freigabe, Inhaltssperre und
 * die Spiegelung in den Trainingskatalog (145) liegen im
 * {@see LearningCourseService}.
 */
class LearningCourseController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly LearningCourseService $courses,
        private readonly LearningContentService $content,
        private readonly LearningCoursePortabilityService $portability,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', LearningCourse::class);

        $status = $request->query('status');
        $status = is_string($status) ? LearningCourseStatus::tryFrom($status) : null;

        $query = LearningCourse::query()
            ->with('trainingCourse')
            ->withCount('units')
            ->orderBy('title');

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return view('learning.courses.index', [
            'courses' => $query->paginate(30)->withQueryString(),
            'status' => $status,
            'releasedCount' => LearningCourse::query()->released()->count(),
            'canCreate' => Gate::allows('create', LearningCourse::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', LearningCourse::class);

        return view('learning.courses._form_dialog', [
            'course' => null,
            'trainingCourses' => $this->trainingCourseOptions(),
            'assets' => $this->assetOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', LearningCourse::class);

        /** @var User $actor */
        $actor = Auth::user();
        $course = $this->courses->createCourse($this->currentOrganization(), $actor, $this->validated($request));

        return redirect()
            ->route('learning.courses.show', $course->sqid)
            ->with('success', __('learning.flash.created'));
    }

    public function show(LearningCourse $course): View {
        Gate::authorize('view', $course);

        $course->load([
            'sections',
            'units.section',
            'versions' => fn ($q) => $q->orderByDesc('version'),
            'trainingCourse',
            'owner',
        ]);

        $translations = \App\Models\Learning\LearningContentTranslation::query()
            ->where('translatable_type', $course->getMorphClass())
            ->where('translatable_id', $course->id)
            ->orderBy('locale')
            ->get();

        return view('learning.courses.show', [
            'translations' => $translations,
            'sourceHash' => app(LearningTranslationService::class)->sourceHash($course),
            'locales' => array_values(array_filter((array) config('app.available_locales', ['de', 'en']))),
            'course' => $course,
            'canEditContent' => Gate::allows('update', $course),
            'canEditMeta' => Gate::allows('updateMeta', $course),
            'canRelease' => Gate::allows('release', $course),
            'canDelete' => Gate::allows('delete', $course),
        ]);
    }

    public function edit(LearningCourse $course): View {
        Gate::authorize('updateMeta', $course);

        return view('learning.courses._form_dialog', [
            'course' => $course,
            'trainingCourses' => $this->trainingCourseOptions(),
            'assets' => $this->assetOptions(),
        ]);
    }

    public function update(Request $request, LearningCourse $course): RedirectResponse {
        Gate::authorize('updateMeta', $course);

        $this->courses->updateCourse($course, $this->validated($request, $course));

        return redirect()->back()->with('success', __('learning.flash.updated'));
    }

    public function destroy(LearningCourse $course): RedirectResponse {
        Gate::authorize('delete', $course);

        $course->delete();

        return redirect()
            ->route('learning.courses.index')
            ->with('success', __('learning.flash.deleted'));
    }

    public function createSection(LearningCourse $course): View {
        Gate::authorize('update', $course);

        return view('learning.courses._section_dialog', ['course' => $course]);
    }

    public function createUnit(LearningCourse $course): View {
        Gate::authorize('update', $course);

        return view('learning.courses._unit_dialog', [
            'course' => $course,
            'sections' => $course->sections()->get(),
        ]);
    }

    /** Abschnitt anlegen (Struktur-Editor der Kursakte). */
    public function storeSection(Request $request, LearningCourse $course): RedirectResponse {
        Gate::authorize('update', $course);

        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->courses->addSection($course, $data);

        return redirect()->back()->with('success', __('learning.flash.section_added'));
    }

    /** Lerneinheit anlegen. Inhaltsblöcke folgen mit dem Editor (MVP-736). */
    public function storeUnit(Request $request, LearningCourse $course): RedirectResponse {
        Gate::authorize('update', $course);

        if ($request->filled('learning_section_id')) {
            $request->merge(['learning_section_id' => Sqid::decodeOrNumeric(LearningSection::class, $request->input('learning_section_id'))]);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'kind' => ['required', Rule::enum(LearningUnitKind::class)],
            'learning_section_id' => ['nullable', 'integer'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'points' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_mandatory' => ['nullable', 'boolean'],
        ]);

        $section = null;
        if (! empty($data['learning_section_id'])) {
            $section = LearningSection::query()
                ->where('learning_course_id', $course->id)
                ->find($data['learning_section_id']);
        }

        $this->courses->addUnit($course, [
            'title' => $data['title'],
            'kind' => $data['kind'],
            'section' => $section,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'points' => $data['points'] ?? 0,
            'is_mandatory' => (bool) ($data['is_mandatory'] ?? true),
        ]);

        return redirect()->back()->with('success', __('learning.flash.unit_added'));
    }

    /** Editor einer Lerneinheit: Stammdaten und Inhaltsblöcke (MVP-736). */
    public function editUnit(LearningCourse $course, LearningUnit $unit): View {
        Gate::authorize('update', $course);
        $this->guardUnitBelongsToCourse($course, $unit);

        $unit->load('attachments');

        // Untertitelspuren je Video (Feature 150): der Editor muss zeigen,
        // welche Spur maschinell ist und noch auf Durchsicht wartet.
        $subtitles = MediaRendition::query()
            ->whereIn('attachment_id', $unit->attachments->pluck('id')->all())
            ->where('kind', MediaRenditionKind::Subtitle->value)
            ->orderBy('locale')
            ->get()
            ->groupBy('attachment_id');

        return view('learning.courses.unit_editor', [
            'course' => $course,
            'unit' => $unit,
            'blocks' => $unit->blocks(),
            'sections' => $course->sections()->get(),
            'allowedHosts' => $this->content->allowedHosts($this->currentOrganization()),
            'subtitles' => $subtitles,
            'canTranscribe' => app(VideoTranscodingService::class)->isTranscriptionAvailable(),
        ]);
    }

    /** Stammdaten der Einheit inklusive Freischaltregel. */
    public function updateUnit(Request $request, LearningCourse $course, LearningUnit $unit): RedirectResponse {
        Gate::authorize('update', $course);
        $this->guardUnitBelongsToCourse($course, $unit);

        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'points' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_mandatory' => ['nullable', 'boolean'],
            'release_after_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ]);

        $releaseAfter = $data['release_after_days'] ?? null;

        $unit->update([
            'title' => $data['title'],
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'points' => max(0, (int) ($data['points'] ?? 0)),
            'is_mandatory' => (bool) ($data['is_mandatory'] ?? false),
            // Freischaltplan (Drip): Tage ab Einschreibung, NULL = sofort.
            'release_rule' => $releaseAfter !== null ? ['after_days' => (int) $releaseAfter] : null,
        ]);

        return redirect()->back()->with('success', __('learning.flash.unit_updated'));
    }

    public function storeBlock(Request $request, LearningCourse $course, LearningUnit $unit): RedirectResponse {
        Gate::authorize('update', $course);
        $this->guardUnitBelongsToCourse($course, $unit);

        $data = $request->validate([
            'type' => ['required', Rule::enum(LearningBlockKind::class)],
            'text' => ['nullable', 'string', 'max:5000'],
            'tone' => ['nullable', 'string', 'in:info,warning,success,error'],
            'items' => ['nullable', 'string', 'max:5000'],
            'url' => ['nullable', 'string', 'url', 'max:2000'],
            'caption' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
            'require_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'media' => array_merge(['nullable'], FileAttacher::rule()),
        ]);

        $kind = LearningBlockKind::from($data['type']);
        $media = $request->file('media');

        // Bild-, Datei- und Videoblöcke tragen ihre Quelle als Anhang der
        // Lerneinheit — ohne Upload bliebe `attachment_id` für immer leer und
        // der Block im Kurs unsichtbar.
        if ($media instanceof UploadedFile) {
            /** @var User $uploader */
            $uploader = Auth::user();

            $attachment = app(FileAttacher::class)->store(
                $unit,
                $media,
                $uploader->id,
                ['organization_id' => $unit->organization_id],
                'learning-content',
            );

            $data['attachment_id'] = $attachment->id;

            // Videos werden umgerechnet (Feature 150): Handy-Aufnahmen sind
            // groß und liegen oft in Formaten vor, die nicht jeder Browser
            // spielt. Der Aufruf läuft in der Warteschlange — ffmpeg auf
            // demselben Server darf den Request nicht blockieren.
            if ($kind === LearningBlockKind::Video && str_starts_with((string) $attachment->mime, 'video/')) {
                $attachment->forceFill(['media_state' => \App\Enums\Media\MediaState::Pending])->save();

                \App\Jobs\TranscodeVideoJob::dispatch($attachment->id);
            }
        }

        // Ein Bild ohne Alternativtext ist für Menschen, die es nicht sehen
        // können, nicht vorhanden (BFSG/WCAG 1.1.1).
        if ($kind === LearningBlockKind::Image && trim((string) ($data['alt'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'alt' => (string) __('learning.errors.image_alt_required'),
            ]);
        }

        $this->content->appendBlock($unit, $kind, $data);

        return redirect()->back()->with('success', __('learning.flash.block_added'));
    }

    public function destroyBlock(LearningCourse $course, LearningUnit $unit, int $index): RedirectResponse {
        Gate::authorize('update', $course);
        $this->guardUnitBelongsToCourse($course, $unit);

        $this->content->removeBlock($unit, $index);

        return redirect()->back()->with('success', __('learning.flash.block_removed'));
    }

    public function moveBlock(Request $request, LearningCourse $course, LearningUnit $unit, int $index): RedirectResponse {
        Gate::authorize('update', $course);
        $this->guardUnitBelongsToCourse($course, $unit);

        $this->content->moveBlock($unit, $index, $request->input('direction') === 'up' ? -1 : 1);

        return redirect()->back();
    }

    private function guardUnitBelongsToCourse(LearningCourse $course, LearningUnit $unit): void {
        abort_unless($unit->learning_course_id === $course->id, 404);
    }

    /** Prüfungs-Editor einer Quiz-Einheit (MVP-738). */
    public function editQuiz(LearningCourse $course, LearningUnit $unit): View {
        Gate::authorize('update', $course);
        $this->guardUnitBelongsToCourse($course, $unit);

        return view('learning.courses.quiz_editor', [
            'course' => $course,
            'unit' => $unit,
            'quiz' => $unit->quiz()->with('questions.options')->first(),
        ]);
    }

    /** Prüfungseinstellungen speichern; legt die Prüfung bei Bedarf an. */
    public function updateQuiz(Request $request, LearningCourse $course, LearningUnit $unit): RedirectResponse {
        Gate::authorize('update', $course);
        $this->guardUnitBelongsToCourse($course, $unit);

        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'pass_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'max_attempts' => ['required', 'integer', 'min:0', 'max:50'],
            'retry_wait_hours' => ['required', 'integer', 'min:0', 'max:8760'],
            'questions_per_attempt' => ['nullable', 'integer', 'min:1', 'max:500'],
            'shuffle_questions' => ['nullable', 'boolean'],
            'shuffle_answers' => ['nullable', 'boolean'],
            'feedback_mode' => ['required', Rule::enum(LearningFeedbackMode::class)],
            'show_solutions' => ['nullable', 'boolean'],
        ]);

        LearningQuiz::query()->updateOrCreate(
            ['learning_unit_id' => $unit->id],
            [
                'organization_id' => $course->organization_id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'pass_percent' => (int) $data['pass_percent'],
                'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
                'max_attempts' => (int) $data['max_attempts'],
                'retry_wait_hours' => (int) $data['retry_wait_hours'],
                'questions_per_attempt' => $data['questions_per_attempt'] ?? null,
                'shuffle_questions' => (bool) ($data['shuffle_questions'] ?? false),
                'shuffle_answers' => (bool) ($data['shuffle_answers'] ?? false),
                'feedback_mode' => $data['feedback_mode'],
                'show_solutions' => (bool) ($data['show_solutions'] ?? false),
            ]
        );

        return redirect()->back()->with('success', __('learning.flash.quiz_saved'));
    }

    /**
     * Frage anlegen. Optionen kommen als Zeilenliste: eine Zeile je Option,
     * ein führendes `*` markiert die richtige — kompakt und ohne JavaScript.
     */
    public function storeQuestion(Request $request, LearningCourse $course, LearningUnit $unit): RedirectResponse {
        Gate::authorize('update', $course);
        $this->guardUnitBelongsToCourse($course, $unit);

        $quiz = $unit->quiz;
        abort_if($quiz === null, 404);

        $data = $request->validate([
            'kind' => ['required', Rule::enum(LearningQuestionKind::class)],
            'prompt' => ['required', 'string', 'min:2', 'max:2000'],
            'explanation' => ['nullable', 'string', 'max:2000'],
            'points' => ['required', 'integer', 'min:1', 'max:100'],
            'options' => ['nullable', 'string', 'max:5000'],
            'partial_credit' => ['nullable', 'boolean'],
            'case_sensitive' => ['nullable', 'boolean'],
            'image' => array_merge(['nullable'], FileAttacher::rule()),
        ]);

        $kind = LearningQuestionKind::from($data['kind']);
        $lines = $this->linesOf($data['options'] ?? '');

        $settings = [];
        if ($kind === LearningQuestionKind::Multiple) {
            $settings['partial_credit'] = (bool) ($data['partial_credit'] ?? false);
        }
        if (in_array($kind, [LearningQuestionKind::ShortText, LearningQuestionKind::Cloze], true)) {
            $settings['case_sensitive'] = (bool) ($data['case_sensitive'] ?? false);
        }
        if ($kind === LearningQuestionKind::ShortText) {
            // Freitext: jede Zeile ist eine akzeptierte Lösung.
            $settings['answers'] = array_map(static fn (string $line): string => ltrim($line, '*'), $lines);
        }
        if ($kind === LearningQuestionKind::Hotspot) {
            // Bildmarkierung: eine Zeile je Trefferfläche, „x,y,b,h" in
            // Prozent der Bildkante. Prozent statt Pixel, sonst hinge die
            // richtige Antwort an der Anzeigegröße.
            $settings['hotspots'] = $this->parseHotspots($lines);
        }
        if ($kind === LearningQuestionKind::Matrix) {
            // Matrix: „Zeile = Spalte" je Zeile. Anders als bei der
            // Zuordnung darf dieselbe Spalte mehrfach vorkommen.
            $settings = array_merge($settings, $this->parseMatrix($lines));
        }
        if ($kind === LearningQuestionKind::Cloze) {
            // Lückentext: eine Zeile je Lücke, Alternativen mit „|" getrennt.
            // Der Bewerter liest `gaps` — schrieb der Editor hier `answers`,
            // blieben die Lücken leer und die Frage gäbe immer null Punkte.
            $settings['gaps'] = array_map(
                static fn (string $line): array => array_values(array_filter(
                    array_map('trim', explode('|', $line)),
                    static fn (string $part): bool => $part !== ''
                )),
                $lines
            );
        }

        $question = LearningQuestion::query()->create([
            'organization_id' => $course->organization_id,
            'learning_quiz_id' => $quiz->id,
            'kind' => $kind->value,
            'prompt' => $data['prompt'],
            'explanation' => $data['explanation'] ?? null,
            'points' => (int) $data['points'],
            'position' => (int) $quiz->questions()->max('position') + 1,
            'settings' => $settings !== [] ? $settings : null,
        ]);

        $image = $request->file('image');

        if ($kind->needsImage() && $image instanceof UploadedFile) {
            /** @var User $uploader */
            $uploader = Auth::user();

            $attachment = app(FileAttacher::class)->store(
                $question,
                $image,
                $uploader->id,
                ['organization_id' => $course->organization_id],
                'learning-questions',
            );

            $settings['image_attachment_id'] = $attachment->id;
            $question->forceFill(['settings' => $settings])->save();
        }

        if ($kind === LearningQuestionKind::Matching) {
            // Zuordnung: eine Zeile je Paar, „links = rechts". Beide Seiten
            // werden Optionen mit gemeinsamem `match_key` — ohne den findet
            // der Bewerter kein einziges Paar.
            $this->storeMatchingOptions($question, $course->organization_id, $lines);
        } elseif ($kind->needsOptions()) {
            foreach ($lines as $index => $line) {
                $isCorrect = str_starts_with($line, '*');
                $question->options()->create([
                    'organization_id' => $course->organization_id,
                    'label' => trim(ltrim($line, '*')),
                    'is_correct' => $isCorrect,
                    'position' => $index + 1,
                ]);
            }
        }

        return redirect()->back()->with('success', __('learning.flash.question_added'));
    }

    public function destroyQuestion(LearningCourse $course, LearningUnit $unit, LearningQuestion $question): RedirectResponse {
        Gate::authorize('update', $course);
        $this->guardUnitBelongsToCourse($course, $unit);
        abort_unless($question->learning_quiz_id === $unit->quiz?->id, 404);

        $question->delete();

        return redirect()->back();
    }

    /**
     * Trefferflächen einer Bildmarkierung: „x,y,breite,höhe" in Prozent,
     * ein führendes `*` markiert die **richtige** Fläche — dieselbe
     * Konvention wie bei den Antwortoptionen.
     *
     * Es müssen auch falsche Flächen hinterlegt werden können: sonst wäre
     * die Tastatur-Auswahlliste eine Liste ausschließlich richtiger
     * Antworten und damit wertlos.
     *
     * Zeilen, die keine vier Zahlen ergeben, werden übergangen — eine halbe
     * Fläche ist keine Fläche.
     *
     * @param  list<string>  $lines
     * @return list<array{x: float, y: float, w: float, h: float, is_correct: bool, label: string}>
     */
    private function parseHotspots(array $lines): array {
        $spots = [];

        foreach ($lines as $index => $line) {
            $correct = str_starts_with($line, '*');
            $rest = trim(ltrim($line, '*'));

            // Optionale Beschriftung nach einem Doppelpunkt: „10,20,30,15: Sicherungskasten"
            [$coords, $label] = array_pad(explode(':', $rest, 2), 2, null);

            $parts = array_map('trim', preg_split('/[,;]/', (string) $coords) ?: []);

            if (count($parts) < 4) {
                continue;
            }

            $values = array_map(static fn (string $p): float => (float) str_replace(',', '.', $p), array_slice($parts, 0, 4));

            if ($values[2] <= 0 || $values[3] <= 0) {
                continue;
            }

            $spots[] = [
                'x' => $values[0],
                'y' => $values[1],
                'w' => $values[2],
                'h' => $values[3],
                'is_correct' => $correct,
                'label' => trim((string) $label) !== '' ? trim((string) $label) : (string) ($index + 1),
            ];
        }

        return $spots;
    }

    /**
     * Matrix aus „Zeile = Spalte"-Zeilen. Die Spalten ergeben sich aus den
     * rechten Seiten in der Reihenfolge ihres ersten Auftretens — dieselbe
     * Spalte darf mehrfach genannt werden.
     *
     * @param  list<string>  $lines
     * @return array{rows: list<array{label: string, column: int}>, columns: list<string>}
     */
    private function parseMatrix(array $lines): array {
        $columns = [];
        $rows = [];

        foreach ($lines as $line) {
            $parts = array_map('trim', explode('=', $line, 2));

            if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }

            $index = array_search($parts[1], $columns, true);

            if ($index === false) {
                $columns[] = $parts[1];
                $index = count($columns) - 1;
            }

            $rows[] = ['label' => $parts[0], 'column' => (int) $index];
        }

        return ['rows' => $rows, 'columns' => $columns];
    }

    /**
     * Zuordnungspaare anlegen: „links = rechts" je Zeile. Beide Optionen
     * teilen sich einen `match_key`; Zeilen ohne „=" werden übergangen,
     * weil eine halbe Zuordnung nichts zuordnet.
     *
     * @param  list<string>  $lines
     */
    private function storeMatchingOptions(LearningQuestion $question, int $organizationId, array $lines): void {
        $position = 0;

        foreach ($lines as $index => $line) {
            $parts = array_map('trim', explode('=', $line, 2));

            if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }

            $key = 'p' . ($index + 1);

            foreach ($parts as $label) {
                $question->options()->create([
                    'organization_id' => $organizationId,
                    'label' => $label,
                    'is_correct' => true,
                    'match_key' => $key,
                    'position' => ++$position,
                ]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function linesOf(string $text): array {
        $lines = preg_split('/\R/', $text) ?: [];

        return array_values(array_filter(array_map('trim', $lines), static fn (string $l): bool => $l !== ''));
    }

    /** Aufgabe einer Einheit pflegen (MVP-739). */
    public function editAssignment(LearningCourse $course, LearningUnit $unit): View {
        Gate::authorize('update', $course);
        $this->guardUnitBelongsToCourse($course, $unit);

        return view('learning.courses.assignment_editor', [
            'course' => $course,
            'unit' => $unit,
            'assignment' => $unit->assignment,
        ]);
    }

    /**
     * Die Rubrik wird als Zeilenliste gepflegt: `Schlüssel | Bezeichnung |
     * Punkte` je Zeile — erklärbar ohne JavaScript.
     */
    public function updateAssignment(Request $request, LearningCourse $course, LearningUnit $unit): RedirectResponse {
        Gate::authorize('update', $course);
        $this->guardUnitBelongsToCourse($course, $unit);

        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'instructions' => ['nullable', 'string', 'max:20000'],
            'submission_kind' => ['required', 'string', 'in:text,file,both'],
            'due_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'points' => ['required', 'integer', 'min:1', 'max:1000'],
            'pass_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'rubric' => ['nullable', 'string', 'max:5000'],
            'requires_second_opinion' => ['nullable', 'boolean'],
        ]);

        LearningAssignment::query()->updateOrCreate(
            ['learning_unit_id' => $unit->id],
            [
                'organization_id' => $course->organization_id,
                'title' => $data['title'],
                'instructions' => $data['instructions'] ?? null,
                'submission_kind' => $data['submission_kind'],
                'due_days' => $data['due_days'] ?? null,
                'points' => (int) $data['points'],
                'pass_percent' => (int) $data['pass_percent'],
                'rubric' => $this->parseRubric($data['rubric'] ?? ''),
                'requires_second_opinion' => (bool) ($data['requires_second_opinion'] ?? false),
            ]
        );

        return redirect()->back()->with('success', __('learning.flash.assignment_saved'));
    }

    /**
     * @return list<array{key: string, label: string, weight: int, max_points: int}>|null
     */
    private function parseRubric(string $text): ?array {
        $rows = [];
        foreach ($this->linesOf($text) as $index => $line) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 2) {
                continue;
            }
            $rows[] = [
                'key' => $parts[0] !== '' ? $parts[0] : 'k' . ($index + 1),
                'label' => $parts[1],
                'weight' => 1,
                'max_points' => isset($parts[2]) ? max(0, (int) $parts[2]) : 10,
            ];
        }

        return $rows !== [] ? $rows : null;
    }

    /** Kurs als JSON exportieren — Lehrmaterial, keine Nachweise. */
    public function exportCourse(LearningCourse $course): \Symfony\Component\HttpFoundation\Response {
        Gate::authorize('view', $course);

        $payload = $this->portability->export($course);
        $filename = 'kurs-' . $course->code . '.json';

        return response()->streamDownload(
            static function () use ($payload): void {
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            },
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }

    /** Kurs aus einer Exportdatei übernehmen — immer als Entwurf. */
    public function importCourse(Request $request): RedirectResponse {
        Gate::authorize('create', LearningCourse::class);

        $request->validate([
            'file' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:5120'],
        ]);

        $raw = (string) file_get_contents($request->file('file')->getRealPath());
        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            return redirect()->back()->withErrors(['file' => __('learning.errors.import_format')]);
        }

        /** @var User $actor */
        $actor = Auth::user();
        $course = $this->portability->import($this->currentOrganization(), $payload, $actor);

        return redirect()
            ->route('learning.courses.show', $course->sqid)
            ->with('success', __('learning.flash.imported'));
    }

    public function submitReview(LearningCourse $course): RedirectResponse {
        Gate::authorize('update', $course);

        $this->courses->submitForReview($course);

        return redirect()->back()->with('success', __('learning.flash.review_requested'));
    }

    public function release(Request $request, LearningCourse $course): RedirectResponse {
        Gate::authorize('release', $course);

        $label = $request->string('label')->trim()->value();

        /** @var User $actor */
        $actor = Auth::user();
        $version = $this->courses->release($course, $actor, $label !== '' ? $label : null);

        return redirect()->back()->with('success', __('learning.flash.released', ['version' => $version->version]));
    }

    public function reopen(LearningCourse $course): RedirectResponse {
        Gate::authorize('update', $course);

        $this->courses->reopen($course);

        return redirect()->back()->with('success', __('learning.flash.reopened'));
    }

    public function archive(LearningCourse $course): RedirectResponse {
        Gate::authorize('archive', $course);

        $this->courses->archive($course);

        return redirect()->back()->with('success', __('learning.flash.archived'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?LearningCourse $course = null): array {
        // Das Formular sendet Sqids; numerische IDs bleiben für Alt-Aufrufer gültig.
        if ($request->filled('training_course_id')) {
            $request->merge(['training_course_id' => Sqid::decodeOrNumeric(TrainingCourse::class, $request->input('training_course_id'))]);
        }

        if ($request->filled('asset_id')) {
            $request->merge(['asset_id' => Sqid::decodeOrNumeric(\App\Models\Asset::class, $request->input('asset_id'))]);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'code' => ['nullable', 'string', 'max:60'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'objectives' => ['nullable', 'string', 'max:5000'],
            'audiences' => ['nullable', 'array'],
            'audiences.*' => [Rule::enum(LearningAudience::class)],
            'access_kind' => ['required', Rule::enum(LearningAccessKind::class)],
            'time_policy' => ['required', Rule::enum(LearningTimePolicy::class)],
            'instruction_suitability' => ['required', Rule::enum(LearningInstructionSuitability::class)],
            'training_course_id' => ['nullable', new ExistsInCurrentOrganization('training_courses')],
            // Geräteeinweisung (MVP-740): Zeiger auf das Gerät, an dem
            // unterwiesen wird — kein Guard, nur der Nachweis.
            'asset_id' => ['nullable', new ExistsInCurrentOrganization('assets')],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'validity_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'points' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'certificate_enabled' => ['nullable', 'boolean'],
            'sequential' => ['nullable', 'boolean'],
            'access_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $data['certificate_enabled'] = (bool) ($data['certificate_enabled'] ?? false);
        $data['sequential'] = (bool) ($data['sequential'] ?? false);
        $data['audiences'] = $data['audiences'] ?? [LearningAudience::Internal->value];

        // Der Code wird nur beim Anlegen vergeben — er ist der Anker des Kurses.
        if ($course !== null) {
            unset($data['code']);
        }

        return $data;
    }

    /**
     * @return \Illuminate\Support\Collection<int, TrainingCourse>
     */
    private function trainingCourseOptions() {
        return TrainingCourse::query()
            ->active()
            ->orderBy('title')
            ->get(['id', 'title', 'code']);
    }

    /**
     * Untertitelspur zu einem Video hinterlegen (Feature 150).
     *
     * **Von Hand als WebVTT.** Eine maschinell erzeugte Spur ist erst nach
     * menschlicher Durchsicht ein Barrierefreiheitsnachweis (WCAG 1.2.2) —
     * und beim Verkauf an Verbraucher ist der Pflicht, nicht Kür.
     */
    public function storeSubtitle(Request $request, LearningCourse $course, LearningUnit $unit, Attachment $attachment): RedirectResponse {
        Gate::authorize('update', $course);
        abort_unless($unit->learning_course_id === $course->id, 404);
        abort_unless(
            $attachment->attachable_type === $unit->getMorphClass()
            && (int) $attachment->attachable_id === (int) $unit->id,
            404
        );

        $data = $request->validate([
            // Gegen die eingerichteten Sprachen: eine Spur in einer Sprache,
            // die es in der Anwendung nicht gibt, kann niemand auswählen.
            'locale' => ['required', 'string', Rule::in($this->availableLocales())],
            'vtt' => ['required', 'file', 'max:2048'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $data['vtt'];

        app(VideoTranscodingService::class)->attachSubtitle(
            $attachment,
            (string) file_get_contents($file->getRealPath()),
            (string) $data['locale'],
        );

        return redirect()
            ->route('learning.courses.units.edit', ['course' => $course->sqid, 'unit' => $unit->sqid])
            ->with('success', __('media.flash.subtitle_added'));
    }

    /**
     * Untertitelspur maschinell erzeugen lassen (Feature 150).
     *
     * Whisper läuft **lokal** auf demselben Server; es verlässt kein Byte das
     * Haus. Das Ergebnis ist ein Entwurf und wird als solcher gekennzeichnet:
     * erst die Durchsicht macht daraus einen Nachweis nach WCAG 1.2.2.
     */
    public function transcribeSubtitle(Request $request, LearningCourse $course, LearningUnit $unit, Attachment $attachment): RedirectResponse {
        Gate::authorize('update', $course);
        $this->assertUnitAttachment($course, $unit, $attachment);

        $data = $request->validate([
            // Der Wert geht als Sprachvorgabe an die Erkennung; ein Code, den
            // Whisper nicht kennt, lässt den Job erst nach Minuten scheitern.
            'locale' => ['required', 'string', Rule::in($this->availableLocales())],
        ]);

        $back = redirect()->route('learning.courses.units.edit', ['course' => $course->sqid, 'unit' => $unit->sqid]);

        if (! app(VideoTranscodingService::class)->isTranscriptionAvailable()) {
            return $back->with('error', __('media.errors.whisper_missing'));
        }

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        \App\Jobs\TranscribeSubtitleJob::dispatch(
            (int) $attachment->id,
            (string) $data['locale'],
            (int) $actor->id,
            route('learning.courses.units.edit', ['course' => $course->sqid, 'unit' => $unit->sqid]),
        );

        return $back->with('success', __('media.flash.transcription_queued'));
    }

    /** Maschinelle Untertitelspur nach Durchsicht freigeben (Feature 150). */
    public function reviewSubtitle(Request $request, LearningCourse $course, LearningUnit $unit, MediaRendition $rendition): RedirectResponse {
        Gate::authorize('update', $course);
        $this->assertUnitSubtitle($course, $unit, $rendition);

        $reviewer = $request->user();
        abort_unless($reviewer instanceof User, 403);

        app(VideoTranscodingService::class)->markSubtitleReviewed($rendition, $reviewer);

        return redirect()
            ->route('learning.courses.units.edit', ['course' => $course->sqid, 'unit' => $unit->sqid])
            ->with('success', __('media.flash.subtitle_reviewed'));
    }

    /** Untertitelspur verwerfen (Feature 150). */
    public function destroySubtitle(LearningCourse $course, LearningUnit $unit, MediaRendition $rendition): RedirectResponse {
        Gate::authorize('update', $course);
        $this->assertUnitSubtitle($course, $unit, $rendition);

        app(VideoTranscodingService::class)->deleteSubtitle($rendition);

        return redirect()
            ->route('learning.courses.units.edit', ['course' => $course->sqid, 'unit' => $unit->sqid])
            ->with('success', __('media.flash.subtitle_removed'));
    }

    /**
     * In der Anwendung eingerichtete Sprachen.
     *
     * @return list<string>
     */
    private function availableLocales(): array {
        /** @var list<string> $locales */
        $locales = (array) config('app.available_locales', [config('app.locale', 'de')]);

        return array_values(array_filter($locales, 'is_string'));
    }

    /** Hängt der Anhang wirklich an dieser Einheit dieses Kurses? */
    private function assertUnitAttachment(LearningCourse $course, LearningUnit $unit, Attachment $attachment): void {
        abort_unless($unit->learning_course_id === $course->id, 404);
        abort_unless(
            $attachment->attachable_type === $unit->getMorphClass()
            && (int) $attachment->attachable_id === (int) $unit->id,
            404
        );
    }

    /** Gehört die Ableitung zu einem Video dieser Einheit — und ist sie eine Untertitelspur? */
    private function assertUnitSubtitle(LearningCourse $course, LearningUnit $unit, MediaRendition $rendition): void {
        abort_unless($rendition->kind === MediaRenditionKind::Subtitle, 404);

        $attachment = $rendition->attachment;
        abort_unless($attachment instanceof Attachment, 404);

        $this->assertUnitAttachment($course, $unit, $attachment);
    }

    /**
     * Kurs in eine Sprache übersetzen (MVP-748).
     *
     * Das Ergebnis ist **immer ein Entwurf** — eine maschinell übersetzte
     * Sicherheitsunterweisung darf nicht unbesehen als Nachweis gelten.
     */
    public function translate(Request $request, LearningCourse $course): RedirectResponse {
        Gate::authorize('update', $course);

        $data = $request->validate([
            'locale' => ['required', 'string', 'max:8'],
        ]);

        try {
            $created = app(LearningTranslationService::class)->translateCourse($course, (string) $data['locale']);
        } catch (AiException $e) {
            return back()->withErrors(['locale' => $e->getMessage()]);
        }

        return redirect()
            ->route('learning.courses.show', $course->sqid)
            ->with('success', __('learning.flash.translated', ['count' => count($created)]));
    }

    /** Übersetzung freigeben — erst jetzt sehen Lernende sie. */
    public function approveTranslation(LearningCourse $course, LearningContentTranslation $translation): RedirectResponse {
        Gate::authorize('update', $course);

        abort_unless(
            (int) $translation->organization_id === (int) $course->organization_id,
            404
        );

        app(LearningTranslationService::class)->approve($translation, $this->actorUser());

        return redirect()
            ->route('learning.courses.show', $course->sqid)
            ->with('success', __('learning.flash.translation_approved'));
    }

    private function actorUser(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /**
     * Geräte für die Einweisung (MVP-740).
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Asset>
     */
    private function assetOptions() {
        return \App\Models\Asset::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** Medien eines Inhaltsblocks für die Autoren-Vorschau. */
    public function unitMedia(LearningCourse $course, LearningUnit $unit, Attachment $attachment): SymfonyResponse {
        Gate::authorize('view', $course);

        abort_unless($unit->learning_course_id === $course->id, 404);
        abort_unless(
            $attachment->attachable_type === $unit->getMorphClass()
            && (int) $attachment->attachable_id === (int) $unit->id,
            404
        );

        return app(\App\Services\Media\MediaResponder::class)->attachment($attachment);
    }

    /**
     * Teilnehmerliste eines Präsenztermins als PDF (MVP-741).
     *
     * Arbeitsmittel, kein Nachweis — nachgewiesen ist die Teilnahme erst
     * mit dem Status „teilgenommen".
     */
    public function attendanceList(LearningCourse $course, LearningUnit $unit): Response {
        Gate::authorize('view', $course);
        abort_unless($unit->learning_course_id === $course->id, 404);
        abort_unless($unit->kind === LearningUnitKind::Event, 404);

        $content = app(LearningAttendanceListPdfRenderer::class)->output($unit);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="teilnehmerliste-' . $unit->sqid . '.pdf"',
        ]);
    }
}
