<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MemberController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin\Access;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserGroup;
use App\Support\SortableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Zentrale Übersicht über alle Mitglieder der aktuellen Organisation
 * inkl. ihrer effektiven Rollen und Gruppen-Mitgliedschaften, mit der
 * Möglichkeit, Rollen sowie Gruppen pro User direkt zuzuweisen.
 *
 * Dieser Controller ergänzt {@see \App\Http\Controllers\OrgMemberController}
 * um die feingranulare Verwaltung — der OrgMember-Controller bleibt
 * verantwortlich für das Anlegen und Löschen von Mitgliedern selbst.
 */
class MemberController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('manage-access');

        /** @var User $auth */
        $auth = Auth::user();

        // TENANT-BYPASS: User-Sonderfall (kein Trait). Mandantengrenze wird
        // explizit über where('organization_id', $auth->organization_id) gesetzt.
        $query = User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $auth->organization_id)
            ->with(['roles', 'userGroups']);

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'name' => 'name',
            'email' => 'email',
            'created_at' => 'created_at',
        ], 'name', 'asc');

        $members = $query->paginate(25)->withQueryString();

        return view('admin.access.members.index', compact('members', 'sort', 'dir', 'search'));
    }

    public function edit(User $member): View {
        Gate::authorize('manage-access');
        $this->ensureSameOrg($member);

        $member->load(['roles', 'userGroups']);

        return view('admin.access.members._form_dialog', [
            'member' => $member,
            'roles' => $this->availableRoles(),
            'groups' => UserGroup::query()->orderBy('name')->get(),
            'assignedRoles' => $member->roles->pluck('id')->all(),
            'assignedGroups' => $member->userGroups->pluck('id')->all(),
            'effectivePermissions' => $member->effectivePermissionNames()->sort()->values(),
        ]);
    }

    public function update(Request $request, User $member): RedirectResponse {
        Gate::authorize('manage-access');
        $this->ensureSameOrg($member);

        $data = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['integer'],
            'groups' => ['array'],
            'groups.*' => ['integer'],
        ]);

        // Nur Rollen, die zur Org bzw. global gehören, dürfen zugewiesen werden.
        $validRoles = Role::query()
            ->whereIn('id', $data['roles'] ?? [])
            ->where(function ($q) use ($member): void {
                $teamForeign = config('permission.column_names.team_foreign_key', 'team_id');
                $q->whereNull($teamForeign)
                    ->orWhere($teamForeign, $member->organization_id);
            })
            ->get();

        $member->syncRoles($validRoles);

        $validGroupIds = UserGroup::query()
            ->whereIn('id', $data['groups'] ?? [])
            ->where('organization_id', $member->organization_id)
            ->pluck('id')
            ->all();

        $member->userGroups()->sync($validGroupIds);

        return redirect()->route('admin.access.members.index')
            ->with('success', __('access.flash.member_updated'));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Role>
     */
    private function availableRoles(): \Illuminate\Database\Eloquent\Collection {
        $org = $this->currentOrganization();
        $teamForeign = config('permission.column_names.team_foreign_key', 'team_id');

        return Role::query()
            ->where(function ($q) use ($teamForeign, $org): void {
                $q->whereNull($teamForeign)
                    ->orWhere($teamForeign, $org->id);
            })
            ->orderBy('name')
            ->get();
    }

    private function currentOrganization(): Organization {
        abort_unless(app()->bound('currentOrganization'), 403);
        $org = app('currentOrganization');
        abort_unless($org instanceof Organization, 403);

        return $org;
    }

    private function ensureSameOrg(User $member): void {
        /** @var User $auth */
        $auth = Auth::user();
        abort_unless($member->organization_id === $auth->organization_id, 403);
    }
}
