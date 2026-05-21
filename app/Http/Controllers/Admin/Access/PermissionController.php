<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PermissionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin\Access;

use App\Enums\User\Permission as PermissionEnum;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Read-only-Übersicht aller verfügbaren Permissions, gruppiert nach
 * Ressource. Permissions sind im Enum {@see PermissionEnum} hartkodiert
 * und können nicht zur Laufzeit erzeugt werden — die UI dient ausschließlich
 * der Transparenz für Org-Admins.
 */
class PermissionController extends Controller {
    public function index(): View {
        Gate::authorize('manage-access');

        return view('admin.access.permissions.index', [
            'grouped' => PermissionEnum::grouped(),
        ]);
    }
}
