<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Services\Security\SecurityOverviewService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin-Seite „Sicherheit" (Feature 016, MVP) — read-only Überblick
 * sicherheitsrelevanter Zustände: aktive Sessions, API-Tokens, externe
 * Integrationen, letzte Exporte, letzte Supportzugriffe sowie
 * 2FA-/Verschlüsselungs-Kennzahlen.
 *
 * Reine Anzeige: es werden keine Sicherheitsobjekte verändert. Die
 * automatisierten Lösch-/Aufbewahrungsläufe (Feature 016, „Später") sind
 * NICHT Teil dieser Seite.
 */
class SecurityController extends Controller {
    public function index(SecurityOverviewService $overview): View {
        Gate::authorize(Permission::SecurityView->value);

        return view('admin.security.index', [
            'security' => $overview->collect(),
        ]);
    }
}
