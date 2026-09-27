<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenantUsageController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\RequiresPlatformOperator;
use App\Http\Controllers\Controller;
use App\Models\Platform\Organization;
use App\Services\Licensing\ModuleStatusResolver;
use App\Services\Metrics\OperationsMetricsService;
use Illuminate\View\View;

/**
 * Nutzungsübersicht je Mandant für den Plattformbetrieb (MVP-951); nur für Betreiber.
 */
class TenantUsageController extends Controller {
    use RequiresPlatformOperator;

    public function index(OperationsMetricsService $metrics, ModuleStatusResolver $modules): View {
        $this->assertPlatformOperator();
        $organizations = Organization::query()->withoutGlobalScopes()->withCount(['users' => fn ($q) => $q->withoutGlobalScopes()->whereNull('deactivated_at')])->orderBy('name')->paginate(50);
        $rows = [];
        foreach ($organizations as $organization) {
            $usage = $metrics->tenantUsage($organization);
            $rows[] = [
                'organization' => $organization,
                'users' => (int) $organization->getAttribute('users_count'),
                'active_users' => $usage['active_users'],
                'bytes' => $usage['bytes'],
                'last_activity' => $usage['last_activity'],
                'modules' => count(array_filter($modules->forOrganization($organization), static fn (array $row): bool => $row['available'])),
                'status' => $organization->tenantStatus(),
            ];
        }

        return view('admin.organizations.usage', ['rows' => $rows, 'organizations' => $organizations]);
    }
}
