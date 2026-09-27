<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureImportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Procedure;

use App\Enums\Procedure\ProcedureStepType;
use App\Exceptions\DocumentTextUnavailableException;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Procedure\ProcedureTemplate;
use App\Services\Document\DocumentTextExtractor;
use App\Services\Procedure\{ProcedureTemplateService, WorkInstructionParser};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Arbeitsanweisung importieren (Feature 026, MVP-913): Text, PDF oder
 * Textverarbeitungsdatei → Schrittvorschläge → Vorschau → neue Prozedur als
 * Entwurf. Nichts wird ohne die Vorschau übernommen.
 */
class ProcedureImportController extends Controller {
    use ResolvesCurrentOrganization;

    public function form(): View {
        Gate::authorize('create', ProcedureTemplate::class);

        return view('procedures.import._form_dialog');
    }

    public function preview(Request $request, DocumentTextExtractor $extractor, WorkInstructionParser $parser): View|RedirectResponse {
        Gate::authorize('create', ProcedureTemplate::class);
        $data = $request->validate([
            'file' => ['nullable', 'file', 'max:20480', 'mimes:txt,md,pdf,docx,doc,odt,rtf', 'required_without:text'],
            'text' => ['nullable', 'string', 'max:100000', 'required_without:file'],
        ]);

        $text = (string) ($data['text'] ?? '');
        $source = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $source = $file->getClientOriginalName();
            try {
                $text = $extractor->extractFile((string) $file->getRealPath(), $source, (string) $file->getMimeType());
            } catch (DocumentTextUnavailableException) {
                return redirect()->toList('procedures.index')->with('error', __('procedure.import.unreadable'));
            }
        }

        $parsed = $parser->parse($text);
        if ($parsed['steps'] === []) {
            return redirect()->toList('procedures.index')->with('error', __('procedure.import.no_steps'));
        }

        $name = $parsed['title'] ?? ($source !== null ? pathinfo($source, PATHINFO_FILENAME) : __('procedure.import.default_name'));

        return view('procedures.import.preview', [
            'name' => Str::limit($name, 180, ''),
            'code' => Str::upper(Str::limit(Str::slug($name, '_'), 50, '')),
            'steps' => $parsed['steps'],
            'source' => $source,
        ]);
    }

    public function store(Request $request, ProcedureTemplateService $templates): RedirectResponse {
        Gate::authorize('create', ProcedureTemplate::class);
        $organization = $this->currentOrganization();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_\-]+$/', Rule::unique('procedure_templates', 'code')->where('organization_id', $organization->id)],
            'steps' => ['required', 'array', 'max:' . WorkInstructionParser::MAX_STEPS],
            'steps.*.include' => ['nullable', 'boolean'],
            'steps.*.label' => ['required', 'string', 'max:180'],
            'steps.*.description' => ['nullable', 'string', 'max:2000'],
            'steps.*.step_type' => ['required', Rule::enum(ProcedureStepType::class)],
        ]);

        $steps = [];
        foreach (array_values($data['steps']) as $row) {
            if (! (bool) ($row['include'] ?? false)) {
                continue;
            }
            $steps[] = ['code' => sprintf('S%02d', count($steps) + 1), 'step_type' => $row['step_type'], 'label' => $row['label'], 'description' => $row['description'] ?? null];
        }
        if ($steps === []) {
            return back()->withInput()->withErrors(['steps' => __('procedure.import.no_steps')]);
        }

        $template = DB::transaction(function () use ($templates, $organization, $data, $steps): ProcedureTemplate {
            $template = $templates->create($organization, $this->authUser(), ['code' => $data['code'], 'name' => $data['name'], 'description' => __('procedure.import.description')]);
            $templates->syncSteps($template->versions()->firstOrFail(), $steps);

            return $template;
        });

        return redirect()->route('procedures.edit', $template)->with('success', __('procedure.import.done', ['count' => count($steps)]));
    }
}
