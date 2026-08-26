<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalRequestController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Rental;

use App\Exceptions\RentalConflictException;
use App\Http\Controllers\Controller;
use App\Models\{Asset, User};
use App\Models\Rental\{RentalCase, RentalProfile, RentalRequest};
use App\Services\Rental\RentalRequestService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Interne Entscheidung über Portal-Verleihanfragen (Feature 073, MVP-714):
 * Annahme erzeugt Akte (Entwurf) + Vormerkung über den Service, Ablehnung
 * mit Grund. Rechte wie die Verleihakte (rental.viewAny / rental.manage).
 */
class RentalRequestController extends Controller {
    public function __construct(private readonly RentalRequestService $service) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', RentalCase::class);

        $status = $request->string('status')->toString();

        return view('rental.requests', [
            'requests' => RentalRequest::query()
                ->with(['customer:id,name', 'asset:id,name', 'portalUser:id,name,email', 'decidedBy:id,name', 'rentalCase:id,number'])
                ->when($status === '' || $status === 'open', fn ($q) => $q->open())
                ->when($status !== '' && $status !== 'open' && $status !== 'all', fn ($q) => $q->where('status', $status))
                ->orderByRaw("CASE WHEN status = 'requested' THEN 0 ELSE 1 END")
                ->orderBy('starts_at')
                ->paginate(25)
                ->withQueryString(),
            'status' => $status === '' ? 'open' : $status,
            'canManage' => Gate::allows('create', RentalCase::class),
            // Gerätewahl für Gruppenanfragen: leihfähige Profile je Gruppe.
            'assetsByGroup' => RentalProfile::query()
                ->rentable()
                ->whereNotNull('group_code')
                ->with('asset:id,name')
                ->get()
                ->groupBy('group_code')
                ->map(fn ($profiles) => $profiles->map(fn (RentalProfile $p): ?Asset => $p->asset)->filter()->sortBy('name')->values()),
        ]);
    }

    public function accept(Request $request, RentalRequest $rentalRequest): RedirectResponse {
        Gate::authorize('create', RentalCase::class);

        $asset = null;
        if ($request->filled('asset')) {
            $id = Sqid::decode(Asset::class, (string) $request->input('asset'));
            $asset = $id !== null ? Asset::query()->whereKey($id)->first() : null;
            abort_if($asset === null, 404);
        }

        try {
            $accepted = $this->service->accept($rentalRequest, $this->actor(), $asset);
        } catch (RentalConflictException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Anfrage angenommen — Verleihakte :number als Entwurf angelegt und Zeitraum vorgemerkt.', [
            'number' => $accepted->rentalCase !== null ? $accepted->rentalCase->number : '—',
        ]));
    }

    public function decline(Request $request, RentalRequest $rentalRequest): RedirectResponse {
        Gate::authorize('create', RentalCase::class);

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        try {
            $this->service->decline($rentalRequest, $this->actor(), (string) $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Anfrage abgelehnt — der Kunde wurde informiert.'));
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
