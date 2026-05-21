<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin\Access;

use App\Enums\User\Permission as PermissionEnum;
use App\Enums\User\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Verwaltung der Rollen für die aktuell aktive Organisation. Globale
 * Rollen (team_id = NULL, z. B. der Plattform-Admin) sind read-only
 * sichtbar, dürfen aber nicht über diese UI angepasst werden — sie
 * werden ausschließlich vom Seeder verwaltet.
 */
class RoleController extends Controller {
    public function index(): View {
        Gate::authorize('manage-access');

        $organization = $this->currentOrganization();
        $teamForeign = config('permission.column_names.team_foreign_key', 'team_id');

        $orgRoles = Role::query()
            ->where($teamForeign, $organization->id)
            ->withCount('permissions')
            ->orderBy('name')
            ->get();

        $globalRoles = Role::query()
            ->whereNull($teamForeign)
            ->withCount('permissions')
            ->orderBy('name')
            ->get();

        return view('admin.access.roles.index', [
            'organization' => $organization,
            'orgRoles' => $orgRoles,
            'globalRoles' => $globalRoles,
            'systemRoleNames' => UserRole::values(),
        ]);
    }

    public function create(): View {
        Gate::authorize('manage-access');

        return view('admin.access.roles.form', [
            'role' => new Role,
            'assigned' => [],
            'permissions' => PermissionEnum::grouped(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('manage-access');

        $organization = $this->currentOrganization();
        $teamForeign = config('permission.column_names.team_foreign_key', 'team_id');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9._-]+$/'],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $slug = Str::lower($data['name']);

        // Innerhalb der Organisation eindeutig (Unique-Index deckt es ab,
        // wir liefern eine sprechende Validierung).
        $exists = Role::query()
            ->where($teamForeign, $organization->id)
            ->where('name', $slug)
            ->where('guard_name', 'web')
            ->exists();
        abort_if($exists, 422, __('access.error.role_exists'));

        $role = Role::create([
            'name' => $slug,
            'guard_name' => 'web',
            $teamForeign => $organization->id,
        ]);

        $role->syncPermissions($this->validatedPermissionNames($data['permissions'] ?? []));

        return redirect()->route('admin.access.roles.index')
            ->with('success', __('access.flash.role_created'));
    }

    public function edit(Role $role): View {
        Gate::authorize('manage-access');
        $this->ensureEditable($role);

        return view('admin.access.roles.form', [
            'role' => $role,
            'assigned' => $role->permissions()->pluck('name')->all(),
            'permissions' => PermissionEnum::grouped(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse {
        Gate::authorize('manage-access');
        $this->ensureEditable($role);

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $role->syncPermissions($this->validatedPermissionNames($data['permissions'] ?? []));

        return redirect()->route('admin.access.roles.index')
            ->with('success', __('access.flash.role_updated'));
    }

    public function destroy(Role $role): RedirectResponse {
        Gate::authorize('manage-access');
        $this->ensureEditable($role);

        if (in_array($role->name, UserRole::values(), true)) {
            return back()->with('error', __('access.error.role_system_protected'));
        }

        $role->delete();

        return redirect()->route('admin.access.roles.index')
            ->with('success', __('access.flash.role_deleted'));
    }

    private function currentOrganization(): Organization {
        abort_unless(app()->bound('currentOrganization'), 403);
        $org = app('currentOrganization');
        abort_unless($org instanceof Organization, 403);

        return $org;
    }

    private function ensureEditable(Role $role): void {
        $organization = $this->currentOrganization();
        $teamForeign = config('permission.column_names.team_foreign_key', 'team_id');

        // Globale Rollen dürfen nicht über die Org-UI verändert werden.
        abort_if($role->{$teamForeign} === null, 403, __('access.error.role_global_readonly'));
        abort_unless((int) $role->{$teamForeign} === $organization->id, 403);
    }

    /**
     * Filtert die übergebenen Permission-Namen auf solche, die im Enum
     * definiert sind — verhindert, dass über manipulierte Formulareingaben
     * beliebige Permission-Strings angelegt/zugewiesen werden.
     *
     * @param  list<string>  $names
     * @return list<int>  IDs der Permissions, die syncPermissions() erwartet
     */
    private function validatedPermissionNames(array $names): array {
        $allowed = PermissionEnum::values();
        $valid = array_values(array_intersect($names, $allowed));

        return Permission::query()
            ->whereIn('name', $valid)
            ->where('guard_name', 'web')
            ->pluck('id')
            ->all();
    }
}
