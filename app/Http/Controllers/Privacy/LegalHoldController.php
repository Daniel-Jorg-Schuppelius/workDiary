<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegalHoldController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Privacy\{ComplianceFinding, LegalHold};
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Retention\LegalHoldService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Legal Hold (MVP-801, Feature 130). Gleiche Rechte wie die Aufbewahrungs-
 * entscheidungen: Wer über Löschungen entscheidet, sperrt sie auch.
 */
class LegalHoldController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly LegalHoldService $legalHolds) {}

    public function index(): View {
        Gate::authorize('viewAny', ComplianceFinding::class);

        return view('privacy.legal-holds.index', [
            'holds' => LegalHold::query()
                ->with(['holdable', 'placedBy:id,name', 'releasedBy:id,name'])
                ->orderByRaw('released_at IS NULL DESC')
                ->orderByDesc('placed_at')
                ->paginate(50),
            'canManage' => Gate::allows('manage', ComplianceFinding::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('manage', ComplianceFinding::class);
        $organization = $this->currentOrganization();

        return view('privacy.legal-holds._form_dialog', [
            'users' => User::query()->where('organization_id', $organization->id)->whereNull('customer_id')->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('manage', ComplianceFinding::class);

        $kind = $request->input('kind') === 'customer' ? 'customer' : 'user';
        $request->merge([
            'subject' => $kind === 'customer'
                ? Sqid::decodeOrNumeric(Customer::class, $request->input('customer_id'))
                : Sqid::decodeOrNumeric(User::class, $request->input('user_id')),
        ]);

        $data = $request->validate([
            'kind' => ['required', Rule::in(['user', 'customer'])],
            'subject' => ['required', 'integer', new ExistsInCurrentOrganization($kind === 'customer' ? 'customers' : 'users')],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $holdable = $kind === 'customer'
            ? Customer::query()->findOrFail((int) $data['subject'])
            : User::query()->where('organization_id', $this->currentOrganization()->id)->findOrFail((int) $data['subject']);

        /** @var User $actor */
        $actor = Auth::user();
        $this->legalHolds->place($holdable, (string) $data['reason'], $data['reference'] ?? null, $actor);

        return redirect()->route('dataprotection.legal-holds.index')
            ->with('status', __('Legal Hold gesetzt.'));
    }

    public function releaseDialog(LegalHold $hold): View {
        Gate::authorize('manage', ComplianceFinding::class);

        return view('privacy.legal-holds._release_dialog', ['hold' => $hold]);
    }

    public function release(Request $request, LegalHold $hold): RedirectResponse {
        Gate::authorize('manage', ComplianceFinding::class);

        $data = $request->validate(['release_reason' => ['required', 'string', 'min:10', 'max:2000']]);

        /** @var User $actor */
        $actor = Auth::user();
        $this->legalHolds->release($hold, (string) $data['release_reason'], $actor);

        return redirect()->route('dataprotection.legal-holds.index')
            ->with('status', __('Legal Hold aufgehoben.'));
    }
}
