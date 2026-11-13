<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormSubmissionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Form;

use App\Enums\User\Permission as P;
use App\Exceptions\FieldDocumentConflictException;
use App\Http\Controllers\Concerns\{AuthorizesCarrier, ResolvesGlobalDateRange};
use App\Http\Controllers\Controller;
use App\Http\Controllers\Document\DocumentController;
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Form\{FormSubmission, FormTemplate};
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Services\Attachments\FileAttacher;
use App\Services\Fields\{FieldSchema, FieldValues};
use App\Services\Form\{FormService, FormSubmissionPdfRenderer};
use App\Services\Support\Content\ContentSubjectResolver;
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Ausgefüllte Formulare (Feature 032): Ausfüll-Dialog (Modal, dynamisch
 * gerenderte Felder), Read-Only-/Druck-Seite und gefilterte Liste.
 *
 * Lange Formulare (Feature 203): Der Dialog gliedert über <x-field-form>
 * und speichert jedes Feld beim Verlassen in einen Entwurf (MVP-1650). Der
 * Entwurf entsteht mit dem ersten Feld ({@see storeDraft()}), gespeichert
 * wird über den gemeinsamen Endpunkt `field-documents.autosave`; abgesendet
 * wird er über {@see store()} mit `draft` und `draft_version`.
 */
class FormSubmissionController extends Controller {
    use AuthorizesCarrier;
    use ResolvesGlobalDateRange;

    /**
     * Whitelist der erlaubten Bezugs-Typen. Verhindert, dass Aufrufer
     * beliebige Klassen an `subject_type` setzen können — analog
     * DocumentController::DOCUMENTABLE_MAP.
     *
     * @var array<string, class-string<Model>>
     */
    public const array SUBJECT_MAP = [
        'diary' => DiaryEntry::class,
        'customer' => Customer::class,
        'asset' => Asset::class,
        'project' => Project::class,
    ];

    public function __construct(
        private readonly FormService $service,
        private readonly ContentSubjectResolver $subjects,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', FormSubmission::class);

        /** @var User $user */
        $user = Auth::user();
        // Teamleitung+ (formTemplate.viewAny) sieht alle Submissions der
        // Organisation; alle anderen ausschließlich die EIGENEN.
        $canViewAll = $user->isAdmin() || $user->can(P::FormTemplateViewAny->value);

        $filters = [
            'template' => (string) $request->query('template', 'all'),
        ];

        // Zeitraum kommt aus der globalen Header-Auswahl (Hausstandard),
        // nicht aus einem eigenen Von/Bis in der Filterleiste.
        $range = $this->globalDateRange();

        $query = FormSubmission::query()
            ->submitted()
            ->with(['template', 'submitter'])
            ->with($this->subjects->eagerLoad(FormSubmission::class))
            ->whereBetween('submitted_at', [$range['from']->startOfDay(), $range['to']->endOfDay()])
            ->orderByDesc('submitted_at');

        if (! $canViewAll) {
            $query->where('submitted_by_user_id', $user->id);
        }

        $templateId = Sqid::decode(FormTemplate::class, $filters['template']);
        if ($templateId !== null) {
            $query->where('form_template_id', $templateId);
        } else {
            $filters['template'] = 'all';
        }

        $submissions = $query->paginate(25)->withQueryString();

        return view('forms.submissions.index', [
            'submissions' => $submissions,
            // Entwürfe sind persönlich (Feature 203): nur die eigenen, ohne Zeitraum.
            'drafts' => FormSubmission::query()->drafts()
                ->where('submitted_by_user_id', $user->id)
                ->with('template')
                ->with($this->subjects->eagerLoad(FormSubmission::class))
                ->latest('updated_at')
                ->get(),
            'filters' => $filters,
            'hasActiveFilters' => $filters['template'] !== 'all',
            'templates' => FormTemplate::query()->orderBy('name')->get(),
            'activeTemplates' => Gate::allows('create', FormSubmission::class)
                ? FormTemplate::query()->active()->orderBy('name')->get()
                : collect(),
        ]);
    }

    /** Ausfüll-Dialog: ?template=<sqid>[&subject_kind=&subject_id=]. */
    public function create(Request $request): View {
        Gate::authorize('create', FormSubmission::class);

        $template = $this->findActiveTemplate((string) $request->query('template', ''));
        [$subjectKind, $subjectId] = $this->resolveOptionalSubjectFromRequest(
            (string) $request->query('subject_kind', ''),
            (string) $request->query('subject_id', ''),
        );

        // Eigener Entwurf zu Vorlage und Bezug wird fortgesetzt (Feature 203).
        $subject = $subjectKind !== null ? $this->findSubject($subjectKind, (string) $subjectId) : null;
        $draft = $this->service->ownDraft($template, $subject, $this->authUser());
        $rebased = $draft !== null && $this->service->rebaseDraft($draft);

        return $this->dialog($template, $subjectKind, $subjectId, $draft, $rebased);
    }

    /** Eigenen Entwurf weiter ausfüllen (Feature 203). */
    public function edit(FormSubmission $submission): View {
        Gate::authorize('autosave', $submission);

        $submission->load(['template', 'subject']);
        $template = $submission->template ?? abort(404);
        $rebased = $this->service->rebaseDraft($submission);
        $subject = $submission->subject;
        $kind = $subject !== null ? array_search($subject::class, self::SUBJECT_MAP, true) : false;

        return $this->dialog(
            $template,
            $kind === false ? null : $kind,
            $kind === false ? null : Sqid::encode($subject::class, (int) $subject->getKey()),
            $submission->refresh(),
            $rebased,
        );
    }

    /**
     * Entwurf anlegen oder den eigenen offenen fortsetzen (Feature 203,
     * MVP-1650) — der Browser ruft das vor dem ersten gespeicherten Feld.
     */
    public function storeDraft(Request $request): JsonResponse {
        Gate::authorize('create', FormSubmission::class);

        $data = $request->validate([
            'form_template_id' => ['required', 'string'],
            'subject_kind' => ['nullable', 'string', 'in:' . implode(',', array_keys(self::SUBJECT_MAP))],
            'subject_id' => ['nullable', 'string', 'required_with:subject_kind'],
        ]);

        $template = $this->findActiveTemplate((string) $data['form_template_id']);
        $subject = null;
        if (filled($data['subject_kind'] ?? null)) {
            $subject = $this->findSubject((string) $data['subject_kind'], (string) ($data['subject_id'] ?? ''));
            $this->authorizeCarrier($subject);
        }

        $draft = $this->service->startDraft($template, $subject, $this->authUser());

        return response()->json([
            'draft' => $draft->sqid,
            'version' => $draft->version,
            'url' => $this->autosaveUrl($draft),
            'reload_url' => route('form-submissions.edit', $draft),
        ], $draft->wasRecentlyCreated ? 201 : 200);
    }

    /** Eigenen Entwurf verwerfen (Feature 203). */
    public function destroy(FormSubmission $submission): RedirectResponse {
        Gate::authorize('discard', $submission);

        $this->service->discardDraft($submission);

        return redirect()->back()->with('success', __('form.draft.discarded'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse {
        Gate::authorize('create', FormSubmission::class);

        $data = $request->validate([
            'form_template_id' => ['required', 'string'],
            // Entwurf aus dem Speichern je Feld (Feature 203) samt Version.
            'draft' => ['nullable', 'string', 'max:64'],
            'draft_version' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:2000'],
            'subject_kind' => ['nullable', 'string', 'in:' . implode(',', array_keys(self::SUBJECT_MAP))],
            'subject_id' => ['nullable', 'string', 'required_with:subject_kind'],
            'values' => ['nullable', 'array'],
            // Foto-/Datei-Felder (Rang 32) je Feld-Key, Unterschrift als Base64-PNG.
            'files' => ['nullable', 'array'],
            'files.*' => FileAttacher::rule(),
            'signatures' => ['nullable', 'array'],
            'signatures.*' => ['nullable', 'string', 'max:2000000'],
        ]);

        // Ein Entwurf trägt Vorlage, Bezug und eingefrorene Felder selbst — er
        // lässt sich auch absenden, wenn die Vorlage inzwischen archiviert ist.
        $draft = filled($data['draft'] ?? null) ? $this->findOwnDraft((string) $data['draft']) : null;
        if ($draft !== null && ! $draft->isDraft()) {
            // Doppelt abgeschickt: der Entwurf ist schon abgesendet — nichts zweimal anlegen.
            return redirect()->back()->with('success', __('form.flash.submitted'))->withFragment('form-submission-' . $draft->id);
        }
        if ($draft !== null) {
            Gate::authorize('autosave', $draft);
        }
        $template = $draft === null ? $this->findActiveTemplate((string) $data['form_template_id']) : null;

        $subject = null;
        if ($draft === null && filled($data['subject_kind'] ?? null)) {
            $subject = $this->findSubject((string) $data['subject_kind'], (string) ($data['subject_id'] ?? ''));
            $this->authorizeCarrier($subject);
        }

        /** @var array<string, \Illuminate\Http\UploadedFile> $files */
        $files = (array) $request->file('files', []);
        /** @var array<string, string> $signatures */
        $signatures = array_filter((array) $request->input('signatures', []), 'is_string');

        /** @var User $user */
        $user = Auth::user();
        /** @var array<string, mixed> $notes */
        $notes = (array) ($data['notes'] ?? []);

        try {
            $submission = $draft !== null
                ? $this->service->submitDraft($draft, (array) ($data['values'] ?? []), $user, $files, $signatures, notes: $notes, expectedVersion: isset($data['draft_version']) ? (int) $data['draft_version'] : null)
                : $this->service->submit($template ?? abort(404), $subject, (array) ($data['values'] ?? []), $user, $files, $signatures, notes: $notes);
        } catch (FieldDocumentConflictException $e) {
            if ($request->expectsJson()) {
                return response()->json($e->toArray(), 409);
            }

            return redirect()->back()->withInput()->withErrors(['draft' => $e->getMessage()]);
        }

        return redirect()
            ->back()
            ->with('success', __('form.flash.submitted'))
            ->withFragment('form-submission-' . $submission->id);
    }

    /** Read-Only-Seite mit Druck-CSS (analog Fallakte). */
    public function show(FormSubmission $submission): View {
        Gate::authorize('view', $submission);

        $submission->load(['template', 'submitter', 'subject']);

        return view('forms.submissions.show', [
            'submission' => $submission,
            'subjectLabel' => $this->subjectLabel($submission),
        ]);
    }

    /** PDF-Ausgabe des ausgefüllten Formulars (Feature 032, Rang 31). */
    public function pdf(FormSubmission $submission, FormSubmissionPdfRenderer $renderer): Response {
        Gate::authorize('view', $submission);

        $pdf = $renderer->output($submission, $this->subjectLabel($submission));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="formular-' . $submission->id . '.pdf"',
        ]);
    }

    /**
     * Ausfüll-Dialog (Feature 032/203): langer Bogen als großer Dialog mit
     * Abschnittskarten; Speichern je Feld in den Entwurf — vorhanden oder
     * mit dem ersten Feld angelegt.
     */
    private function dialog(FormTemplate $template, ?string $subjectKind, ?string $subjectId, ?FormSubmission $draft, bool $rebased = false): View {
        return view('forms.submissions._form_dialog', [
            'template' => $template,
            'subjectKind' => $subjectKind,
            'subjectId' => $subjectId,
            'draft' => $draft,
            'rebased' => $rebased,
            'schema' => FieldSchema::fromArray($draft !== null ? $draft->fields_snapshot : $template->fields),
            'values' => $draft !== null ? FieldValues::fromArray($draft->values) : new FieldValues,
            'autosave' => $draft !== null
                ? ['url' => $this->autosaveUrl($draft), 'version' => $draft->version, 'reload_url' => route('form-submissions.edit', $draft)]
                : ['start_url' => route('form-submissions.drafts.store'), 'start_fields' => ['form_template_id', 'subject_kind', 'subject_id']],
        ]);
    }

    /** Feld-URL des gemeinsamen Endpunkts; `__FIELD__` ersetzt der Browser. */
    private function autosaveUrl(FormSubmission $draft): string {
        return route('field-documents.autosave', ['type' => $draft->getMorphClass(), 'document' => $draft->sqid, 'field' => '__FIELD__']);
    }

    /** Eigene Einsendung zum Entwurfsschlüssel (404 bei fremder Organisation, 403 bei fremder Person). */
    private function findOwnDraft(string $rawId): FormSubmission {
        $id = Sqid::decodeOrNumeric(FormSubmission::class, $rawId);
        /** @var FormSubmission|null $draft */
        $draft = $id !== null && $id > 0 ? FormSubmission::query()->find($id) : null;
        if ($draft === null) {
            abort(404);
        }
        if ((int) $draft->submitted_by_user_id !== (int) $this->authUser()->id) {
            abort(403);
        }

        return $draft;
    }

    /** Aktive Vorlage über Sqid auflösen (404 bei fremder Org/inaktiv). */
    private function findActiveTemplate(string $rawId): FormTemplate {
        $id = Sqid::decodeOrNumeric(FormTemplate::class, $rawId);
        if ($id === null || $id < 1) {
            abort(404);
        }

        /** @var FormTemplate|null $template */
        $template = FormTemplate::query()->active()->find($id);
        if ($template === null) {
            abort(404);
        }

        return $template;
    }

    /**
     * Löst kind+Sqid in das Bezugs-Model auf (404 bei unbekanntem Typ,
     * fremder Organisation — globaler Scope — oder kaputter Id).
     */
    private function findSubject(string $kind, string $rawId): Model {
        $class = self::SUBJECT_MAP[$kind] ?? null;
        if ($class === null) {
            abort(404);
        }

        $id = Sqid::decodeOrNumeric($class, $rawId);
        if ($id === null || $id < 1) {
            abort(404);
        }

        /** @var Model|null $subject */
        $subject = $class::query()->find($id);
        if ($subject === null) {
            abort(404);
        }

        return $subject;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveOptionalSubjectFromRequest(string $kind, string $rawId): array {
        if ($kind === '') {
            return [null, null];
        }

        $subject = $this->findSubject($kind, $rawId);

        return [$kind, Sqid::encode($subject::class, (int) $subject->getKey())];
    }

    /** Anzeige-Label des Bezugs (Typ-Kürzel + Name/Titel). */
    private function subjectLabel(FormSubmission $submission): ?string {
        $subject = $submission->subject;
        if ($subject === null) {
            return null;
        }

        $kind = array_search($subject::class, self::SUBJECT_MAP, true) ?: 'diary';
        $name = (string) ($subject->getAttribute('title') ?? $subject->getAttribute('name') ?? ('#' . $subject->getKey()));

        return __('form.subject_kind.' . $kind) . ': ' . $name;
    }
}
