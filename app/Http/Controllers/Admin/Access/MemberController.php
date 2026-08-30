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

use App\Enums\User\UserRole;
use App\Http\Controllers\Concerns\{AuditsAccessChanges, ResolvesCurrentOrganization};
use App\Http\Controllers\Controller;
use App\Models\{User, UserGroup};
use App\Support\SortableQuery;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
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
    use AuditsAccessChanges;
    use ResolvesCurrentOrganization;

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
            $query->search($search);
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
        $this->ensureMayManagePlatformAdmin($member);

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
        $this->ensureMayManagePlatformAdmin($member);

        $data = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['integer'],
            'groups' => ['array'],
            // Sqids aus dem Formular (W3.3); Rollen bleiben numerisch, weil das
            // Spatie-Role-Modell aus dem Vendor-Paket kein HasSqid tragen kann.
            'groups.*' => ['string'],
        ]);

        $teamForeign = config('permission.column_names.team_foreign_key', 'team_id');

        // Nur Rollen, die zur Org bzw. global gehören, dürfen zugewiesen werden.
        $validRoles = Role::query()
            ->whereIn('id', $data['roles'] ?? [])
            ->where(function ($q) use ($member, $teamForeign): void {
                $q->whereNull($teamForeign)
                    ->orWhere($teamForeign, $member->organization_id);
            })
            ->get();

        // Eskalationsschutz: Die globale System-Rolle "admin" (plattformweiter
        // Zugriff, auch org-übergreifend) darf NUR ein echter Plattform-Admin
        // vergeben oder entziehen — nicht ein delegierter access.manage-Verwalter.
        $auth = Auth::user();
        $adminRole = Role::query()->where('name', UserRole::Admin->value)->whereNull($teamForeign)->first();
        if ($adminRole instanceof Role && ! ($auth instanceof User && $auth->isAdmin())) {
            $validRoles = $validRoles->reject(fn (Role $r): bool => $r->is($adminRole));
            $hadAdmin = \Illuminate\Support\Facades\DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
                ->where('role_id', $adminRole->id)
                ->where('model_id', $member->getKey())
                ->where('model_type', $member->getMorphClass())
                ->exists();
            if ($hadAdmin) {
                $validRoles->push($adminRole);
            }
        }

        // Bauturbo A17 (MVP-335): Rollen-Vergabe/-Entzug als Diff auditieren
        // (supportzugriff-grundsaetze.md §4.1); No-Op-Sync erzeugt keine Events.
        $this->syncRolesAudited($member, $validRoles);

        $requestedGroupIds = array_filter(array_map(
            static fn (string $v): ?int => \App\Support\Sqid::decodeOrNumeric(UserGroup::class, $v),
            $data['groups'] ?? [],
        ));
        $validGroupIds = UserGroup::query()
            ->whereIn('id', $requestedGroupIds)
            ->where('organization_id', $member->organization_id)
            ->pluck('id')
            ->all();

        // Bauturbo A17 (MVP-335): Gruppen-Mitgliedschaften vererben Rollen/
        // Permissions — Änderungen daher wie in UserGroupController::attach-/
        // detachMember mit den etablierten user_group.member_*-Events auditieren.
        $beforeGroupIds = $member->userGroups()->pluck('user_groups.id')->map(fn ($id): int => (int) $id)->all();
        $member->userGroups()->sync($validGroupIds);

        $addedGroups = UserGroup::query()->whereIn('id', array_diff($validGroupIds, $beforeGroupIds))->get();
        $removedGroups = UserGroup::query()->whereIn('id', array_diff($beforeGroupIds, $validGroupIds))->get();
        foreach ($addedGroups as $group) {
            $group->audit('user_group.member_added', ['member_id' => $member->id, 'member_name' => $member->name]);
        }
        foreach ($removedGroups as $group) {
            $group->audit('user_group.member_removed', ['member_id' => $member->id, 'member_name' => $member->name]);
        }

        return redirect()->route('admin.access.members.index')
            ->with('success', __('access.flash.member_updated'));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Role>
     */
    private function availableRoles(): \Illuminate\Database\Eloquent\Collection {
        $org = $this->currentOrganization();
        $teamForeign = config('permission.column_names.team_foreign_key', 'team_id');

        $roles = Role::query()
            ->where(function ($q) use ($teamForeign, $org): void {
                $q->whereNull($teamForeign)
                    ->orWhere($teamForeign, $org->id);
            })
            ->orderBy('name')
            ->get();

        // Die globale System-Rolle "admin" nur echten Plattform-Admins zur
        // Auswahl anbieten (Server-Guard in update() ist die eigentliche Grenze).
        $auth = Auth::user();
        if (! ($auth instanceof User && $auth->isAdmin())) {
            $roles = $roles->reject(
                fn (Role $r): bool => $r->name === UserRole::Admin->value && $r->getAttribute($teamForeign) === null
            )->values();
        }

        return $roles;
    }

    /**
     * Ein Plattform-Betreiber ist kein gewöhnliches Mitglied.
     *
     * In einer On-Prem-Installation sitzt der Betreiber meist in derselben
     * (einzigen) Organisation. Ohne diese Schranke konnte ein org-lokaler
     * Admin ihm ein neues Passwort setzen, seine E-Mail ändern, ihm die Rolle
     * entziehen oder das Konto löschen — und damit Org-Admin gegen
     * Cross-Tenant-Betreiber tauschen bzw. den Betreiber aussperren
     * (Sicherheitsscan 2026-08-23, S-14). Nur ein Betreiber verwaltet einen
     * Betreiber.
     */
    private function ensureMayManagePlatformAdmin(User $member): void {
        if (! $member->isGlobalAdmin()) {
            return;
        }

        $auth = Auth::user();

        abort_unless($auth instanceof User && $auth->isGlobalAdmin(), 403);
    }

    private function ensureSameOrg(User $member): void {
        /** @var User $auth */
        $auth = Auth::user();
        abort_unless($member->organization_id === $auth->organization_id, 403);
    }
}
