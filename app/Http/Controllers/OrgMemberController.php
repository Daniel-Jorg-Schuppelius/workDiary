<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Verwaltet Mitglieder der eigenen Organisation.
 * Nur Org-Admins dürfen zugreifen (Gate 'manage-members' via OrganizationPolicy).
 */
class OrgMemberController extends Controller {
    public function index(): View {
        Gate::authorize('manage-members');

        /** @var User $auth */
        $auth = Auth::user();

        $members = User::withoutGlobalScopes()
            ->where('organization_id', $auth->organization_id)
            ->with('roles')
            ->orderBy('name')
            ->paginate(25);

        $roles = [User::ROLE_ADMIN, User::ROLE_USER, User::ROLE_BUCHHALTUNG];

        return view('org.members.index', compact('members', 'roles'));
    }

    public function create(): View {
        Gate::authorize('manage-members');

        $roles = [User::ROLE_ADMIN, User::ROLE_USER, User::ROLE_BUCHHALTUNG];

        return view('org.members.create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('manage-members');

        /** @var User $auth */
        $auth = Auth::user();

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'role'     => ['required', 'in:' . implode(',', [User::ROLE_ADMIN, User::ROLE_USER, User::ROLE_BUCHHALTUNG])],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'organization_id'      => $auth->organization_id,
            'name'                 => $data['name'],
            'email'                => $data['email'],
            'password'             => Hash::make($data['password']),
            'must_change_password' => true,
        ]);

        $role = Role::findOrCreate($data['role'], 'web');
        $user->assignRole($role);

        return redirect()->route('org.members.index')
            ->with('success', __('Mitglied wurde angelegt.'));
    }

    public function edit(User $member): View {
        Gate::authorize('manage-members');
        $this->ensureSameOrg($member);

        $roles = [User::ROLE_ADMIN, User::ROLE_USER, User::ROLE_BUCHHALTUNG];

        return view('org.members.edit', compact('member', 'roles'));
    }

    public function update(Request $request, User $member): RedirectResponse {
        Gate::authorize('manage-members');
        $this->ensureSameOrg($member);

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $member->id],
            'role'  => ['required', 'in:' . implode(',', [User::ROLE_ADMIN, User::ROLE_USER, User::ROLE_BUCHHALTUNG])],
        ]);

        $member->update(['name' => $data['name'], 'email' => $data['email']]);

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
