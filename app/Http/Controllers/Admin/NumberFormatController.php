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
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveNumberFormatRequest;
use App\Models\{NumberFormat, Organization};
use App\Services\Numbering\NumberSequenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NumberFormatController extends Controller {
    public function __construct(
        private readonly NumberSequenceService $numberSequence,
    ) {}

    public function index(): View|RedirectResponse {
        Gate::authorize('manage', NumberFormat::class);

        $organization = $this->currentOrganization();
        if (! $organization instanceof Organization) {
            return redirect()->route('admin.organizations.index')
                ->with('warning', __('Bitte zuerst eine Organisation anlegen bzw. dem aktuellen Benutzer zuweisen.'));
        }

        $rows = [];
        foreach (NumberScope::cases() as $scope) {
            $format = $this->numberSequence->resolveFormat((int) $organization->id, $scope);
            $rows[] = [
                'scope' => $scope,
                'format' => $format,
                'preview' => $this->numberSequence->peekNext($organization, $scope),
                'persisted' => $format->exists,
            ];
        }

        return view('admin.number-formats.index', [
            'organization' => $organization,
            'rows' => $rows,
        ]);
    }

    public function update(SaveNumberFormatRequest $request): RedirectResponse {
        Gate::authorize('manage', NumberFormat::class);

        $organization = $this->currentOrganization();
        if (! $organization instanceof Organization) {
            return redirect()->route('admin.organizations.index')
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
        ]);

        return redirect()->route('admin.number-formats.index')
            ->with('success', __('Nummernkreis-Format aktualisiert.'));
    }

    private function currentOrganization(): ?Organization {
        if (app()->bound('currentOrganization')) {
            $bound = app('currentOrganization');
            if ($bound instanceof Organization) {
                return $bound;
            }
        }

        /** @var \App\Models\User|null $user */
        $user = \Illuminate\Support\Facades\Auth::user();

        return $user?->organization;
    }
}
