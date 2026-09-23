<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StartPageController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Models\Platform\User;
use App\Services\Navigation\StartPageResolver;
use Illuminate\Http\{RedirectResponse, Request};
use App\Http\Controllers\Controller;

/**
 * Ziel nach dem Login (MVP-799). Eigene Route statt Auflösung im Login selbst:
 * Erst hier laufen Organisationskontext, Lizenz und Rollen-Team — ohne sie
 * ließe sich nicht prüfen, ob die Person die Startseite öffnen darf.
 */
class StartPageController extends Controller {
    public function __invoke(Request $request, StartPageResolver $startPages): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        return redirect()->route($startPages->routeFor($user) ?? 'diary.index');
    }
}
