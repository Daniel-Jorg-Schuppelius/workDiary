<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentChainController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Billing\DocumentChainService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Belegkette (MVP-1057): was abzurechnen oder nachzufassen ist, aus allen Modulen. */
class DocumentChainController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(Request $request, DocumentChainService $chain): View {
        Gate::authorize('viewAny', Invoice::class);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return view('billing.chain', [
            'groups' => $chain->groups($this->currentOrganization(), $user, 50),
        ]);
    }
}
