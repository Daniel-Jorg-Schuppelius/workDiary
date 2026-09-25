<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractTemplateController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Models\Contract\{Contract, ContractTemplate};
use App\Models\Platform\User;
use App\Services\Contract\ContractTemplateService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Vertragsvorlagen (Feature 079, MVP-893): entstehen aus einem Vertrag;
 * hier Name, Aktivierung und Löschen. Wer Verträge anlegen darf, pflegt sie.
 */
class ContractTemplateController extends Controller {
    public function index(): View {
        Gate::authorize('create', Contract::class);

        return view('contracts.templates.index', [
            'templates' => ContractTemplate::query()->orderBy('name')->paginate(25),
        ]);
    }

    public function edit(ContractTemplate $template): View {
        Gate::authorize('create', Contract::class);

        return view('contracts.templates._form_dialog', ['template' => $template]);
    }

    public function update(Request $request, ContractTemplate $template): RedirectResponse {
        Gate::authorize('create', Contract::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $template->forceFill(['name' => $data['name'], 'is_active' => (bool) ($data['is_active'] ?? false), 'updated_by' => Auth::id()])->save();

        return redirect()->toList('contracts.templates.index')->with('status', __('contract.template.flash.updated'));
    }

    public function destroy(ContractTemplate $template): RedirectResponse {
        Gate::authorize('create', Contract::class);
        $template->delete();

        return redirect()->toList('contracts.templates.index')->with('status', __('contract.template.flash.deleted'));
    }

    public function fromContractForm(Contract $contract): View {
        Gate::authorize('create', Contract::class);
        Gate::authorize('view', $contract);

        return view('contracts.templates._from_contract_dialog', ['contract' => $contract]);
    }

    public function fromContract(Request $request, Contract $contract, ContractTemplateService $templates): RedirectResponse {
        Gate::authorize('create', Contract::class);
        Gate::authorize('view', $contract);
        $data = $request->validate(['name' => ['required', 'string', 'max:180']]);

        /** @var User $actor */
        $actor = Auth::user();
        $template = $templates->fromContract($contract, $data['name'], $actor);

        return redirect()->route('contracts.show', $contract)->with('status', __('contract.template.flash.created', ['name' => $template->name]));
    }
}
