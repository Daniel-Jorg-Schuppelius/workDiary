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

use App\Enums\User\{Permission, UserRole};
use App\Http\Controllers\Concerns\ManagesUserContactDetails;
use App\Models\User;
use App\Support\SortableQuery;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Hash};
use Illuminate\Validation\Rule;
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
        /** @var User $auth */
        $auth = Auth::user();
        $this->authorizeMemberDirectoryAccess($auth);

        // TENANT-BYPASS: User-Model nutzt keinen BelongsToOrganization-Trait
        // (Authenticatable-Sonderfall, siehe docs/security/tenant-audit-2026.md).
        // Mandantengrenze wird hier durch where('organization_id', $auth->organization_id)
        // explizit hergestellt.
        $query = User::withoutGlobalScopes()
            ->where('organization_id', $auth->organization_id)
            ->with('roles');

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'name'             => 'name',
            'personnel_number' => 'personnel_number',
            'email'            => 'email',
            'created_at'       => 'created_at',
        ], 'name', 'asc');

        $members = $query->paginate(25)->withQueryString();

        $roles = [UserRole::Admin->value, UserRole::User->value, UserRole::Buchhaltung->value];
        $canManageMembers = $this->canManageMembers($auth);
        $canManagePayroll = $this->canManagePayroll($auth);

        return view('org.members.index', compact('members', 'roles', 'sort', 'dir', 'canManageMembers', 'canManagePayroll'));
    }

    public function create(): View {
        Gate::authorize('manage-members');

        /** @var User $auth */
        $auth = Auth::user();
        $roles = [UserRole::Admin->value, UserRole::User->value, UserRole::Buchhaltung->value];
        $canManageMembers = $this->canManageMembers($auth);
        $canManagePayroll = $this->canManagePayroll($auth);

        return view('org.members._form_dialog', compact('roles', 'canManageMembers', 'canManagePayroll') + ['member' => null, 'isEdit' => false]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('manage-members');

        /** @var User $auth */
        $auth = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'personnel_number' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('users', 'personnel_number')
                    ->where('organization_id', $auth->organization_id),
            ],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:' . implode(',', [UserRole::Admin->value, UserRole::User->value, UserRole::Buchhaltung->value])],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ] + $this->payrollDetailRules($auth) + $this->contactDetailRules());

        $user = User::create([
            'organization_id' => $auth->organization_id,
            'name' => $data['name'],
            'personnel_number' => $this->blankToNull($data['personnel_number'] ?? null),
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'must_change_password' => true,
        ]);
        $this->fillUserPayrollFields($user, $data, $auth);
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
        /** @var User $auth */
        $auth = Auth::user();
        $this->authorizeMemberDirectoryAccess($auth);
        $this->ensureSameOrg($member);
        $member->loadMissing(['addresses', 'bankAccounts']);

        $roles = [UserRole::Admin->value, UserRole::User->value, UserRole::Buchhaltung->value];
        $canManageMembers = $this->canManageMembers($auth);
        $canManagePayroll = $this->canManagePayroll($auth);

        return view('org.members._form_dialog', compact('member', 'roles', 'canManageMembers', 'canManagePayroll') + ['isEdit' => true]);
    }

    public function update(Request $request, User $member): RedirectResponse {
        $this->ensureSameOrg($member);

        /** @var User $auth */
        $auth = Auth::user();
        $this->authorizeMemberDirectoryAccess($auth);

        // Personalverwaltung/Geschäftsführung (kein Admin): dürfen ausschließlich
        // den Personal-/Payroll-Block pflegen — keine Identität, Rolle, Passwort.
        if (! $this->canManageMembers($auth)) {
            abort_unless($this->canManagePayroll($auth), 403);

            $data = $request->validate($this->payrollDetailRules($auth));
            $this->fillUserPayrollFields($member, $data, $auth);
            $member->save();

            return redirect()->route('org.members.index')
                ->with('success', __('Personaldaten wurden aktualisiert.'));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'personnel_number' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('users', 'personnel_number')
                    ->where('organization_id', $auth->organization_id)
                    ->ignore($member->id),
            ],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $member->id],
            'role' => ['required', 'in:' . implode(',', [UserRole::Admin->value, UserRole::User->value, UserRole::Buchhaltung->value])],
        ] + $this->payrollDetailRules($auth) + $this->contactDetailRules());

        $member->fill([
            'name' => $data['name'],
            'personnel_number' => $this->blankToNull($data['personnel_number'] ?? null),
            'email' => $data['email'],
        ]);
        $this->fillUserPayrollFields($member, $data, $auth);
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

    private function authorizeMemberDirectoryAccess(User $user): void {
        abort_unless($this->canManageMembers($user) || $this->canManagePayroll($user), 403);
    }

    /** Voller Mitglieder-Stamm (Identität, Rolle, Passwort, Anlegen/Löschen) — nur Admin. */
    private function canManageMembers(User $user): bool {
        return $user->isAdmin() && $user->organization_id !== null;
    }

    /**
     * Personal-/Lohndaten pflegen — Personalverwaltung + Geschäftsführung
     * (Admin via syncPermissions/Bypass). Steuert auch den Stundenlohn.
     */
    private function canManagePayroll(User $user): bool {
        return $user->organization_id !== null && $user->can(Permission::UserPayrollManage->value);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function payrollDetailRules(User $user): array {
        $rules = [
            'tax_identification_number' => ['nullable', 'string', 'max:32'],
            'social_security_number' => ['nullable', 'string', 'max:64'],
            'date_of_birth' => ['nullable', 'date'],
            'health_insurance' => ['nullable', 'string', 'max:128'],
            'tax_class' => ['nullable', 'string', 'max:16'],
            'child_allowances' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'church_tax' => ['nullable', 'boolean'],
            'employment_start_date' => ['nullable', 'date'],
            'employment_end_date' => ['nullable', 'date', 'after_or_equal:employment_start_date'],
            'employment_type' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\User\EmploymentType::class)],
        ];

        if ($this->canManagePayroll($user)) {
            $rules['payroll_hourly_wage'] = ['nullable', 'numeric', 'min:0', 'max:99999999.99'];

            // Vergütungsmodell (auch für externe Mitarbeiter). Je nach Modell
            // sind Pauschale (Betrag + Intervall) bzw. Stundensatz erforderlich.
            $rules['compensation_model'] = ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\User\CompensationModel::class)];
            $rules['flat_amount'] = ['nullable', 'required_if:compensation_model,pauschal', 'numeric', 'min:0', 'max:99999999.99'];
            $rules['flat_interval'] = ['nullable', 'required_if:compensation_model,pauschal', \Illuminate\Validation\Rule::enum(\App\Enums\User\FlatInterval::class)];
            $rules['compensation_rate'] = ['nullable', 'required_if:compensation_model,nach_zeitaufwand', 'numeric', 'min:0', 'max:99999999.99'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fillUserPayrollFields(User $member, array $data, User $auth): void {
        $payload = [
            'tax_identification_number' => $this->blankToNull($data['tax_identification_number'] ?? null),
            'social_security_number' => $this->blankToNull($data['social_security_number'] ?? null),
            'date_of_birth' => $this->blankToNull($data['date_of_birth'] ?? null),
            'health_insurance' => $this->blankToNull($data['health_insurance'] ?? null),
            'tax_class' => $this->blankToNull($data['tax_class'] ?? null),
            'child_allowances' => $data['child_allowances'] ?? null,
            'church_tax' => (bool) ($data['church_tax'] ?? false),
            'employment_start_date' => $this->blankToNull($data['employment_start_date'] ?? null),
            'employment_end_date' => $this->blankToNull($data['employment_end_date'] ?? null),
            'employment_type' => $this->blankToNull($data['employment_type'] ?? null),
        ];

        if ($this->canManagePayroll($auth)) {
            $payload['payroll_hourly_wage'] = $data['payroll_hourly_wage'] ?? null;

            $model = $this->blankToNull($data['compensation_model'] ?? null);
            $payload['compensation_model'] = $model;
            // Nur die zum Modell passenden Felder behalten, andere auf null setzen.
            $payload['flat_amount'] = $model === \App\Enums\User\CompensationModel::Pauschal->value ? ($data['flat_amount'] ?? null) : null;
            $payload['flat_interval'] = $model === \App\Enums\User\CompensationModel::Pauschal->value ? $this->blankToNull($data['flat_interval'] ?? null) : null;
            $payload['compensation_rate'] = $model === \App\Enums\User\CompensationModel::NachZeitaufwand->value ? ($data['compensation_rate'] ?? null) : null;
        }

        $member->fill($payload);
    }
}
