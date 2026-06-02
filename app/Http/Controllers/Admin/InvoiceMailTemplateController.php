<?php
/*
 * Created on   : Mon May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceMailTemplateController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvoiceMailTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, DB};

class InvoiceMailTemplateController extends Controller {
    public function index(Request $request): View {
        $this->authorizeBilling();
        $search = $request->string('q')->toString();
        $templates = InvoiceMailTemplate::query()
            ->when($search !== '', fn($q) => $q->where(function ($w) use ($search): void {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            }))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('admin.invoice-mail-templates.index', [
            'templates' => $templates,
            'variables' => InvoiceMailTemplate::availableVariables(),
            'search' => $search,
        ]);
    }

    public function create(): View {
        $this->authorizeBilling();

        return view('admin.invoice-mail-templates.form', [
            'template' => new InvoiceMailTemplate(['is_default' => false]),
            'variables' => InvoiceMailTemplate::availableVariables(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $this->authorizeBilling();
        $data = $this->validateInput($request);

        DB::transaction(function () use ($data): InvoiceMailTemplate {
            $tpl = InvoiceMailTemplate::create($data + [
                'organization_id' => Auth::user()?->organization_id,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            $this->ensureSingleDefault($tpl);

            return $tpl;
        });

        return redirect()->route('admin.invoice-mail-templates.index')
            ->with('status', __('Template gespeichert.'));
    }

    public function edit(InvoiceMailTemplate $invoiceMailTemplate): View {
        $this->authorizeBilling();

        return view('admin.invoice-mail-templates.form', [
            'template' => $invoiceMailTemplate,
            'variables' => InvoiceMailTemplate::availableVariables(),
        ]);
    }

    public function update(Request $request, InvoiceMailTemplate $invoiceMailTemplate): RedirectResponse {
        $this->authorizeBilling();
        $data = $this->validateInput($request);

        DB::transaction(function () use ($invoiceMailTemplate, $data): void {
            $invoiceMailTemplate->update($data + ['updated_by' => Auth::id()]);
            $this->ensureSingleDefault($invoiceMailTemplate);
        });

        return redirect()->route('admin.invoice-mail-templates.index')
            ->with('status', __('Template aktualisiert.'));
    }

    public function destroy(InvoiceMailTemplate $invoiceMailTemplate): RedirectResponse {
        $this->authorizeBilling();
        $invoiceMailTemplate->delete();

        return redirect()->route('admin.invoice-mail-templates.index')
            ->with('status', __('Template gelöscht.'));
    }

    /** @return array<string, mixed> */
    private function validateInput(Request $request): array {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_default' => ['nullable', 'boolean'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string', 'max:65535'],
            'body_text' => ['required', 'string', 'max:65535'],
        ]);
    }

    /**
     * Setzt is_default für alle anderen Templates der gleichen Org-Scope auf false,
     * wenn $tpl is_default = true ist.
     */
    private function ensureSingleDefault(InvoiceMailTemplate $tpl): void {
        if (! $tpl->is_default) {
            return;
        }
        InvoiceMailTemplate::query()
            ->where('id', '!=', $tpl->id)
            ->where(function ($q) use ($tpl): void {
                if ($tpl->organization_id === null) {
                    $q->whereNull('organization_id');
                } else {
                    $q->where('organization_id', $tpl->organization_id);
                }
            })
            ->update(['is_default' => false]);
    }

    private function authorizeBilling(): void {
        abort_unless(Auth::user()?->canManageBilling() === true, 403);
    }
}
