<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LabelTemplateController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Models\LabelTemplate;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\View\View;

/**
 * Etiketten-Layout-Designer (Feature 047/048, E5): benannte Etikettenvorlagen
 * (Papiergröße, Ausrichtung, QR, dargestellte Felder, Standardvorlage) verwalten.
 * Sehen mit inventory.viewAny, Verwalten mit inventory.configure.
 */
class LabelTemplateController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(): View {
        $this->canView();

        return view('inventory.label-templates.index', [
            'templates' => LabelTemplate::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'canManage' => Auth::user()?->can(P::InventoryConfigure->value) ?? false,
        ]);
    }

    public function create(): View {
        $this->canManage();

        return view('inventory.label-templates._form_dialog', ['isDialog' => true, 'template' => null]);
    }

    public function store(Request $request): RedirectResponse {
        $this->canManage();
        $template = LabelTemplate::query()->create($this->validated($request));
        $this->applyDefault($template);

        return redirect()->route('inventory.label-templates.index')->with('success', __('inventory.label_template.flash.saved'));
    }

    public function edit(LabelTemplate $labelTemplate): View {
        $this->canManage();

        return view('inventory.label-templates._form_dialog', ['isDialog' => true, 'template' => $labelTemplate]);
    }

    public function update(Request $request, LabelTemplate $labelTemplate): RedirectResponse {
        $this->canManage();
        $labelTemplate->update($this->validated($request));
        $this->applyDefault($labelTemplate);

        return redirect()->route('inventory.label-templates.index')->with('success', __('inventory.label_template.flash.saved'));
    }

    public function destroy(LabelTemplate $labelTemplate): RedirectResponse {
        $this->canManage();
        $labelTemplate->delete();

        return redirect()->route('inventory.label-templates.index')->with('success', __('inventory.label_template.flash.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'paper_size' => ['required', 'in:a6,a7,a8'],
            'orientation' => ['required', 'in:portrait,landscape'],
            'with_qr' => ['nullable', 'boolean'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['in:' . implode(',', LabelTemplate::FIELDS)],
            'is_default' => ['nullable', 'boolean'],
        ]);

        return [
            'organization_id' => $this->currentOrganization()->id,
            'name' => $data['name'],
            'paper_size' => $data['paper_size'],
            'orientation' => $data['orientation'],
            'with_qr' => (bool) ($data['with_qr'] ?? false),
            'fields' => array_values($data['fields']),
            'is_default' => (bool) ($data['is_default'] ?? false),
        ];
    }

    /** Stellt sicher, dass je Organisation höchstens eine Standardvorlage existiert. */
    private function applyDefault(LabelTemplate $template): void {
        if (! $template->is_default) {
            return;
        }
        DB::transaction(function () use ($template): void {
            LabelTemplate::query()->where('id', '!=', $template->id)->update(['is_default' => false]);
        });
    }

    private function canView(): void {
        abort_unless((Auth::user()?->can(P::InventoryViewAny->value) ?? false) || (Auth::user()?->can(P::InventoryConfigure->value) ?? false), 403);
    }

    private function canManage(): void {
        abort_unless(Auth::user()?->can(P::InventoryConfigure->value) ?? false, 403);
    }
}
