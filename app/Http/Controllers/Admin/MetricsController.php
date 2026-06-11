<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MetricsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Services\Metrics\OperationsMetricsService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin-Seite „Betriebsmetriken" (Feature 036, MVP) — read-only Kennzahlen
 * inkl. aggregierter Feature-Nutzung. Health-Checks mit Ampel-Status liegen
 * auf der Diagnose-Seite (MVP-044), nicht hier.
 */
class MetricsController extends Controller {
    public function index(OperationsMetricsService $metrics): View {
        Gate::authorize(Permission::MetricsView->value);

        return view('admin.metrics.index', [
            'metrics' => $metrics->collect(),
        ]);
    }
}
