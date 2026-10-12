<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberFormatController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\Numbering\NumberScope;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Numbering\SaveNumberFormatRequest;
use App\Models\Numbering\NumberFormat;
use App\Models\Platform\Organization;
use App\Services\Numbering\{NumberAuthority, NumberSequenceService};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NumberFormatController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly NumberSequenceService $numberSequence,
        private readonly NumberAuthority $authority,
    ) {}

    public function index(): View|RedirectResponse {
        Gate::authorize('manage', NumberFormat::class);

        $organization = $this->currentOrganizationOrUserOrganization();
        if (! $organization instanceof Organization) {
            return redirect()->toList('admin.organizations.index')
                ->with('warning', __('Bitte zuerst eine Organisation anlegen bzw. dem aktuellen Benutzer zuweisen.'));
        }

        $rows = [];
        foreach (NumberScope::cases() as $scope) {
            $format = $this->numberSequence->resolveFormat((int) $organization->id, $scope);
            $source = $this->authority->configuredSource($organization, $scope);
            $rows[] = [
                'scope' => $scope,
                'format' => $format,
                'preview' => $this->numberSequence->peekNext($organization, $scope),
                'persisted' => $format->exists,
                'source' => $source,
                'sourceLabel' => $this->authority->sourceLabel($this->authority->sourceFor($organization, $scope)),
                // Kunden- und Lieferantennummern wählt man hier; Belegkreise steuert das Programm selbst (MVP-1130).
                'sourceOptions' => in_array($scope, SaveNumberFormatRequest::SELECTABLE_SOURCE_SCOPES, true)
                    ? $this->authority->sourceOptions($organization, $scope)
                    : null,
            ];
        }

        return view('admin.number-formats.index', [
            'organization' => $organization,
            'rows' => $rows,
        ]);
    }

    public function update(SaveNumberFormatRequest $request): RedirectResponse {
        Gate::authorize('manage', NumberFormat::class);

        $organization = $this->currentOrganizationOrUserOrganization();
        if (! $organization instanceof Organization) {
            return redirect()->toList('admin.organizations.index')
                ->with('warning', __('Bitte zuerst eine Organisation anlegen bzw. dem aktuellen Benutzer zuweisen.'));
        }

        $scope = NumberScope::from((string) $request->input('scope'));
        $this->numberSequence->setFormat($organization, $scope, [
            'prefix' => (string) $request->string('prefix'),
            'prefix_separator' => (string) $request->string('prefix_separator'),
            'include_year' => (bool) $request->boolean('include_year'),
            'year_separator' => (string) $request->string('year_separator'),
            'padding' => (int) $request->integer('padding'),
            'reset_per_year' => (bool) $request->boolean('reset_per_year'),
            'starts_at' => (int) $request->integer('starts_at'),
        ] + ($request->filled('source') ? ['source' => (string) $request->string('source')] : []));

        return redirect()->route('admin.number-formats.index')
            ->with('success', __('Nummernkreis-Format aktualisiert.'));
    }
}
