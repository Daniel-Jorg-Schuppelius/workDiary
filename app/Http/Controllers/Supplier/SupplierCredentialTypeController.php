<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCredentialTypeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Supplier;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Classification\Tag;
use App\Models\Supplier\SupplierCredentialType;
use App\Rules\ExistsInCurrentOrganization;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Eigene Nachweistypen der Organisation (Feature 117, MVP-1586). Die
 * Katalogzeilen der Installation bleiben Vorlage: sie sind hier nur lesbar und
 * lassen sich als eigener Typ übernehmen. Deaktivieren statt löschen, weil
 * hinterlegte Nachweise auf den Typ verweisen.
 */
class SupplierCredentialTypeController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(): View {
        Gate::authorize(P::SupplierCredentialTypeManage->value);
        $organizationId = $this->currentOrganization()->id;

        return view('suppliers.credential-types.index', [
            'types' => SupplierCredentialType::query()
                ->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $organizationId))
                ->orderByRaw('organization_id is null')->orderBy('name')
                ->paginate(50),
            'tags' => Tag::query()->orderBy('name')->get()->keyBy('id'),
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize(P::SupplierCredentialTypeManage->value);

        // Vorlage aus dem Katalog: nur dessen Zeilen, nie Typen anderer Organisationen.
        $templateId = Sqid::decodeOrNumeric(SupplierCredentialType::class, $request->query('vorlage'));
        $template = $templateId !== null ? SupplierCredentialType::query()->whereNull('organization_id')->find($templateId) : null;

        return $this->form(null, $template);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(P::SupplierCredentialTypeManage->value);
        $organizationId = $this->currentOrganization()->id;
        $data = $this->validated($request);

        $base = Str::limit(Str::slug($data['name'], '_'), 40, '') ?: 'nachweis';
        $code = $base;
        for ($i = 2; SupplierCredentialType::query()->where('organization_id', $organizationId)->where('code', $code)->exists(); $i++) {
            $code = $base . '_' . $i;
        }

        SupplierCredentialType::query()->create($data + ['organization_id' => $organizationId, 'code' => $code]);

        return redirect()->route('suppliers.credential-types.index')->with('success', __('procurement.credential_types.saved'));
    }

    public function edit(SupplierCredentialType $credentialType): View {
        $this->authorizeOwn($credentialType);

        return $this->form($credentialType, null);
    }

    public function update(Request $request, SupplierCredentialType $credentialType): RedirectResponse {
        $this->authorizeOwn($credentialType);
        $credentialType->update($this->validated($request));

        return redirect()->route('suppliers.credential-types.index')->with('success', __('procurement.credential_types.saved'));
    }

    private function form(?SupplierCredentialType $type, ?SupplierCredentialType $template): View {
        return view('suppliers.credential-types._form_dialog', [
            'type' => $type,
            'template' => $template,
            'tags' => Tag::query()->orderBy('name')->get(),
        ]);
    }

    /** Eigener Typ der Organisation — Katalogzeilen und fremde Typen sind keine Ressource. */
    private function authorizeOwn(SupplierCredentialType $type): void {
        Gate::authorize(P::SupplierCredentialTypeManage->value);
        abort_unless($type->organization_id !== null && (int) $type->organization_id === (int) $this->currentOrganization()->id, 404);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $request->merge(['required_tag_ids' => array_values(array_filter(array_map(
            static fn ($value): ?int => Sqid::decodeOrNumeric(Tag::class, $value),
            (array) $request->input('required_tag_ids', []),
        )))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:500'],
            'default_validity_months' => ['nullable', 'integer', 'min:1', 'max:240'],
            'warn_days_before' => ['required', 'integer', 'min:0', 'max:365'],
            'blocking_mode' => ['required', Rule::in([SupplierCredentialType::MODE_WARN, SupplierCredentialType::MODE_BLOCK])],
            'is_required_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'required_tag_ids' => ['nullable', 'array'],
            'required_tag_ids.*' => ['integer', new ExistsInCurrentOrganization('tags')],
        ]);
        $data['required_tag_ids'] = ($data['required_tag_ids'] ?? []) === [] ? null : array_map('intval', $data['required_tag_ids']);

        return $data;
    }
}
