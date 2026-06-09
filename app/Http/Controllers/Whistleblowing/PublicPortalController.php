<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicPortalController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Whistleblowing;

use App\Http\Controllers\Controller;
use App\Models\Whistleblowing\Portal;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Oeffentliche Portalseite (Zweck, Vertraulichkeit, Datenschutz, externe
 * Meldewege) und das Meldeformular. Kein App-Menue, kein Auth-Kontext.
 */
class PublicPortalController extends Controller {
    public function show(Request $request): View {
        /** @var Portal $portal */
        $portal = $request->attributes->get('wb_portal');

        return view('whistleblowing.public.portal', [
            'portal' => $portal,
        ]);
    }
}
