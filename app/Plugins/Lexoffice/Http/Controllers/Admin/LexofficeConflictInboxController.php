<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeConflictInboxController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Platform\User;
use App\Plugins\Lexoffice\LexofficePlugin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/** Alte Adresse der Lexoffice-Konflikte: leitet in die Zuordnungs-Inbox (MVP-103), dort werden sie aufgelöst. */
class LexofficeConflictInboxController extends Controller {
    public function index(): RedirectResponse {
        /** @var User $admin */
        $admin = Auth::user();
        abort_unless($admin->canManageBilling(), 403);

        return redirect()->route('admin.integration.inbox', ['plugin' => LexofficePlugin::ID, 'case' => 'conflict']);
    }
}
