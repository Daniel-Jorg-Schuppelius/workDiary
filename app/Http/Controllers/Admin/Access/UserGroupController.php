<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserGroupController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin\Access;

use App\Enums\User\{Permission as PermissionEnum, UserRole};
use App\Http\Controllers\Concerns\{AuditsAccessChanges, ParsesIndexQuery, ResolvesCurrentOrganization};
use App\Http\Controllers\Controller;
use App\Models\{User, UserGroup};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Auth, DB, Gate};
use Illuminate\View\View;
use Spatie\Permission\Models\{Permission, Role};

/**
 * Verwaltung der organisationsspezifischen Benutzergruppen. Eine Gruppe
 * bündelt Mitglieder und vererbt diesen Rollen sowie direkte Permissions.
 */
class UserGroupController extends Controller {
    use AuditsAccessChanges;
    use ParsesIndexQuery;
    use ResolvesCurrentOrganization;

    private const ALLOWED_SORTS = ['name', 'slug', 'members_count', 'description'];

    public function index(Request $request): View {
        Gate::authorize('viewAny', UserGroup::class);

        ['search' => $search, 'sort' => $sort, 'dir' => $dir]
            = $this->parseIndexQuery($request, self::ALLOWED_SORTS, 'name');

        $groups = UserGroup::query()
            ->withCount('members')
            ->when($search !== '', fn($q) => $q->search($search))
            ->orderBy($sort, $dir)
            ->paginate(25)
            ->withQueryString();

        return view('admin.access.groups.index', compact('groups', 'search', 'sort', 'dir'));
    }

    public function create(): View {
        Gate::authorize('create', UserGroup::class);

        return view('admin.access.groups._form_dialog', [
            'group' => new UserGroup,
            'assignedRoles' => [],
            'assignedPermissions' => [],
            'roles' => $this->availableRoles(),
            'permissions' => PermissionEnum::grouped(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', UserGroup::class);

        /** @var User $auth */
        $auth = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:16'],
            'roles' => ['array'],
            'roles.*' => ['integer'],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $group = UserGroup::create([
            'organization_id' => $auth->organization_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? null,
            'is_system' => false,
        ]);

        $this->syncRolesAndPermissions($group, $data['roles'] ?? [], $data['permissions'] ?? []);

        return redirect()->route('admin.access.groups.show', $group)
            ->with('success', __('access.flash.group_created'));
    }

    public function show(UserGroup $group): View {
        Gate::authorize('view', $group);

        $group->load(['roles', 'permissions', 'members' => function ($q): void {
            // TENANT-BYPASS: User-Sonderfall (kein Trait); Pivot-Members implizit über group->organization_id gefiltert.
            $q->withoutGlobalScopes()->orderBy('name');
        }]);

        // Hinzufügbare Mitglieder: alle User der Org, die nicht bereits in der Gruppe sind.
        $memberIds = $group->members->pluck('id')->all();
        // TENANT-BYPASS: User-Sonderfall; Org-Filter explizit über group->organization_id (Group selbst tenant-scoped).
        $addableUsers = User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $group->organization_id)
            ->whereNotIn('id', $memberIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.access.groups.show', compact('group', 'addableUsers'));
    }

    public function edit(UserGroup $group): View {
        Gate::authorize('update', $group);

        $group->load(['roles', 'permissions']);

        return view('admin.access.groups._form_dialog', [
            'group' => $group,
            'assignedRoles' => $group->roles->pluck('id')->all(),
            'assignedPermissions' => $group->permissions->pluck('name')->all(),
            'roles' => $this->availableRoles(),
            'permissions' => PermissionEnum::grouped(),
        ]);
    }

    public function update(Request $request, UserGroup $group): RedirectResponse {
        Gate::authorize('update', $group);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:16'],
            'roles' => ['array'],
            'roles.*' => ['integer'],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $group->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? null,
        ]);

        $this->syncRolesAndPermissions($group, $data['roles'] ?? [], $data['permissions'] ?? []);

        return redirect()->route('admin.access.groups.show', $group)
            ->with('success', __('access.flash.group_updated'));
    }

    public function destroy(UserGroup $group): RedirectResponse {
        Gate::authorize('delete', $group);

        $group->delete();

        return redirect()->route('admin.access.groups.index')
            ->with('success', __('access.flash.group_deleted'));
    }

    public function attachMemberForm(UserGroup $group): View {
        Gate::authorize('update', $group);

        $memberIds = $group->members()->pluck('users.id')->all();
        // TENANT-BYPASS: User-Sonderfall; Org-Filter explizit über group->organization_id.
        $addableUsers = User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $group->organization_id)
            ->whereNotIn('id', $memberIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.access.groups._attach_member_dialog', compact('group', 'addableUsers'));
    }

    public function attachMember(Request $request, UserGroup $group): RedirectResponse {
        Gate::authorize('update', $group);

        $data = $request->validate([
            'user_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
        ]);

        /** @var User|null $user */
        $user = User::query()->find($data['user_id']);
        abort_unless($user instanceof User && $user->organization_id === $group->organization_id, 422);

        $alreadyMember = $group->members()->whereKey($user->id)->exists();

        $group->members()->syncWithoutDetaching([
            $user->id => ['joined_at' => Carbon::now()],
        ]);

        if (! $alreadyMember) {
            $group->audit('user_group.member_added', [
                'member_id' => $user->id,
                'member_name' => $user->name,
            ]);
        }

        return back()->with('success', __('access.flash.member_added'));
    }

    public function detachMember(UserGroup $group, User $user): RedirectResponse {
        Gate::authorize('update', $group);
        abort_unless($user->organization_id === $group->organization_id, 403);

        $detached = $group->members()->detach($user->id);

        if ($detached > 0) {
            $group->audit('user_group.member_removed', [
                'member_id' => $user->id,
                'member_name' => $user->name,
            ]);
        }

        return back()->with('success', __('access.flash.member_removed'));
    }

    /**
     * Rollen, die einer Gruppe der aktuellen Organisation zugewiesen werden
     * dürfen: alle Rollen mit team_id = NULL (global) oder team_id = aktuelle Org.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Role>
     */
    private function availableRoles(): \Illuminate\Database\Eloquent\Collection {
        $organization = $this->currentOrganization();
        $teamForeign = config('permission.column_names.team_foreign_key', 'team_id');

        $roles = Role::query()
            ->where(function ($q) use ($teamForeign, $organization): void {
                $q->whereNull($teamForeign)
                    ->orWhere($teamForeign, $organization->id);
            })
            ->orderBy('name')
            ->get();

        // Eskalationsschutz: Die globale System-Rolle "admin" (plattformweit, auch
        // org-übergreifend) darf ein delegierter access.manage-Verwalter nicht über
        // eine Gruppe vergeben — nur ein echter Plattform-Admin sieht sie hier.
        $auth = Auth::user();
        if (! ($auth instanceof User && $auth->isAdmin())) {
            $roles = $roles->reject(
                fn (Role $r): bool => $r->name === UserRole::Admin->value && $r->getAttribute($teamForeign) === null
            )->values();
        }

        return $roles;
    }

    /**
     * @param  list<int>     $roleIds
     * @param  list<string>  $permissionNames
     */
    private function syncRolesAndPermissions(UserGroup $group, array $roleIds, array $permissionNames): void {
        $organization = $this->currentOrganization();
        $teamForeign = config('permission.column_names.team_foreign_key', 'team_id');

        $validRoles = Role::query()
            ->whereIn('id', $roleIds)
            ->where(function ($q) use ($teamForeign, $organization): void {
                $q->whereNull($teamForeign)
                    ->orWhere($teamForeign, $organization->id);
            })
            ->get();

        // Eskalationsschutz (analog MemberController): die globale "admin"-Rolle darf
        // nur ein echter Plattform-Admin einer Gruppe zuweisen/entziehen. Hatte die
        // Gruppe sie bereits, bleibt sie erhalten, damit ein Verwalter sie nicht
        // versehentlich verliert.
        $auth = Auth::user();
        $adminRole = Role::query()->where('name', UserRole::Admin->value)->whereNull($teamForeign)->first();
        if ($adminRole instanceof Role && ! ($auth instanceof User && $auth->isAdmin())) {
            $validRoles = $validRoles->reject(fn (Role $r): bool => $r->is($adminRole));
            $hadAdmin = DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
                ->where('role_id', $adminRole->id)
                ->where('model_id', $group->getKey())
                ->where('model_type', $group->getMorphClass())
                ->exists();
            if ($hadAdmin) {
                $validRoles->push($adminRole);
            }
        }

        // Bauturbo A17 (MVP-335): Rollen der Gruppe wirken auf alle Mitglieder —
        // Vergabe/Entzug als user.role.assigned/.revoked-Diff auditieren
        // (supportzugriff-grundsaetze.md §4.1); No-Op-Sync erzeugt keine Events.
        $this->syncRolesAudited($group, $validRoles);

        $validPermissions = Permission::query()
            ->whereIn('name', array_intersect($permissionNames, PermissionEnum::values()))
            ->where('guard_name', 'web')
            ->get();

        // Direkt-Permissions (Ausnahmefall) analog als granted/revoked-Diff.
        $this->syncPermissionsAudited($group, $validPermissions);
    }
}
