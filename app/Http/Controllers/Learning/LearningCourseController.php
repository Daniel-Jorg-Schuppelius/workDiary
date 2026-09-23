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

use App\Enums\Learning\{LearningAccessKind, LearningAudience, LearningBlockKind, LearningCourseKind, LearningCourseStatus, LearningFeedbackMode, LearningInstructionSuitability, LearningQuestionKind, LearningTimePolicy, LearningUnitKind};
use App\Enums\Media\MediaRenditionKind;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Attachments\Attachment;
use App\Models\Learning\{LearningAssignment, LearningContentTranslation, LearningCourse, LearningCourseCategory, LearningEnrollment, LearningQuestion, LearningQuestionCategory, LearningQuiz, LearningQuizAttempt, LearningQuizDrawRule, LearningSection, LearningUnit};
use App\Models\Media\MediaRendition;
use App\Models\Platform\User;
use App\Models\Training\TrainingCourse;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Ai\Exceptions\AiException;
use App\Services\Attachments\FileAttacher;
use App\Services\Learning\{LearningAiSuggestionService, LearningAttendanceListPdfRenderer, LearningContentService, LearningCoursePortabilityService, LearningCourseService, LearningOutlineParser, LearningQuestionCatalogService, LearningQuestionEditorService, LearningTranslationService};
use App\Services\Media\VideoTranscodingService;
use App\Support\Sqid;
use CommonToolkit\Helper\Data\JsonHelper;
use CommonToolkit\Helper\FileSystem\File;
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
        private readonly LearningQuestionEditorService $questions,
        private readonly LearningAiSuggestionService $ai,
        private readonly LearningQuestionCatalogService $catalog,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', LearningCourse::class);

        $status = $request->query('status');
        $status = is_string($status) ? LearningCourseStatus::tryFrom($status) : null;
        $kind = LearningCourseKind::tryFrom((string) $request->query('kind', ''));
        // Kategorie (MVP-788): Sqid aus der Auswahl, sonst kein Filter.
        $categoryId = $request->filled('category') ? Sqid::decodeOrNumeric(LearningCourseCategory::class, (string) $request->query('category')) : null;
        // Schlagwort (MVP-810): unbekannte Kennung filtert auf 0 statt den Filter fallen zu lassen.
        $tagId = $request->filled('tag') ? (Sqid::decodeOrNumeric(\App\Models\Classification\Tag::class, (string) $request->query('tag')) ?? 0) : null;

        /** @var User $viewer */
        $viewer = Auth::user();
        $query = LearningCourse::query()
            ->with(['trainingCourse', 'category', 'tags:id,name,color,slug'])
            ->withCount('units')
            // Trainer-Scoping (MVP-786): einzige Filterstelle ist der Scope.
            ->visibleTo($viewer)
            ->orderBy('title');

        if ($status !== null) {
            $query->where('status', $status->value);
        }
        if ($kind !== null) {
            $query->where('kind', $kind->value);
        }
        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }
        if ($tagId !== null) {
            $query->whereHas('tags', fn ($t) => $t->whereKey($tagId));
        }

        // Ansicht Liste/Kacheln (MVP-794): Umschalter speichert die Nutzerpräferenz.
        $view = (string) $request->query('view', '');
        if (in_array($view, ['list', 'tiles'], true)) {
            // Verschachtelt wie die übrigen Lern-Präferenzen (setPreference() speichert flach).
            $preferences = (array) ($viewer->preferences ?? []);
            data_set($preferences, 'learning.catalog_view', $view);
            $viewer->forceFill(['preferences' => $preferences])->save();
        } else {
            $view = (string) (($viewer->preferences['learning']['catalog_view'] ?? null) ?: 'list');
        }
        $courses = $query->paginate(30)->withQueryString();

        return view('learning.courses.index', [
            'courses' => $courses,
            'status' => $status,
            'kind' => $kind,
            'categoryId' => $categoryId,
            'categories' => $this->categoryOptions(),
            'tagId' => $tagId,
            // Nur Schlagwörter an Kursen, die die Person sehen darf (Trainer-Scoping).
            'tags' => \App\Models\Classification\Tag::query()
                ->whereHas('learningCourses', fn ($q) => $q->visibleTo($viewer))
                ->orderBy('name')
                ->get(['id', 'name']),
            'viewMode' => $view,
            'ratings' => app(\App\Services\Learning\LearningCourseRatingService::class)->ratingsFor(array_values(array_map('intval', $courses->pluck('id')->all()))),
            'releasedCount' => LearningCourse::query()->released()->count(),
            'canCreate' => Gate::allows('create', LearningCourse::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', LearningCourse::class);

        return view('learning.courses._form_dialog', [
            'course' => null,
            'trainingCourses' => $this->trainingCourseOptions(),
            'courseOptions' => $this->courseOptions(),
            'assets' => $this->assetOptions(),
            'categories' => $this->categoryOptions(),
            'competencies' => \App\Models\Learning\Competency::query()->where('is_active', true)->orderBy('name')->get(),
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

        // Teilnehmerkarte (MVP-778): Zähler je Status, die Liste liegt auf
        // einer eigenen Seite.
        $enrollmentCounts = LearningEnrollment::query()
            ->where('learning_course_id', $course->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('learning.courses.show', [
            'enrollmentCounts' => $enrollmentCounts,
            'canManageParticipants' => Gate::allows('updateMeta', $course),
            'aiOutline' => Gate::allows('update', $course) && $this->ai->isAvailable($course->organization, LearningAiSuggestionService::CAPABILITY_OUTLINE),
            'trainers' => $course->trainers()->orderBy('name')->get(),
            'trainerOptions' => Gate::allows('updateMeta', $course)
                ? User::query()->inCurrentOrganization()->where('organization_id', $course->organization_id)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'scopingEnabled' => LearningCourse::scopingEnabled($course->organization),
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
            'courseOptions' => $this->courseOptions($course),
            'assets' => $this->assetOptions(),
            'categories' => $this->categoryOptions(),
            'competencies' => \App\Models\Learning\Competency::query()->where('is_active', true)->orderBy('name')->get(),
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
            'is_preview' => ['nullable', 'boolean'],
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
            'is_preview' => (bool) ($data['is_preview'] ?? false),
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
            // Prozedurblock (MVP-806): nur aktive Vorlagen der eigenen Organisation.
            'procedureTemplates' => \App\Models\ProcedureTemplate::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'subtitles' => $subtitles,
            'canTranscribe' => app(VideoTranscodingService::class)->isTranscriptionAvailable(),
            // LTI-Einheit (Feature 149): die aktiven Tools der Organisation zur Auswahl.
            'ltiTools' => $unit->kind === \App\Enums\Learning\LearningUnitKind::Lti
                ? \App\Models\Learning\LearningLtiTool::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
            // Eigene Parameter als `name=wert`-Zeilen — im View ohne @php, das dort kollidiert.
            'ltiCustom' => collect($unit->ltiLink->custom ?? [])
                ->map(static fn (string $value, string $name): string => $name . '=' . $value)
                ->implode("\n"),
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
            'is_preview' => ['nullable', 'boolean'],
            'release_after_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'release_at' => ['nullable', 'date'],
            'min_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);

        // Freischaltplan (Drip, MVP-788): Tage ab Einschreibung und/oder festes
        // Datum — es gilt das spätere; ohne beides ist die Einheit sofort offen.
        $release = array_filter([
            'after_days' => (int) ($data['release_after_days'] ?? 0) > 0 ? (int) $data['release_after_days'] : null,
            'at' => ! empty($data['release_at']) ? \Illuminate\Support\Carbon::parse((string) $data['release_at'])->toDateString() : null,
        ], static fn ($v) => $v !== null);
        $completion = (array) ($unit->completion_rule ?? []);
        if ((int) ($data['min_seconds'] ?? 0) > 0) {
            $completion['min_seconds'] = (int) $data['min_seconds'];
        } else {
            unset($completion['min_seconds']);
        }

        $unit->update([
            'title' => $data['title'],
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'points' => max(0, (int) ($data['points'] ?? 0)),
            'is_mandatory' => (bool) ($data['is_mandatory'] ?? false),
            'is_preview' => (bool) ($data['is_preview'] ?? false),
            'release_rule' => $release !== [] ? $release : null,
            'completion_rule' => $completion !== [] ? $completion : null,
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
            'autoplay' => ['nullable', 'boolean'],
            'remember_position' => ['nullable', 'boolean'],
            'media' => array_merge(['nullable'], $this->learningMediaRule()),
            // MVP-806
            'language' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9+#._ -]+$/'],
            'sections' => ['nullable', 'string', 'max:10000'],
            'rows' => ['nullable', 'string', 'max:10000'],
            'explanation' => ['nullable', 'string', 'max:2000'],
            'procedure_template_id' => ['nullable', 'string', 'max:64'],
            'alts' => ['nullable', 'string', 'max:3000'],
            'gallery' => ['nullable', 'array', 'max:' . LearningContentService::GALLERY_MAX_IMAGES],
            'gallery.*' => $this->learningMediaRule(),
        ]);

        $kind = LearningBlockKind::from($data['type']);
        $media = $request->file('media');
        /** @var list<UploadedFile> $galleryFiles */
        $galleryFiles = $kind === LearningBlockKind::Gallery ? array_values(array_filter((array) $request->file('gallery', []), static fn (mixed $file): bool => $file instanceof UploadedFile)) : [];

        // Ein Upload muss zur Blockart passen: ein PDF im Bild- oder
        // Audioblock bliebe im Kurs eine leere Fläche.
        $mediaType = $kind->mediaType();
        foreach ([...($media instanceof UploadedFile ? [$media] : []), ...$galleryFiles] as $upload) {
            if ($mediaType !== null && ! str_starts_with((string) $upload->getMimeType(), $mediaType . '/')) {
                throw ValidationException::withMessages([
                    $kind === LearningBlockKind::Gallery ? 'gallery' : 'media' => (string) __('learning.errors.media_type_mismatch', ['kind' => $kind->label()]),
                ]);
            }
        }

        if ($kind === LearningBlockKind::Procedure) {
            $data['procedure_template_id'] = Sqid::decode(\App\Models\ProcedureTemplate::class, (string) ($data['procedure_template_id'] ?? ''));
        }

        // Galerie: ein Alternativtext je Bild, in derselben Reihenfolge. Vor
        // dem Speichern geprüft, damit keine verwaisten Anhänge entstehen.
        if ($kind === LearningBlockKind::Gallery) {
            $alts = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['alts'] ?? '')) ?: []), static fn (string $alt): bool => $alt !== ''));
            if (count($galleryFiles) < 2) {
                throw ValidationException::withMessages(['gallery' => (string) __('learning.errors.gallery_count', ['max' => LearningContentService::GALLERY_MAX_IMAGES])]);
            }
            if (count($alts) !== count($galleryFiles)) {
                throw ValidationException::withMessages(['alts' => (string) __('learning.errors.gallery_alt_required')]);
            }
        }

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

        if ($galleryFiles !== []) {
            /** @var User $uploader */
            $uploader = Auth::user();
            $data['images'] = [];
            foreach ($galleryFiles as $position => $file) {
                $attachment = app(FileAttacher::class)->store($unit, $file, $uploader->id, ['organization_id' => $unit->organization_id], 'learning-content');
                $data['images'][] = ['attachment_id' => $attachment->id, 'alt' => $alts[$position] ?? ''];
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

    /**
     * Uploadregel für Lerninhalte: die generischen Anhangsformate plus Audio
     * und Video. Ohne diese Endungen lehnte schon die Validierung jedes Video
     * ab — der Upload im Videoblock und damit die Umrechnung (Feature 150)
     * waren über die Oberfläche nie erreichbar.
     *
     * @return list<string>
     */
    private function learningMediaRule(): array {
        $extensions = [...FileAttacher::ALLOWED_EXTENSIONS, 'mp4', 'm4v', 'webm', 'mov', 'mp3', 'm4a', 'ogg', 'oga', 'wav'];

        return ['file', 'max:' . FileAttacher::maxKb(), 'mimes:' . implode(',', $extensions)];
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
            'quiz' => $unit->quiz()->with(['questions.options', 'questions.category', 'drawRules.category'])->first(),
            'aiQuestions' => $this->ai->isAvailable($course->organization, LearningAiSuggestionService::CAPABILITY_QUESTIONS),
            'categories' => $this->categories($course->organization_id),
        ]);
    }

    /** Prüfungsstatistik (MVP-785): Versuche, Quoten je Frage ab n ≥ 5, Zeitbedarf. */
    public function quizStatistics(LearningCourse $course, LearningUnit $unit): View {
        Gate::authorize(\App\Enums\User\Permission::LearningGrade->value);
        abort_unless($course->isVisibleTo($this->uploader()), 404);
        $this->guardUnitBelongsToCourse($course, $unit);
        $quiz = $unit->quiz;
        abort_if($quiz === null, 404);

        $attempts = LearningQuizAttempt::query()
            ->with(['enrollment.user', 'enrollment.externalParticipant'])
            ->where('learning_quiz_id', $quiz->id)
            ->orderByDesc('started_at')
            ->paginate(50);

        return view('learning.courses.quiz_statistics', [
            'course' => $course,
            'unit' => $unit,
            'quiz' => $quiz,
            'attempts' => $attempts,
            'stats' => app(\App\Services\Learning\LearningReportService::class)->quizStatistics($quiz),
            'minGroup' => \App\Services\Learning\LearningReportService::MIN_GROUP,
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
            'questions_per_attempt_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'pass_points' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'shuffle_questions' => ['nullable', 'boolean'],
            'shuffle_answers' => ['nullable', 'boolean'],
            'feedback_mode' => ['required', Rule::enum(LearningFeedbackMode::class)],
            'show_solutions' => ['nullable', 'boolean'],
            'display_mode' => ['nullable', Rule::in(['all', 'single'])],
            'allow_back' => ['nullable', 'boolean'],
            'allow_skip' => ['nullable', 'boolean'],
            'require_all_answered' => ['nullable', 'boolean'],
            'result_messages' => ['nullable', 'string', 'max:5000'],
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
                'questions_per_attempt_percent' => $data['questions_per_attempt_percent'] ?? null,
                'pass_points' => $data['pass_points'] ?? null,
                'shuffle_questions' => (bool) ($data['shuffle_questions'] ?? false),
                'shuffle_answers' => (bool) ($data['shuffle_answers'] ?? false),
                'feedback_mode' => $data['feedback_mode'],
                'show_solutions' => (bool) ($data['show_solutions'] ?? false),
                'display_mode' => $data['display_mode'] ?? 'all',
                'allow_back' => (bool) ($data['allow_back'] ?? false),
                'allow_skip' => (bool) ($data['allow_skip'] ?? false),
                'require_all_answered' => (bool) ($data['require_all_answered'] ?? false),
                'result_messages' => $this->parseResultMessages((string) ($data['result_messages'] ?? '')),
            ]
        );

        return redirect()->back()->with('success', __('learning.flash.quiz_saved'));
    }

    /**
     * Frage anlegen. Optionen kommen als Zeilenliste: eine Zeile je Option,
     * ein führendes `*` markiert die richtige — kompakt und ohne JavaScript.
     * Die Übersetzung der Zeilen liegt im {@see LearningQuestionEditorService}.
     */
    public function storeQuestion(Request $request, LearningCourse $course, LearningUnit $unit): RedirectResponse {
        Gate::authorize('update', $course);
        $this->guardUnitBelongsToCourse($course, $unit);

        $quiz = $unit->quiz;
        abort_if($quiz === null, 404);

        $data = $this->validatedQuestion($request, $course);
        $image = $request->file('image');

        $this->questions->create($course->organization_id, $data, $image instanceof UploadedFile ? $image : null, $this->uploader(), $quiz);

        return redirect()
            ->route('learning.courses.units.quiz.edit', [$course, $unit])
            ->with('success', __('learning.flash.question_added'));
    }

    /** Frage bearbeiten (MVP-779): derselbe Editor, Textfeld aus der Frage zurückgefüllt. */
    public function editQuestion(LearningCourse $course, LearningUnit $unit, LearningQuestion $question): View {
        Gate::authorize('update', $course);
        $this->guardQuestionBelongsToUnit($course, $unit, $question);

        return view('learning.courses.quiz_editor', [
            'course' => $course,
            'unit' => $unit,
            'quiz' => $unit->quiz()->with(['questions.options', 'questions.category', 'drawRules.category'])->first(),
            'editing' => $question,
            'editingLines' => $this->questions->toLines($question),
            'aiQuestions' => $this->ai->isAvailable($course->organization, LearningAiSuggestionService::CAPABILITY_QUESTIONS),
            'categories' => $this->categories($course->organization_id),
        ]);
    }

    public function updateQuestion(Request $request, LearningCourse $course, LearningUnit $unit, LearningQuestion $question): RedirectResponse {
        Gate::authorize('update', $course);
        $this->guardQuestionBelongsToUnit($course, $unit, $question);

        $data = $this->validatedQuestion($request, $course);
        $image = $request->file('image');

        $this->questions->update($question, $data, $image instanceof UploadedFile ? $image : null, $this->uploader());

        return redirect()
            ->route('learning.courses.units.quiz.edit', [$course, $unit])
            ->with('success', __('learning.flash.question_updated'));
    }

    public function duplicateQuestion(LearningCourse $course, LearningUnit $unit, LearningQuestion $question): RedirectResponse {
        Gate::authorize('update', $course);
        $this->guardQuestionBelongsToUnit($course, $unit, $question);

        $quiz = $unit->quiz;
        abort_if($quiz === null, 404);
        $this->questions->duplicate($question, $this->uploader(), $quiz);

        return redirect()
            ->route('learning.courses.units.quiz.edit', [$course, $unit])
            ->with('success', __('learning.flash.question_duplicated'));
    }

    public function moveQuestion(Request $request, LearningCourse $course, LearningUnit $unit, LearningQuestion $question): RedirectResponse {
        Gate::authorize('update', $course);
        $this->guardQuestionBelongsToUnit($course, $unit, $question);

        $quiz = $unit->quiz;
        abort_if($quiz === null, 404);
        $this->questions->move($quiz, $question, $request->input('direction') === 'up' ? -1 : 1);

        return redirect()->route('learning.courses.units.quiz.edit', [$course, $unit]);
    }

    /** Aus der Prüfung nehmen — die Katalogfrage bleibt (MVP-782). */
    public function destroyQuestion(LearningCourse $course, LearningUnit $unit, LearningQuestion $question): RedirectResponse {
        Gate::authorize('update', $course);
        $this->guardQuestionBelongsToUnit($course, $unit, $question);

        $quiz = $unit->quiz;
        abort_if($quiz === null, 404);
        $this->catalog->detach($quiz, $question);

        return redirect()->route('learning.courses.units.quiz.edit', [$course, $unit])
            ->with('success', __('learning.flash.question_detached'));
    }

    /** Katalogfragen zur Auswahl (Dialog, MVP-782): alles, was noch nicht in der Prüfung steht. */
    public function catalogPicker(Request $request, LearningCourse $course, LearningUnit $unit): View {
        Gate::authorize('update', $course);
        $this->guardUnitBelongsToCourse($course, $unit);
        $quiz = $unit->quiz;
        abort_if($quiz === null, 404);

        $category = Sqid::decode(LearningQuestionCategory::class, (string) $request->query('category', ''));
        $kind = LearningQuestionKind::tryFrom((string) $request->query('kind', ''));
        $search = trim((string) $request->query('q', ''));

        $questions = LearningQuestion::query()
            ->with('category')
            ->where('organization_id', $course->organization_id)
            ->whereNotIn('id', $quiz->questions()->select('learning_questions.id'))
            ->when($category !== null, fn ($q) => $q->where('learning_question_category_id', $category))
            ->when($kind, fn ($q, LearningQuestionKind $k) => $q->where('kind', $k->value))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->whereLikeEscaped('prompt', $search)->orWhereLikeEscaped('title', $search)))
            ->orderBy('title')
            ->orderBy('prompt')
            ->limit(200)
            ->get();

        return view('learning.courses._catalog_picker_dialog', [
            'course' => $course,
            'unit' => $unit,
            'questions' => $questions,
            'categories' => $this->categories($course->organization_id),
            'category' => $category,
            'kind' => $kind,
            'search' => $search,
        ]);
    }

    /** Ausgewählte Katalogfragen ans Ende der Prüfung setzen. */
    public function attachQuestions(Request $request, LearningCourse $course, LearningUnit $unit): RedirectResponse {
        Gate::authorize('update', $course);
        $this->guardUnitBelongsToCourse($course, $unit);
        $quiz = $unit->quiz;
        abort_if($quiz === null, 404);

        $data = $request->validate([
            'question_ids' => ['required', 'array', 'min:1', 'max:200'],
            'question_ids.*' => ['string', 'max:64'],
        ]);

        $ids = array_values(array_filter(array_map(
            static fn (string $raw): ?int => Sqid::decode(LearningQuestion::class, $raw),
            $data['question_ids'],
        )));
        $attached = 0;
        foreach (LearningQuestion::query()->where('organization_id', $course->organization_id)->whereIn('id', $ids)->get() as $question) {
            $this->catalog->attach($quiz, $question);
            $attached++;
        }

        return redirect()
            ->route('learning.courses.units.quiz.edit', [$course, $unit])
            ->with('success', __('learning.flash.questions_attached', ['count' => $attached]));
    }

    /** Ziehregel setzen: N Fragen einer Kategorie je Versuch (MVP-782). */
    public function storeDrawRule(Request $request, LearningCourse $course, LearningUnit $unit): RedirectResponse {
        Gate::authorize('update', $course);
        $this->guardUnitBelongsToCourse($course, $unit);
        $quiz = $unit->quiz;
        abort_if($quiz === null, 404);

        $data = $request->validate([
            'category_id' => ['required', 'string', 'max:64'],
            'count' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $categoryId = Sqid::decode(LearningQuestionCategory::class, (string) $data['category_id']);
        $category = $categoryId !== null
            ? LearningQuestionCategory::query()->where('organization_id', $course->organization_id)->find($categoryId)
            : null;
        if ($category === null) {
            throw ValidationException::withMessages(['category_id' => (string) __('learning.errors.category_required')]);
        }

        $this->catalog->setDrawRule($quiz, $category, (int) $data['count']);

        return redirect()
            ->route('learning.courses.units.quiz.edit', [$course, $unit])
            ->with('success', __('learning.flash.draw_rule_saved'));
    }

    public function destroyDrawRule(LearningCourse $course, LearningUnit $unit, LearningQuizDrawRule $rule): RedirectResponse {
        Gate::authorize('update', $course);
        $this->guardUnitBelongsToCourse($course, $unit);
        abort_unless($rule->learning_quiz_id === $unit->quiz?->id, 404);

        $this->catalog->removeDrawRule($rule);

        return redirect()->route('learning.courses.units.quiz.edit', [$course, $unit]);
    }

    /** Einheit in der Kursstruktur verschieben (MVP-779). */
    public function moveUnit(Request $request, LearningCourse $course, LearningUnit $unit): RedirectResponse {
        Gate::authorize('update', $course);
        $this->guardUnitBelongsToCourse($course, $unit);

        $this->courses->moveUnit($unit, $request->input('direction') === 'up' ? -1 : 1);

        return redirect()->route('learning.courses.show', $course);
    }

    public function moveSection(Request $request, LearningCourse $course, LearningSection $section): RedirectResponse {
        Gate::authorize('update', $course);
        abort_unless($section->learning_course_id === $course->id, 404);

        $this->courses->moveSection($section, $request->input('direction') === 'up' ? -1 : 1);

        return redirect()->route('learning.courses.show', $course);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function questionRules(): array {
        return [
            'kind' => ['required', Rule::enum(LearningQuestionKind::class)],
            'title' => ['nullable', 'string', 'max:180'],
            'category_id' => ['nullable', 'string', 'max:64'],
            'prompt' => ['required', 'string', 'min:2', 'max:2000'],
            'explanation' => ['nullable', 'string', 'max:2000'],
            'hint' => ['nullable', 'string', 'max:1000'],
            'feedback_correct' => ['nullable', 'string', 'max:1000'],
            'feedback_incorrect' => ['nullable', 'string', 'max:1000'],
            'points' => ['required', 'integer', 'min:1', 'max:100'],
            'options' => ['nullable', 'string', 'max:5000'],
            'partial_credit' => ['nullable', 'boolean'],
            // Aufsatz (MVP-793): Text, Datei oder beides.
            'submission_kind' => ['nullable', 'string', 'in:text,upload,both'],
            'case_sensitive' => ['nullable', 'boolean'],
            'image' => array_merge(['nullable'], FileAttacher::rule()),
        ];
    }

    /**
     * Ergebnistexte je Prozentbereich (MVP-783): eine Zeile je Stufe als
     * „80: Text". Zeilen ohne Zahl werden übergangen.
     *
     * @return list<array{from_percent: int, text: string}>|null
     */
    private function parseResultMessages(string $text): ?array {
        $messages = [];
        foreach (LearningQuestionEditorService::linesOf($text) as $line) {
            if (preg_match('/^(\d{1,3})\s*[:%]?\s*[:\-–]?\s*(.+)$/u', $line, $m) !== 1) {
                continue;
            }
            $messages[] = ['from_percent' => max(0, min(100, (int) $m[1])), 'text' => trim($m[2])];
        }
        usort($messages, static fn (array $a, array $b): int => $a['from_percent'] <=> $b['from_percent']);

        return $messages !== [] ? $messages : null;
    }

    private function guardQuestionBelongsToUnit(LearningCourse $course, LearningUnit $unit, LearningQuestion $question): void {
        $this->guardUnitBelongsToCourse($course, $unit);
        $quiz = $unit->quiz;
        abort_unless($quiz !== null && $this->catalog->isInQuiz($quiz, $question), 404);
    }

    /**
     * Editor-Felder prüfen; die Kategorie kommt als Sqid und muss der
     * Organisation des Kurses gehören.
     *
     * @return array<string, mixed>
     */
    private function validatedQuestion(Request $request, LearningCourse $course): array {
        $data = $request->validate($this->questionRules());
        $data['category_id'] = null;

        $raw = (string) $request->input('category_id', '');
        if ($raw !== '') {
            $id = Sqid::decode(LearningQuestionCategory::class, $raw);
            $category = $id !== null ? LearningQuestionCategory::query()->where('organization_id', $course->organization_id)->find($id) : null;
            if ($category === null) {
                throw ValidationException::withMessages(['category_id' => (string) __('learning.errors.category_required')]);
            }
            $data['category_id'] = $category->id;
        }

        return $data;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, LearningQuestionCategory>
     */
    private function categories(int $organizationId) {
        return LearningQuestionCategory::query()->where('organization_id', $organizationId)->orderBy('position')->orderBy('name')->get();
    }

    private function uploader(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /** KI-Gliederung (MVP-781): Dialog mit Thema und Zielgruppe. */
    public function createAiOutline(LearningCourse $course): View {
        Gate::authorize('update', $course);
        abort_unless($this->ai->isAvailable($course->organization, LearningAiSuggestionService::CAPABILITY_OUTLINE), 404);

        return view('learning.courses._ai_outline_dialog', [
            'course' => $course,
            'topic' => trim($course->title . ($course->objectives ? "\n" . $course->objectives : '')),
        ]);
    }

    /**
     * Gliederungsentwurf anlegen: Abschnitte und Einheiten ohne Inhalt. Die KI
     * schlägt vor, der Autor füllt — nichts davon ist freigegeben oder bewertet.
     */
    public function storeAiOutline(Request $request, LearningCourse $course): RedirectResponse {
        Gate::authorize('update', $course);
        abort_unless($this->ai->isAvailable($course->organization, LearningAiSuggestionService::CAPABILITY_OUTLINE), 404);

        $data = $request->validate([
            'topic' => ['required', 'string', 'min:3', 'max:2000'],
            'audience' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $outline = $this->ai->draftOutline($this->currentOrganization(), $data['topic'], [], $data['audience'] ?? null);
        } catch (AiException $e) {
            throw ValidationException::withMessages(['topic' => $e->getMessage()]);
        }

        $sections = app(LearningOutlineParser::class)->parse($outline);
        if ($sections === []) {
            throw ValidationException::withMessages(['topic' => (string) __('learning.errors.ai_outline_empty')]);
        }

        $created = 0;
        foreach ($sections as $entry) {
            $section = $entry['title'] !== '' ? $this->courses->addSection($course, ['title' => $entry['title']]) : null;
            foreach ($entry['units'] as $title) {
                $this->courses->addUnit($course, ['title' => $title, 'section' => $section]);
                $created++;
            }
        }

        return redirect()
            ->route('learning.courses.show', $course)
            ->with('success', __('learning.flash.ai_outline_created', ['sections' => count($sections), 'units' => $created]));
    }

    /** Fragenentwurf (MVP-781): landet als Text im Editor, nie direkt als Frage. */
    public function draftQuestions(Request $request, LearningCourse $course, LearningUnit $unit): RedirectResponse {
        Gate::authorize('update', $course);
        $this->guardUnitBelongsToCourse($course, $unit);
        abort_unless($this->ai->isAvailable($course->organization, LearningAiSuggestionService::CAPABILITY_QUESTIONS), 404);

        $data = $request->validate([
            'count' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        try {
            $draft = $this->ai->draftQuestions($unit, (int) ($data['count'] ?? 5));
        } catch (AiException $e) {
            return redirect()
                ->route('learning.courses.units.quiz.edit', [$course, $unit])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('learning.courses.units.quiz.edit', [$course, $unit])
            ->with('aiDraft', $draft);
    }

    /** Trainer zuordnen (MVP-786): nur Personen der eigenen Organisation. */
    public function storeTrainer(Request $request, LearningCourse $course): RedirectResponse {
        Gate::authorize('updateMeta', $course);

        $data = $request->validate([
            'user_id' => ['required', 'string', 'max:64'],
            'role' => ['required', Rule::in(['trainer', 'grader'])],
        ]);

        $id = Sqid::decode(User::class, (string) $data['user_id']);
        $trainer = $id !== null
            ? User::query()->inCurrentOrganization()->where('organization_id', $course->organization_id)->find($id)
            : null;
        if ($trainer === null) {
            throw ValidationException::withMessages(['user_id' => (string) __('learning.errors.learner_required')]);
        }

        $course->trainers()->syncWithoutDetaching([
            $trainer->id => ['organization_id' => $course->organization_id, 'role' => $data['role']],
        ]);

        return redirect()->route('learning.courses.show', $course)->with('success', __('learning.flash.trainer_added', ['name' => $trainer->name]));
    }

    public function destroyTrainer(LearningCourse $course, User $user): RedirectResponse {
        Gate::authorize('updateMeta', $course);

        $course->trainers()->detach($user->id);

        return redirect()->route('learning.courses.show', $course)->with('success', __('learning.flash.trainer_removed'));
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
            // Dateiregeln und Auto-Freigabe (MVP-788).
            'allowed_extensions' => ['nullable', 'string', 'max:255'],
            'max_files' => ['nullable', 'integer', 'min:1', 'max:5'],
            'max_file_mb' => ['nullable', 'integer', 'min:1', 'max:1024'],
            'auto_approve' => ['nullable', 'boolean'],
        ]);

        // Volle Punkte ohne Bewerter UND Vier-Augen schließen sich aus.
        if (($data['auto_approve'] ?? false) && ($data['requires_second_opinion'] ?? false)) {
            throw ValidationException::withMessages(['auto_approve' => (string) __('learning.errors.auto_approve_conflict')]);
        }
        $extensions = array_values(array_unique(array_filter(array_map(
            static fn (string $e): string => strtolower(ltrim(trim($e), '.')),
            preg_split('/[\s,;]+/', (string) ($data['allowed_extensions'] ?? '')) ?: [],
        ))));

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
                'allowed_extensions' => $extensions !== [] ? $extensions : null,
                'max_files' => $data['max_files'] ?? null,
                'max_file_mb' => $data['max_file_mb'] ?? null,
                'auto_approve' => (bool) ($data['auto_approve'] ?? false),
            ]
        );

        return redirect()->back()->with('success', __('learning.flash.assignment_saved'));
    }

    /**
     * @return list<array{key: string, label: string, weight: int, max_points: int}>|null
     */
    private function parseRubric(string $text): ?array {
        $rows = [];
        foreach (LearningQuestionEditorService::linesOf($text) as $index => $line) {
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

    /**
     * Kurs duplizieren (MVP-794): Export → Import in einem Schritt — ein neuer
     * Entwurf mit neuem Code, ohne Einschreibungen oder Nachweise.
     */
    public function duplicate(LearningCourse $course): RedirectResponse {
        Gate::authorize('view', $course);
        Gate::authorize('create', LearningCourse::class);

        $payload = $this->portability->export($course);
        $payload['course']['title'] = (string) __('learning.copy_title', ['title' => $course->title]);

        /** @var User $actor */
        $actor = Auth::user();
        $copy = $this->portability->import($this->currentOrganization(), $payload, $actor);

        return redirect()
            ->route('learning.courses.show', $copy->sqid)
            ->with('success', __('learning.flash.duplicated'));
    }

    /** Kurs als JSON exportieren — Lehrmaterial, keine Nachweise. */
    public function exportCourse(LearningCourse $course): \Symfony\Component\HttpFoundation\Response {
        Gate::authorize('view', $course);

        $payload = $this->portability->export($course);
        $filename = 'kurs-' . $course->code . '.json';

        return response()->streamDownload(
            static function () use ($payload): void {
                echo JsonHelper::encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

        $raw = File::read((string) $request->file('file')->getRealPath());
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

    /** LearnDash-Import (MVP-792): Dialog. */
    public function importLearnDashDialog(): View {
        Gate::authorize('create', LearningCourse::class);

        return view('learning.courses._learndash_import_dialog');
    }

    public function importLearnDash(Request $request): RedirectResponse {
        Gate::authorize('create', LearningCourse::class);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:zip', 'max:204800'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $report = app(\App\Services\Learning\LearnDashImportService::class)->import(
            $this->currentOrganization(),
            (string) $request->file('file')->getRealPath(),
            $actor,
            (bool) ($data['dry_run'] ?? false),
        );

        return redirect()
            ->route('learning.courses.index')
            ->with('success', __($report['dry_run'] ? 'learning.flash.learndash_dry_run' : 'learning.flash.learndash_imported', [
                'courses' => $report['courses'],
                'units' => $report['units'],
                'questions' => $report['questions'],
                'enrollments' => $report['enrollments'],
                'skipped' => count((array) $report['skipped']),
            ]));
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
        if ($request->filled('exam_for_course_id')) {
            $request->merge(['exam_for_course_id' => Sqid::decodeOrNumeric(LearningCourse::class, $request->input('exam_for_course_id'))]);
        }
        if ($request->filled('category_id')) {
            $request->merge(['category_id' => Sqid::decodeOrNumeric(LearningCourseCategory::class, $request->input('category_id'))]);
        }
        if ($request->filled('competency_id')) {
            $request->merge(['competency_id' => Sqid::decodeOrNumeric(\App\Models\Learning\Competency::class, $request->input('competency_id'))]);
        }
        $prerequisiteIds = $request->input('prerequisite_course_ids');
        if (is_array($prerequisiteIds)) {
            $request->merge(['prerequisite_course_ids' => array_values(array_map(
                static fn ($raw) => Sqid::decodeOrNumeric(LearningCourse::class, (string) $raw),
                $prerequisiteIds,
            ))]);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:180'],
            // Prüfung ohne Kurs (MVP-784): nur beim Anlegen wählbar.
            'kind' => ['nullable', Rule::enum(LearningCourseKind::class)],
            'exam_for_course_id' => ['nullable', new ExistsInCurrentOrganization('learning_courses')],
            'prerequisite_mode' => ['nullable', Rule::in(['all', 'any'])],
            'prerequisite_course_ids' => ['nullable', 'array', 'max:20'],
            'prerequisite_course_ids.*' => [new ExistsInCurrentOrganization('learning_courses')],
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
            // LTI 1.3 (Feature 149): über eine fremde Plattform startbar.
            'lti_available' => ['nullable', 'boolean'],
            'access_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            // Kursoptionen (MVP-788): Kategorie, Verfügbarkeitsfenster, Grenze.
            'category_id' => ['nullable', new ExistsInCurrentOrganization('learning_course_categories')],
            'tags' => ['nullable', 'string', 'max:500'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after_or_equal:available_from'],
            'max_enrollments' => ['nullable', 'integer', 'min:1', 'max:100000'],
            // Kompetenz, die der Abschluss belegt (MVP-798, C3-03) — ohne Feld blieb grantFromCourse wirkungslos.
            'competency_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('competencies')],
            'competency_level' => ['nullable', 'integer', 'between:1,10'],
        ]);

        $data['category_id'] = $data['category_id'] ?? null;
        $data['available_from'] = $data['available_from'] ?? null;
        $data['available_until'] = $data['available_until'] ?? null;
        $data['max_enrollments'] = $data['max_enrollments'] ?? null;
        $data['certificate_enabled'] = (bool) ($data['certificate_enabled'] ?? false);
        $data['sequential'] = (bool) ($data['sequential'] ?? false);
        $data['lti_available'] = (bool) ($data['lti_available'] ?? false);
        $data['audiences'] = $data['audiences'] ?? [LearningAudience::Internal->value];

        // Der Code wird nur beim Anlegen vergeben — er ist der Anker des Kurses.
        if ($course !== null) {
            unset($data['code'], $data['kind']);
        }
        $data['prerequisite_course_ids'] = array_values(array_map('intval', (array) ($data['prerequisite_course_ids'] ?? [])));
        if ($course !== null && in_array($course->id, $data['prerequisite_course_ids'], true)) {
            throw ValidationException::withMessages(['prerequisite_course_ids' => (string) __('learning.errors.prerequisite_self')]);
        }
        if (($data['kind'] ?? $course?->kind?->value) !== LearningCourseKind::Exam->value) {
            $data['exam_for_course_id'] = null;
        }

        return $data;
    }

    /** @return \Illuminate\Support\Collection<int, LearningCourseCategory> */
    private function categoryOptions() {
        return LearningCourseCategory::query()->orderBy('position')->orderBy('name')->get();
    }

    /**
     * Kurse der Organisation als Auswahl (Zielkurs, Voraussetzungen) — ohne
     * den bearbeiteten Kurs selbst.
     *
     * @return \Illuminate\Support\Collection<int, LearningCourse>
     */
    private function courseOptions(?LearningCourse $except = null) {
        return LearningCourse::query()
            ->where('organization_id', $this->currentOrganization()->id)
            ->when($except, fn ($q, LearningCourse $c) => $q->whereKeyNot($c->id))
            ->orderBy('title')
            ->get(['id', 'title', 'code', 'kind']);
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
            File::read((string) $file->getRealPath()),
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
