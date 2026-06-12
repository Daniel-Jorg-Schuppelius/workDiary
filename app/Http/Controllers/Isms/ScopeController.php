<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScopeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Isms;

use App\Http\Controllers\Controller;
use App\Models\Isms\IsmsScope;
use App\Models\User;
use App\Services\Isms\ScopeService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Minimal-Verwaltung der Geltungsbereiche (Feature 046): Liste +
 * Modal-CRUD. Nur isms.manage (IsmsScopePolicy); der Default-Scope
 * („Gesamtorganisation") ist nicht löschbar (Policy + Serviceregel).
 */
class ScopeController extends Controller {
    public function __construct(
        private readonly ScopeService $service,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', IsmsScope::class);

        return view('isms.scopes.index', [
            'scopes' => IsmsScope::query()
                ->withCount(['statements', 'risks'])
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'canManage' => Gate::allows('create', IsmsScope::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', IsmsScope::class);

        return view('isms.scopes._form_dialog', ['scope' => null]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', IsmsScope::class);

        /** @var User $creator */
        $creator = Auth::user();
        $this->service->create($creator, $this->validateScope($request));

        return redirect()
            ->back()
            ->with('success', __('isms.flash.scope_created'));
    }

    public function edit(IsmsScope $scope): View {
        Gate::authorize('update', $scope);

        return view('isms.scopes._form_dialog', ['scope' => $scope]);
    }

    public function update(Request $request, IsmsScope $scope): RedirectResponse {
        Gate::authorize('update', $scope);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->update($scope, $actor, $this->validateScope($request));

        return redirect()
            ->back()
            ->with('success', __('isms.flash.scope_updated'));
    }

    public function destroy(IsmsScope $scope): RedirectResponse {
        Gate::authorize('delete', $scope);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->delete($scope, $actor);

        return redirect()
            ->route('isms.scopes.index')
            ->with('success', __('isms.flash.scope_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateScope(Request $request): array {
        return $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
        ]);
    }
}
