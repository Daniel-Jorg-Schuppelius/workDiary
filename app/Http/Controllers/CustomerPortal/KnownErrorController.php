<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnownErrorController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\{Problem, User};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Bekannte Fehler im Kundenportal (Feature 065, MVP-156): read-only
 * Liste (Titel + Workaround). Quelle sind AUSSCHLIESSLICH Probleme mit
 * status='known_error' UND visibility='customer' — interne Probleme
 * erreichen das Portal strukturell nie (Leak-Test). Org-Scope hart über
 * die Portal-Session (zusätzlich zur Global-Scope-Linie).
 */
class KnownErrorController extends Controller {
    public function index(): View {
        $user = $this->portalUser();

        return view('customer.known-errors.index', [
            'problems' => Problem::query()
                ->where('organization_id', (int) $user->organization_id)
                ->where('status', 'known_error')
                ->where('visibility', 'customer')
                ->orderBy('title')
                ->paginate(25),
        ]);
    }

    private function portalUser(): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        abort_if($user->customer_id === null, 403);

        return $user;
    }
}
