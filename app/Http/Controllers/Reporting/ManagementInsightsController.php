<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagementInsightsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Platform\User;
use App\Services\Reporting\Contracts\TrainingNeedSource;
use App\Services\Reporting\Dto\EarlyWarning;
use App\Services\Reporting\EarlyWarningService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Management-Auswertung (MVP-926): wiederkehrende Probleme aus allen
 * Frühwarnquellen, gruppiert nach Art, und Schulungsbedarf der Organisation.
 */
class ManagementInsightsController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(EarlyWarningService $warnings, TrainingNeedSource $training): View {
        $user = Auth::user();
        abort_unless($user instanceof User && ($user->isAdmin() || $user->can(Permission::ReportView->value)), 403);
        $organization = $this->currentOrganization();

        return view('reports.management-insights', [
            'groups' => collect($warnings->collect($organization))->groupBy(fn (EarlyWarning $w): string => $w->kind)->sortKeys(),
            'trainingNeeds' => $training->trainingNeeds($organization),
        ]);
    }
}
