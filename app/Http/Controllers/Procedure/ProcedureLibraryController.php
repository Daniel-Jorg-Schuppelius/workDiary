<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureLibraryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Procedure;

use App\Enums\Procedure\{ProcedureProofType, ProcedureStepType};
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Procedure\{ProcedureLibraryStep, ProcedureTemplate};
use App\Services\Procedure\ProcedureTemplateService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Schrittbibliothek (Feature 026, MVP-896): Schritte einmal pflegen und als
 * Kopie in Entwurfsversionen einfügen. Wer Vorlagen bearbeiten darf, pflegt
 * auch die Bibliothek.
 */
class ProcedureLibraryController extends Controller {
    public function __construct(private readonly ProcedureTemplateService $templates) {}

    public function index(): View {
        Gate::authorize(Permission::ProcedureTemplateUpdate->value);

        return view('procedures.library.index', ['steps' => ProcedureLibraryStep::query()->orderBy('label')->paginate(25)]);
    }

    public function create(): View {
        Gate::authorize(Permission::ProcedureTemplateUpdate->value);

        return view('procedures.library._form_dialog', ['step' => null]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(Permission::ProcedureTemplateUpdate->value);
        ProcedureLibraryStep::query()->create($this->validated($request) + ['created_by' => Auth::id(), 'updated_by' => Auth::id()]);

        return redirect()->toList('procedures.library.index')->with('success', __('procedure.library.flash.saved'));
    }

    public function edit(ProcedureLibraryStep $step): View {
        Gate::authorize(Permission::ProcedureTemplateUpdate->value);

        return view('procedures.library._form_dialog', ['step' => $step]);
    }

    public function update(Request $request, ProcedureLibraryStep $step): RedirectResponse {
        Gate::authorize(Permission::ProcedureTemplateUpdate->value);
        $step->fill($this->validated($request, $step) + ['updated_by' => Auth::id()])->save();

        return redirect()->toList('procedures.library.index')->with('success', __('procedure.library.flash.saved'));
    }

    public function destroy(ProcedureLibraryStep $step): RedirectResponse {
        Gate::authorize(Permission::ProcedureTemplateUpdate->value);
        $step->delete();

        return redirect()->toList('procedures.library.index')->with('success', __('procedure.library.flash.deleted'));
    }

    /** Dialog im Designer: Bibliotheksschritte in den Entwurf einfügen. */
    public function insertForm(ProcedureTemplate $template): View {
        Gate::authorize('update', $template);

        return view('procedures.library._insert_dialog', [
            'template' => $template,
            'steps' => ProcedureLibraryStep::query()->orderBy('label')->get(),
        ]);
    }

    public function insert(Request $request, ProcedureTemplate $template): RedirectResponse {
        Gate::authorize('update', $template);
        $draft = $template->versions()->whereNull('published_at')->orderByDesc('version')->first();
        if ($draft === null) {
            return back()->with('error', __('procedure.notice.noDraft'));
        }
        $data = $request->validate(['library_steps' => ['required', 'array', 'min:1'], 'library_steps.*' => ['string']]);
        $ids = array_filter(array_map(static fn (string $sqid): ?int => Sqid::decode(ProcedureLibraryStep::class, $sqid), $data['library_steps']));
        $count = 0;
        foreach (ProcedureLibraryStep::query()->whereIn('id', $ids)->orderBy('label')->get() as $library) {
            $this->templates->addStepFromLibrary($draft, $library);
            $count++;
        }

        return redirect()->route('procedures.edit', $template)->with('success', __('procedure.library.flash.inserted', ['count' => $count]));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ProcedureLibraryStep $step = null): array {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_\-]+$/', Rule::unique('procedure_library_steps', 'code')->where('organization_id', Auth::user()?->organization_id)->ignore($step?->id)],
            'step_kind' => ['required', Rule::enum(ProcedureStepType::class)],
            'label' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_required' => ['nullable', 'boolean'],
            'is_blocking' => ['nullable', 'boolean'],
            'required_role' => ['nullable', 'string', 'max:40'],
            'required_qualification_code' => ['nullable', 'string', 'max:60'],
            'requires_second_person' => ['nullable', 'boolean'],
            'requires_proof_kind' => ['nullable', Rule::enum(ProcedureProofType::class)],
        ]);
        foreach (['is_required', 'is_blocking', 'requires_second_person'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        return $data;
    }
}
