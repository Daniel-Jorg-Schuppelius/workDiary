<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RolesSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\ProcedureDocumentation\Sections;

use App\Enums\User\{Permission, UserRole};
use App\Models\{Organization, User};
use App\Services\Finance\ProcedureDocumentation\{FormatsSectionValues, ProcedureSection, SectionContext};
use Spatie\Permission\Models\Role;

/**
 * Rollen-/Rechtematrix der Organisation: Spatie-Rollen (team_id = Org) mit
 * Nutzerzahl und Rechteumfang sowie die vollständige Matrix Recht × Rolle.
 * Plattform-Admins (globale Rolle) werden nur gezählt.
 */
final class RolesSection implements ProcedureSection {
    use FormatsSectionValues;

    public function key(): string {
        return 'roles';
    }

    public function title(): string {
        return (string) __('procedure-documentation.section.roles');
    }

    public function build(Organization $organization, SectionContext $context): array {
        $teamKey = (string) config('permission.column_names.team_foreign_key', 'team_id');
        /** @var \Illuminate\Database\Eloquent\Collection<int, Role> $roles */
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->where($teamKey, $organization->id)
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get();

        $roleRows = [];
        $labels = [];
        /** @var array<string, array<string, bool>> $matrix Recht → Rollenname → gesetzt */
        $matrix = [];
        foreach ($roles as $role) {
            $name = (string) $role->getAttribute('name');
            $label = UserRole::tryFrom($name)?->label() ?? $name;
            $labels[$name] = $label;
            $permissions = $role->permissions->pluck('name')->map(static fn ($p): string => (string) $p)->all();
            $roleRows[] = [$label, $name, (string) $role->users()->count(), (string) count($permissions)];
            foreach ($permissions as $permission) {
                $matrix[$permission][$name] = true;
            }
        }
        ksort($matrix);

        $matrixRows = [];
        foreach ($matrix as $permission => $assigned) {
            $row = [Permission::tryFrom($permission)?->label() ?? $permission, $permission];
            foreach (array_keys($labels) as $roleName) {
                $row[] = isset($assigned[$roleName]) ? 'x' : '';
            }
            $matrixRows[] = $row;
        }

        $platformAdmins = User::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('is_platform_admin', true)
            ->count();

        return [
            'tables' => [
                'roles' => [
                    'title' => (string) __('procedure-documentation.roles.table'),
                    'columns' => [(string) __('procedure-documentation.roles.col.role'), (string) __('procedure-documentation.roles.col.key'), (string) __('procedure-documentation.roles.col.users'), (string) __('procedure-documentation.roles.col.permissions')],
                    'rows' => $roleRows,
                ],
                'matrix' => [
                    'title' => (string) __('procedure-documentation.roles.matrix'),
                    'columns' => array_merge([(string) __('procedure-documentation.roles.col.permission'), (string) __('procedure-documentation.roles.col.key')], array_values($labels)),
                    'rows' => $matrixRows,
                ],
            ],
            'notes' => [(string) __('procedure-documentation.roles.platform_admins', ['count' => $platformAdmins])],
        ];
    }
}
