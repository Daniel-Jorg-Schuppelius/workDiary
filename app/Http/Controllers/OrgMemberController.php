<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgMemberController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\UserRole;
use App\Http\Controllers\Concerns\ManagesUserContactDetails;
use App\Models\User;
use App\Support\SortableQuery;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Hash};
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Verwaltet Mitglieder der eigenen Organisation.
 * Nur Org-Admins dürfen zugreifen (Gate 'manage-members' via OrganizationPolicy).
 */
class OrgMemberController extends Controller {
    use ManagesUserContactDetails;
    public function index(Request $request): View {
        Gate::authorize('manage-members');

        /** @var User $auth */
        $auth = Auth::user();

        // TENANT-BYPASS: User-Model nutzt keinen BelongsToOrganization-Trait
        // (Authenticatable-Sonderfall, siehe docs/security/tenant-audit-2026.md).
        // Mandantengrenze wird hier durch where('organization_id', $auth->organization_id)
        // explizit hergestellt.
        $query = User::withoutGlobalScopes()
            ->where('organization_id', $auth->organization_id)
            ->with('roles');

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'name' => 'name',
            'email' => 'email',
            'created_at' => 'created_at',
        ], 'name', 'asc');

        $members = $query->paginate(25)->withQueryString();

        $roles = [UserRole::Admin->value, UserRole::User->value, UserRole::Buchhaltung->value];

        return view('org.members.index', compact('members', 'roles', 'sort', 'dir'));
    }

    public function create(): View {
        Gate::authorize('manage-members');

        $roles = [UserRole::Admin->value, UserRole::User->value, UserRole::Buchhaltung->value];

        return view('org.members._form_dialog', compact('roles') + ['member' => null, 'isEdit' => false]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('manage-members');

        /** @var User $auth */
        $auth = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:' . implode(',', [UserRole::Admin->value, UserRole::User->value, UserRole::Buchhaltung->value])],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ] + $this->contactDetailRules());

        $user = User::create([
            'organization_id' => $auth->organization_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'must_change_password' => true,
        ]);
        $this->fillUserContactFields($user, $data);
        $user->save();

        $this->syncUserAddress($user, (array) ($data['address'] ?? []));
        $this->syncUserBankAccount($user, (array) ($data['bank'] ?? []));

        $role = Role::findOrCreate($data['role'], 'web');
        $user->assignRole($role);

        return redirect()->route('org.members.index')
            ->with('success', __('Mitglied wurde angelegt.'));
    }

    public function edit(User $member): View {
        Gate::authorize('manage-members');
        $this->ensureSameOrg($member);
        $member->loadMissing(['addresses', 'bankAccounts']);

        $roles = [UserRole::Admin->value, UserRole::User->value, UserRole::Buchhaltung->value];

        return view('org.members._form_dialog', compact('member', 'roles') + ['isEdit' => true]);
    }

    public function update(Request $request, User $member): RedirectResponse {
        Gate::authorize('manage-members');
        $this->ensureSameOrg($member);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $member->id],
            'role' => ['required', 'in:' . implode(',', [UserRole::Admin->value, UserRole::User->value, UserRole::Buchhaltung->value])],
        ] + $this->contactDetailRules());

        $member->fill(['name' => $data['name'], 'email' => $data['email']]);
        $this->fillUserContactFields($member, $data);
        $member->save();

        $this->syncUserAddress($member, (array) ($data['address'] ?? []));
        $this->syncUserBankAccount($member, (array) ($data['bank'] ?? []));

        $role = Role::findOrCreate($data['role'], 'web');
        $member->syncRoles([$role]);

        return redirect()->route('org.members.index')
            ->with('success', __('Mitglied wurde aktualisiert.'));
    }

    public function destroy(User $member): RedirectResponse {
        Gate::authorize('manage-members');
        $this->ensureSameOrg($member);

        /** @var User $auth */
        $auth = Auth::user();

        if ($member->id === $auth->id) {
            return back()->with('error', __('Sie können sich nicht selbst entfernen.'));
        }

        $member->delete();

        return redirect()->route('org.members.index')
            ->with('success', __('Mitglied wurde entfernt.'));
    }

    private function ensureSameOrg(User $member): void {
        /** @var User $auth */
        $auth = Auth::user();

        abort_unless($member->organization_id === $auth->organization_id, 403);
    }
}
