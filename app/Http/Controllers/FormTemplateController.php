<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormTemplateController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Form\FormTemplateStatus;
use App\Models\{FormTemplate, User};
use App\Services\Form\FormService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Vorlagen-Verwaltung (Feature 032): Listenseite + Modal-CRUD.
 * Felddefinition kommt als dynamische Zeilen fields[i][label|type|required|
 * options|help|unit] aus dem Dialog; Strukturvalidierung übernimmt
 * FormFieldDefinition im Service (ValidationException → 422/Errors).
 */
class FormTemplateController extends Controller {
    public function __construct(
        private readonly FormService $service,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', FormTemplate::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
        ];

        $query = FormTemplate::query()->with('creator')->withCount('submissions');

        if ($filters['q'] !== '') {
            $query->where('name', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $filters['q']) . '%');
        }
        if (FormTemplateStatus::tryFrom($filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }

        $templates = $query->orderBy('name')->paginate(25)->withQueryString();

        return view('forms.templates.index', [
            'templates' => $templates,
            'filters' => $filters,
            'hasActiveFilters' => $filters['q'] !== '' || $filters['status'] !== 'all',
            'canManage' => Gate::allows('create', FormTemplate::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', FormTemplate::class);

        return view('forms.templates._form_dialog', ['template' => null]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', FormTemplate::class);

        $data = $this->validateTemplate($request);

        /** @var User $creator */
        $creator = Auth::user();
        $this->service->createTemplate($creator, $data);

        return redirect()
            ->back()
            ->with('success', __('form.flash.template_created'));
    }

    public function edit(FormTemplate $template): View {
        Gate::authorize('update', $template);

        return view('forms.templates._form_dialog', ['template' => $template]);
    }

    public function update(Request $request, FormTemplate $template): RedirectResponse {
        Gate::authorize('update', $template);

        $data = $this->validateTemplate($request);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->updateTemplate($template, $actor, $data);

        return redirect()
            ->back()
            ->with('success', __('form.flash.template_updated'));
    }

    public function activate(FormTemplate $template): RedirectResponse {
        Gate::authorize('activate', $template);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->activate($template, $actor);

        return redirect()
            ->back()
            ->with('success', __('form.flash.template_activated'));
    }

    public function archive(FormTemplate $template): RedirectResponse {
        Gate::authorize('archive', $template);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->archive($template, $actor);

        return redirect()
            ->back()
            ->with('success', __('form.flash.template_archived'));
    }

    public function destroy(FormTemplate $template): RedirectResponse {
        Gate::authorize('delete', $template);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->deleteTemplate($template, $actor);

        return redirect()
            ->route('form-templates.index')
            ->with('success', __('form.flash.template_deleted'));
    }

    /**
     * Basis-Validierung des Dialogs; die Tiefen-Validierung der
     * Felddefinition (keys/types/options) macht FormFieldDefinition.
     *
     * @return array<string, mixed>
     */
    private function validateTemplate(Request $request): array {
        return $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'fields' => ['required', 'array'],
            'fields.*' => ['array'],
            'fields.*.label' => ['nullable', 'string', 'max:160'],
            'fields.*.type' => ['nullable', 'string', 'max:32'],
            'fields.*.required' => ['nullable'],
            'fields.*.options' => ['nullable', 'string', 'max:2000'],
            'fields.*.help' => ['nullable', 'string', 'max:500'],
            'fields.*.unit' => ['nullable', 'string', 'max:20'],
            // Bedingungslogik (Rang 33): {field, op, value}; Auflösung/Zyklen-
            // prüfung übernimmt FormFieldDefinition::normalize.
            'fields.*.visible_if' => ['nullable', 'array'],
            'fields.*.visible_if.field' => ['nullable', 'string', 'max:160'],
            'fields.*.visible_if.op' => ['nullable', 'string', 'max:16'],
            'fields.*.visible_if.value' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
