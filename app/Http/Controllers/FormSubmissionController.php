<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormSubmissionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Models\{Asset, Customer, DiaryEntry, FormSubmission, FormTemplate, Project, User};
use App\Services\Form\FormService;
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Ausgefüllte Formulare (Feature 032): Ausfüll-Dialog (Modal, dynamisch
 * gerenderte Felder), Read-Only-/Druck-Seite und gefilterte Liste.
 */
class FormSubmissionController extends Controller {
    use ResolvesGlobalDateRange;

    /**
     * Whitelist der erlaubten Bezugs-Typen. Verhindert, dass Aufrufer
     * beliebige Klassen an `subject_type` setzen können — analog
     * DocumentController::DOCUMENTABLE_MAP.
     *
     * @var array<string, class-string<Model>>
     */
    public const SUBJECT_MAP = [
        'diary' => DiaryEntry::class,
        'customer' => Customer::class,
        'asset' => Asset::class,
        'project' => Project::class,
    ];

    public function __construct(
        private readonly FormService $service,
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
            ->with(['template', 'submitter', 'subject'])
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

        return view('forms.submissions._form_dialog', [
            'template' => $template,
            'subjectKind' => $subjectKind,
            'subjectId' => $subjectId,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', FormSubmission::class);

        $data = $request->validate([
            'form_template_id' => ['required', 'string'],
            'subject_kind' => ['nullable', 'string', 'in:' . implode(',', array_keys(self::SUBJECT_MAP))],
            'subject_id' => ['nullable', 'string', 'required_with:subject_kind'],
            'values' => ['nullable', 'array'],
        ]);

        $template = $this->findActiveTemplate((string) $data['form_template_id']);

        $subject = null;
        if (filled($data['subject_kind'] ?? null)) {
            $subject = $this->findSubject((string) $data['subject_kind'], (string) ($data['subject_id'] ?? ''));
        }

        /** @var User $user */
        $user = Auth::user();
        $submission = $this->service->submit($template, $subject, (array) ($data['values'] ?? []), $user);

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

    /** Aktive Vorlage über Sqid auflösen (404 bei fremder Org/inaktiv). */
    private function findActiveTemplate(string $rawId): FormTemplate {
        $id = Sqid::decode(FormTemplate::class, $rawId);
        if ($id === null && is_numeric($rawId)) {
            $id = (int) $rawId;
        }
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

        $id = Sqid::decode($class, $rawId);
        if ($id === null && is_numeric($rawId)) {
            $id = (int) $rawId;
        }
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
