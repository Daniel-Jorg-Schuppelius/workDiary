<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MedicalCheckupOccasionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Safety;

use App\Enums\Safety\MedicalCheckupKind;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Safety\{MedicalCheckup, MedicalCheckupOccasion};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Katalog der Vorsorgeanlässe mit Intervall (MVP-986); Rechte wie das Vorsorge-Register. */
class MedicalCheckupOccasionController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(): View {
        Gate::authorize('viewAny', MedicalCheckup::class);

        return view('safety.checkups.occasions', [
            'occasions' => MedicalCheckupOccasion::query()->orderByDesc('is_active')->orderBy('label')->get(),
            'canManage' => Gate::allows('create', MedicalCheckup::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', MedicalCheckup::class);

        return view('safety.checkups._occasion_dialog', ['occasion' => null]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', MedicalCheckup::class);
        MedicalCheckupOccasion::query()->create($this->validated($request, null) + [
            'organization_id' => $this->currentOrganization()->id,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('safety.checkups.occasions.index')->with('success', __('safety.register.flash.occasion_saved'));
    }

    public function edit(MedicalCheckupOccasion $occasion): View {
        Gate::authorize('create', MedicalCheckup::class);

        return view('safety.checkups._occasion_dialog', ['occasion' => $occasion]);
    }

    public function update(Request $request, MedicalCheckupOccasion $occasion): RedirectResponse {
        Gate::authorize('create', MedicalCheckup::class);
        $occasion->update($this->validated($request, $occasion) + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('safety.checkups.occasions.index')->with('success', __('safety.register.flash.occasion_saved'));
    }

    /** @return array{label: string, kind: string, interval_months: int|null} */
    private function validated(Request $request, ?MedicalCheckupOccasion $occasion): array {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:180', Rule::unique('medical_checkup_occasions', 'label')->where('organization_id', $this->currentOrganization()->id)->ignore($occasion?->id)],
            'kind' => ['required', 'string', Rule::enum(MedicalCheckupKind::class)],
            'interval_months' => ['nullable', 'integer', 'between:1,120'],
        ]);

        return [
            'label' => (string) $data['label'],
            'kind' => (string) $data['kind'],
            'interval_months' => isset($data['interval_months']) ? (int) $data['interval_months'] : null,
        ];
    }
}
