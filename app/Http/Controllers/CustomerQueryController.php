<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerQueryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Customer\CustomerQueryStatus;
use App\Enums\User\Permission;
use App\Models\{CustomerQuery, User};
use App\Services\Customer\CustomerQueryService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Interne Verwaltung der Kunden-Rückfragen (Feature 012).
 *
 * Mandantentrennung erfolgt über den OrganizationScope der Modelle; der
 * Zugriff ist über die Permission `protocol.customerQuery.manage` geschützt.
 * Die Kundenseite (Erfassen/Anzeige) läuft über den öffentlichen
 * Signaturlink bzw. den `customer`-Guard und berührt diese Routen nicht.
 */
class CustomerQueryController extends Controller {
    public function __construct(private readonly CustomerQueryService $service) {}

    public function index(Request $request): View {
        $this->authorizeAccess();

        $status = CustomerQueryStatus::tryFrom((string) $request->query('status', ''));

        $queries = CustomerQuery::query()
            ->when($status, fn($q, CustomerQueryStatus $s) => $q->where('status', $s->value))
            ->with(['customer', 'answeredBy', 'subject'])
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('customer-queries.index', [
            'queries' => $queries,
            'status' => $status,
        ]);
    }

    public function answer(Request $request, CustomerQuery $customerQuery): RedirectResponse {
        $this->authorizeAccess();

        $data = $request->validate([
            'answer' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->answer($customerQuery, $actor, $data['answer']);

        return redirect()->route('customer-queries.index')
            ->with('success', __('customer-query.answered'));
    }

    public function close(CustomerQuery $customerQuery): RedirectResponse {
        $this->authorizeAccess();

        $this->service->close($customerQuery);

        return redirect()->route('customer-queries.index')
            ->with('success', __('customer-query.closed'));
    }

    private function authorizeAccess(): void {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user === null || ! ($user->isAdmin() || $user->can(Permission::ProtocolCustomerQueryManage->value))) {
            abort(403);
        }
    }
}
