<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityOffsetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Sustainability;

use App\Enums\Sustainability\SustainabilityOffsetKind;
use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Sustainability\SustainabilityOffset;
use App\Services\Sustainability\SustainabilityClaimChecker;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Klimanachweise und Prüfung von Umweltaussagen (MVP-961). */
class SustainabilityOffsetController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(Request $request): View {
        Gate::authorize(P::SustainabilityViewAny->value);
        $offsets = SustainabilityOffset::query()->orderByDesc('claim_year')->orderByDesc('id')->get();

        return view('sustainability.offsets', [
            'offsets' => $offsets,
            'totals' => $offsets->groupBy('claim_year')->map(static fn ($rows): string => NumberHelper::sumPrecise($rows->pluck('quantity_t')->all(), 3)),
            'findings' => (array) $request->session()->get('claim_findings', []),
            'claimText' => (string) $request->session()->get('claim_text', ''),
            'canManage' => Gate::allows(P::SustainabilityManage->value),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(P::SustainabilityManage->value);
        $data = $request->validate([
            'kind' => ['required', Rule::enum(SustainabilityOffsetKind::class)],
            'provider' => ['required', 'string', 'max:200'],
            'standard' => ['nullable', 'string', 'max:100'],
            'project_name' => ['nullable', 'string', 'max:200'],
            'quantity_t' => ['required', 'numeric', 'min:0.001', 'max:999999999'],
            'vintage_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'claim_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'retired_on' => ['nullable', 'date'],
            'registry_reference' => ['nullable', 'string', 'max:200'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $offset = SustainabilityOffset::query()->create($data + ['organization_id' => $this->currentOrganization()->id, 'created_by' => $this->authUser()->id]);
        $offset->audit('sustainability.offsetRecorded', ['kind' => $offset->kind->value, 'quantity_t' => (string) $offset->quantity_t]);

        return back()->with('success', __('sustainability.offset.flash.saved'));
    }

    public function destroy(SustainabilityOffset $offset): RedirectResponse {
        Gate::authorize(P::SustainabilityManage->value);
        $offset->audit('sustainability.offsetRemoved', ['quantity_t' => (string) $offset->quantity_t]);
        $offset->delete();

        return back()->with('success', __('sustainability.offset.flash.deleted'));
    }

    public function checkClaims(Request $request, SustainabilityClaimChecker $checker): RedirectResponse {
        Gate::authorize(P::SustainabilityViewAny->value);
        $data = $request->validate(['claim_text' => ['required', 'string', 'max:5000']]);

        return back()->with(['claim_findings' => $checker->check($data['claim_text']), 'claim_text' => $data['claim_text'], 'claim_checked' => true]);
    }
}
