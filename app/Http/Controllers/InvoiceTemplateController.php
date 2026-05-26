<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceTemplateController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Requests\SaveInvoiceTemplateRequest;
use App\Models\InvoiceTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Auth, DB, Gate};
use Illuminate\View\View;

class InvoiceTemplateController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', InvoiceTemplate::class);

        return view('invoice_templates.index', [
            'templates' => InvoiceTemplate::query()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', InvoiceTemplate::class);

        return view('invoice_templates.form', [
            'template' => new InvoiceTemplate(),
        ]);
    }

    public function store(SaveInvoiceTemplateRequest $request): RedirectResponse {
        Gate::authorize('create', InvoiceTemplate::class);

        $data = $request->validated();
        $isDefault = (bool) ($data['is_default'] ?? false);

        DB::transaction(function () use ($data, $isDefault): void {
            if ($isDefault) {
                InvoiceTemplate::query()
                    ->where('organization_id', (int) Auth::user()?->organization_id)
                    ->update(['is_default' => false]);
            }
            InvoiceTemplate::create(array_merge($data, [
                'organization_id' => Auth::user()?->organization_id,
                'is_default' => $isDefault,
            ]));
        });

        return redirect()->route('invoice-templates.index')
            ->with('status', __('Vorlage gespeichert.'));
    }

    public function edit(InvoiceTemplate $template): View {
        Gate::authorize('update', $template);

        return view('invoice_templates.form', ['template' => $template]);
    }

    public function update(SaveInvoiceTemplateRequest $request, InvoiceTemplate $template): RedirectResponse {
        Gate::authorize('update', $template);

        $data = $request->validated();
        $isDefault = (bool) ($data['is_default'] ?? false);

        DB::transaction(function () use ($template, $data, $isDefault): void {
            if ($isDefault) {
                InvoiceTemplate::query()
                    ->where('organization_id', $template->organization_id)
                    ->where('id', '!=', $template->id)
                    ->update(['is_default' => false]);
            }
            $template->update(array_merge($data, ['is_default' => $isDefault]));
        });

        return redirect()->route('invoice-templates.index')
            ->with('status', __('Vorlage aktualisiert.'));
    }

    public function destroy(InvoiceTemplate $template): RedirectResponse {
        Gate::authorize('delete', $template);
        $template->delete();

        return redirect()->route('invoice-templates.index')
            ->with('status', __('Vorlage gelöscht.'));
    }
}
