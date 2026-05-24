<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccessHubController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin\Access;

use App\Http\Controllers\Controller;
use App\Models\{Organization, User, UserGroup};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Spatie\Permission\Models\{Permission, Role};

/**
 * Übersichtsseite des Rechte-Bereichs: zeigt Kennzahlen der aktuellen
 * Organisation (Anzahl Rollen, Gruppen, Mitglieder, verfügbare
 * Permissions) und verlinkt auf die Detail-Verwaltungen.
 */
class AccessHubController extends Controller {
    public function __invoke(): View {
        Gate::authorize('manage-access');

        /** @var User $auth */
        $auth = Auth::user();
        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        $teamForeign = config('permission.column_names.team_foreign_key', 'team_id');

        $rolesCount = Role::query()
            ->where(static function ($q) use ($teamForeign, $organization): void {
                $q->whereNull($teamForeign);
                if ($organization instanceof Organization) {
                    $q->orWhere($teamForeign, $organization->id);
                }
            })
            ->count();

        $groupsCount = $organization instanceof Organization
            ? UserGroup::query()->where('organization_id', $organization->id)->count()
            : 0;

        $membersCount = $auth->organization_id
            // TENANT-BYPASS: User-Sonderfall (kein Trait); Org-Filter explizit.
            ? User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $auth->organization_id)
            ->count()
            : 0;

        $permissionsCount = Permission::query()->count();

        return view('admin.access.index', [
            'organization' => $organization,
            'rolesCount' => $rolesCount,
            'groupsCount' => $groupsCount,
            'membersCount' => $membersCount,
            'permissionsCount' => $permissionsCount,
        ]);
    }
}
