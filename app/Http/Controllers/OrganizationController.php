<?php

/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Support\SortableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Organization::class);

        $query = Organization::query()->withoutGlobalScopes()->withCount('users');

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'name' => 'name',
            'plan' => 'plan',
            'locale' => 'locale',
            'timezone' => 'timezone',
            'is_active' => 'is_active',
            'users' => 'users_count',
        ], 'name', 'asc');

        $organizations = $query->paginate(25)->withQueryString();

        return view('admin.organizations.index', compact('organizations', 'sort', 'dir'));
    }

    public function create(): View
    {
        Gate::authorize('create', Organization::class);

        return view('admin.organizations.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Organization::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['required', 'in:'.implode(',', Organization::$plans)],
            'locale' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'string', 'max:64'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        Organization::create($data);

        return redirect()->route('admin.organizations.index')
            ->with('success', __('Organisation wurde erstellt.'));
    }

    public function edit(Organization $organization): View
    {
        Gate::authorize('update', $organization);

        return view('admin.organizations.edit', compact('organization'));
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('update', $organization);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['required', 'in:'.implode(',', Organization::$plans)],
            'locale' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'string', 'max:64'],
            'is_active' => ['boolean'],
            'compliance' => ['sometimes', 'array'],
            'compliance.mode' => ['sometimes', 'in:'.implode(',', Organization::$complianceModes)],
            'compliance.max_hours_day' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'compliance.min_rest_hours' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'compliance.max_hours_week' => ['sometimes', 'integer', 'min:1', 'max:168'],
            'compliance.max_consecutive_days' => ['sometimes', 'integer', 'min:1', 'max:14'],
            'compliance.rules' => ['sometimes', 'array'],
            'compliance.rules.*' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        // Compliance ggf. in settings einbetten.
        if (array_key_exists('compliance', $data)) {
            /** @var array<string,mixed> $settings */
            $settings = (array) ($organization->settings ?? []);
            /** @var array<string,mixed> $existingCompliance */
            $existingCompliance = isset($settings['compliance']) && is_array($settings['compliance'])
                ? $settings['compliance']
                : [];
            $settings['compliance'] = array_replace(
                $existingCompliance,
                $data['compliance'],
            );
            // Boolean-Konvertierung für rules
            if (isset($settings['compliance']['rules']) && is_array($settings['compliance']['rules'])) {
                $settings['compliance']['rules'] = array_map(
                    static fn ($v) => filter_var($v, FILTER_VALIDATE_BOOL),
                    $settings['compliance']['rules'],
                );
            }
            $data['settings'] = $settings;
            unset($data['compliance']);
        }

        $organization->update($data);

        return redirect()->route('admin.organizations.index')
            ->with('success', __('Organisation wurde aktualisiert.'));
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        Gate::authorize('delete', $organization);

        $organization->delete();

        return redirect()->route('admin.organizations.index')
            ->with('success', __('Organisation wurde gelöscht.'));
    }
}
