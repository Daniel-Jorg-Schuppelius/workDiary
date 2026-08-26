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

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\{Asset, User};
use App\Models\Rental\RentalRequest;
use App\Services\Rental\RentalRequestService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

/**
 * Portal-Verleihanfrage (Feature 073, MVP-714): freigegebenes Sortiment,
 * grobe Verfügbarkeit (nur frei/belegt), zweiphasige Anfrage — der Kunde
 * reserviert nie selbst. Eigene Anfragen, eigener Kunde, sonst 404.
 */
class RentalRequestController extends Controller {
    public function __construct(private readonly RentalRequestService $service) {}

    public function index(Request $request): View {
        $user = $this->portalUser();
        $customer = $user->customer;
        abort_if($customer === null, 404);

        [$from, $to] = $this->rangeFromQuery($request);

        $profiles = $this->service->bookableProfiles($customer);
        $availability = [];
        if ($from !== null && $to !== null && $to->greaterThan($from)) {
            foreach ($profiles as $profile) {
                /** @var Asset $asset */
                $asset = $profile->asset;
                $availability[$asset->id] = $this->service->isRoughlyAvailable($asset, $from, $to);
            }
        }

        $requests = RentalRequest::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $user->organization_id)
            ->where('customer_id', $user->customer_id)
            ->with(['asset:id,name'])
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return view('customer.rental.requests', [
            'profiles' => $profiles,
            'groups' => $profiles->pluck('group_code')->filter()->unique()->sort()->values(),
            'from' => $from,
            'to' => $to,
            'availability' => $availability,
            'requests' => $requests,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $user = $this->portalUser();
        $customer = $user->customer;
        abort_if($customer === null, 404);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:80'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after:from'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        [$asset, $group] = $this->resolveSubject((string) $data['subject'], (int) $user->organization_id);

        try {
            $this->service->requestFromPortal(
                $customer,
                $user,
                $asset,
                $group,
                Carbon::parse((string) $data['from']),
                Carbon::parse((string) $data['to']),
                $data['note'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('customer.rentals.requests.index')
            ->with('success', __('Verleih-Anfrage gesendet — wir prüfen die Verfügbarkeit und melden uns.'));
    }

    public function withdraw(RentalRequest $rentalRequest): RedirectResponse {
        $user = $this->portalUser();
        abort_unless(
            (int) $rentalRequest->organization_id === (int) $user->organization_id
            && (int) $rentalRequest->customer_id === (int) $user->customer_id,
            404,
        );

        try {
            $this->service->withdrawFromPortal($rentalRequest, $user);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Verleih-Anfrage zurückgenommen.'));
    }

    /**
     * Formularwert `asset:<sqid>` oder `group:<code>` → (Asset|null, Gruppe|null).
     * Fremde/unbekannte Geräte antworten 404; die Sortiments-Freigabe prüft
     * der Service (Default-Deny).
     *
     * @return array{0: Asset|null, 1: string|null}
     */
    private function resolveSubject(string $value, int $organizationId): array {
        if (str_starts_with($value, 'asset:')) {
            $id = Sqid::decode(Asset::class, substr($value, 6));
            $asset = $id !== null
                ? Asset::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->whereKey($id)->first()
                : null;
            abort_if($asset === null, 404);

            return [$asset, null];
        }
        if (str_starts_with($value, 'group:')) {
            return [null, substr($value, 6)];
        }

        abort(404);
    }

    /** @return array{0: Carbon|null, 1: Carbon|null} */
    private function rangeFromQuery(Request $request): array {
        $parse = static function (mixed $raw): ?Carbon {
            if (! is_string($raw) || $raw === '') {
                return null;
            }
            try {
                return Carbon::parse($raw);
            } catch (\Carbon\Exceptions\InvalidFormatException) {
                return null;
            }
        };

        return [$parse($request->query('from')), $parse($request->query('to'))];
    }

    private function portalUser(): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        abort_if($user->customer_id === null, 404);

        return $user;
    }
}
