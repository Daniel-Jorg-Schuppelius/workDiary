<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisDeadlineTemplateController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Crisis;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Crisis\{CrisisCase, CrisisDeadlineTemplate};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Meldefristen je Organisation (Feature 070, MVP-1589): eigene Fristen einer
 * Krisen-Kategorie ersetzen die Vorgaben dieser Kategorie. Die globalen Zeilen
 * bleiben Vorlage und werden hier nur gezeigt.
 */
class CrisisDeadlineTemplateController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(): View {
        Gate::authorize('viewAny', CrisisCase::class);
        $organizationId = (int) $this->currentOrganization()->id;
        $own = CrisisDeadlineTemplate::query()->where('organization_id', $organizationId);

        return view('crisis.deadlines.index', [
            'templates' => (clone $own)->orderBy('category')->orderByRaw('offset_hours IS NULL DESC')->orderBy('offset_hours')->orderBy('id')->paginate(25)->withQueryString(),
            'defaults' => CrisisDeadlineTemplate::query()->whereNull('organization_id')->where('active', true)->orderBy('category')->orderByRaw('offset_hours IS NULL DESC')->orderBy('offset_hours')->orderBy('id')->get(),
            // Kategorien mit eigenen Fristen (nicht mehr übernehmbar) und solche, deren Vorgaben aktive eigene Fristen ersetzen.
            'ownCategories' => (clone $own)->distinct()->pluck('category')->all(),
            'overridden' => (clone $own)->where('active', true)->distinct()->pluck('category')->all(),
            'canManage' => Gate::allows('create', CrisisCase::class),
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', CrisisCase::class);
        $category = (string) $request->query('category', '');

        return view('crisis.deadlines._form_dialog', ['template' => null, 'category' => in_array($category, CrisisCase::CATEGORIES, true) ? $category : null]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', CrisisCase::class);
        $template = CrisisDeadlineTemplate::query()->create($this->validated($request) + ['organization_id' => $this->currentOrganization()->id]);
        $template->audit('crisis.deadline_template_created', ['category' => $template->category, 'label' => $template->label, 'offset_hours' => $template->offset_hours]);

        return redirect()->toList('crisis.deadlines.index')->with('success', __('crisis.deadlines.flash.saved'));
    }

    /** Vorgaben einer Kategorie als eigene Fristen übernehmen — Ausgangspunkt zum Anpassen. */
    public function adopt(Request $request): RedirectResponse {
        Gate::authorize('create', CrisisCase::class);
        $data = $request->validate(['category' => ['required', 'string', Rule::in(CrisisCase::CATEGORIES)]]);
        $organizationId = (int) $this->currentOrganization()->id;
        if (CrisisDeadlineTemplate::query()->where('organization_id', $organizationId)->where('category', $data['category'])->exists()) {
            return back()->with('error', __('crisis.deadlines.flash.already_own'));
        }
        foreach (CrisisDeadlineTemplate::query()->whereNull('organization_id')->where('category', $data['category'])->where('active', true)->orderBy('id')->get() as $default) {
            $template = CrisisDeadlineTemplate::query()->create(['organization_id' => $organizationId, 'category' => $default->category, 'label' => $default->label, 'offset_hours' => $default->offset_hours, 'source' => $default->source, 'active' => true]);
            $template->audit('crisis.deadline_template_created', ['category' => $template->category, 'label' => $template->label, 'offset_hours' => $template->offset_hours, 'adopted' => true]);
        }

        return redirect()->toList('crisis.deadlines.index')->with('success', __('crisis.deadlines.flash.adopted'));
    }

    public function edit(CrisisDeadlineTemplate $template): View {
        Gate::authorize('create', CrisisCase::class);
        $this->assertSameOrganization($template);

        return view('crisis.deadlines._form_dialog', ['template' => $template, 'category' => $template->category]);
    }

    public function update(Request $request, CrisisDeadlineTemplate $template): RedirectResponse {
        Gate::authorize('create', CrisisCase::class);
        $this->assertSameOrganization($template);
        $template->update($this->validated($request));

        return redirect()->toList('crisis.deadlines.index')->with('success', __('crisis.deadlines.flash.saved'));
    }

    public function toggle(CrisisDeadlineTemplate $template): RedirectResponse {
        Gate::authorize('create', CrisisCase::class);
        $this->assertSameOrganization($template);
        $template->update(['active' => ! $template->active]);

        return redirect()->toList('crisis.deadlines.index')->with('success', __($template->active ? 'crisis.deadlines.flash.activated' : 'crisis.deadlines.flash.deactivated'));
    }

    /** @return array{category: string, label: string, offset_hours: int|null, source: string|null, active: bool} */
    private function validated(Request $request): array {
        $data = $request->validate([
            'category' => ['required', 'string', Rule::in(CrisisCase::CATEGORIES)],
            'label' => ['required', 'string', 'max:200'],
            // Leer = unverzüglich.
            'offset_hours' => ['nullable', 'integer', 'min:0', 'max:87600'],
            'source' => ['nullable', 'string', 'max:300'],
        ]);

        return [
            'category' => (string) $data['category'],
            'label' => (string) $data['label'],
            'offset_hours' => isset($data['offset_hours']) ? (int) $data['offset_hours'] : null,
            'source' => $data['source'] ?? null,
            'active' => $request->boolean('active', true),
        ];
    }
}
