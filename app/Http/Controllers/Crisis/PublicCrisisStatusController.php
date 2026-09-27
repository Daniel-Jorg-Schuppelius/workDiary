<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicCrisisStatusController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Crisis;

use App\Http\Controllers\Controller;
use App\Models\Platform\Organization;
use App\Services\Crisis\CrisisStatusPageService;
use Illuminate\View\View;

/** Öffentliche Statusseite ohne Anmeldung (MVP-915); unbekannter oder pausierter Token → 404. */
class PublicCrisisStatusController extends Controller {
    public function show(string $token, CrisisStatusPageService $statusPage): View {
        $organization = $statusPage->resolve($token);
        abort_unless($organization instanceof Organization, 404);

        return view('public.crisis-status', [
            'orgName' => $organization->name,
            'notices' => $statusPage->notices($organization, ['public']),
        ]);
    }
}
