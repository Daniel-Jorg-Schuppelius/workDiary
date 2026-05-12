<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrganizationController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', Organization::class);

        $organizations = Organization::withoutGlobalScopes()
            ->withCount('users')
            ->orderBy('name')
            ->paginate(25);

        return view('admin.organizations.index', compact('organizations'));
    }

    public function create(): View {
        Gate::authorize('create', Organization::class);

        return view('admin.organizations.create');
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Organization::class);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'plan'     => ['required', 'in:' . implode(',', Organization::$plans)],
            'locale'   => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'string', 'max:64'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        Organization::create($data);

        return redirect()->route('admin.organizations.index')
            ->with('success', __('Organisation wurde erstellt.'));
    }

    public function edit(Organization $organization): View {
        Gate::authorize('update', $organization);

        return view('admin.organizations.edit', compact('organization'));
    }

    public function update(Request $request, Organization $organization): RedirectResponse {
        Gate::authorize('update', $organization);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'plan'     => ['required', 'in:' . implode(',', Organization::$plans)],
            'locale'   => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'string', 'max:64'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $organization->update($data);

        return redirect()->route('admin.organizations.index')
            ->with('success', __('Organisation wurde aktualisiert.'));
    }

    public function destroy(Organization $organization): RedirectResponse {
        Gate::authorize('delete', $organization);

        $organization->delete();

        return redirect()->route('admin.organizations.index')
            ->with('success', __('Organisation wurde gelöscht.'));
    }
}
