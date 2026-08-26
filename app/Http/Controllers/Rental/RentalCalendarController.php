<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalCalendarController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Rental;

use App\Enums\Rental\RentalReservationKind;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Rental\{RentalCase, RentalProfile, RentalReservation};
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Rental\RentalAvailabilityService;
use App\Support\Sqid;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Verfügbarkeitskalender (MVP-260): Monatssicht über Belegungsfenster
 * (Reservierung/Verleih/Wartung/Reinigung/Transport) je Gerät oder
 * Gerätegruppe; manuelle Fenster (z. B. Wartung) werden hier gepflegt.
 */
class RentalCalendarController extends Controller {
    public function __construct(private readonly RentalAvailabilityService $availability) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', RentalCase::class);

        try {
            $month = $request->filled('month')
                ? Carbon::parse($request->string('month')->toString() . '-01')->startOfMonth()
                : now()->startOfMonth();
        } catch (\Carbon\Exceptions\InvalidFormatException) {
            $month = now()->startOfMonth(); // Müll-Input ⇒ aktueller Monat statt 500 (B10).
        }

        $groupCode = $request->string('group')->toString() ?: null;
        $assetId = $request->filled('asset_id')
            ? Sqid::decodeOrNumeric(Asset::class, $request->input('asset_id'))
            : null;

        $reservations = RentalReservation::query()
            ->active()
            ->with(['asset', 'rentalCase.customer'])
            ->where('starts_at', '<', $month->copy()->endOfMonth()->addDays(14))
            ->where('ends_at', '>', $month->copy()->subDays(14))
            ->when($assetId !== null, fn($q) => $q->where('asset_id', $assetId))
            ->when($groupCode !== null, fn($q) => $q->whereHas(
                'asset.rentalProfile',
                fn($p) => $p->where('group_code', $groupCode),
            ))
            ->orderBy('starts_at')
            ->get();

        // Belegungsfenster (inkl. Puffer) auf Kalendertage verteilen.
        $itemsByDay = [];
        foreach ($reservations as $reservation) {
            $cursor = $reservation->blockedFrom()->copy()->startOfDay();
            $last = $reservation->blockedUntil();

            while ($cursor <= $last) {
                if ($cursor->isSameMonth($month)) {
                    $itemsByDay[$cursor->toDateString()][] = $reservation;
                }
                $cursor->addDay();
            }
        }

        return view('rental.calendar', [
            'month' => $month,
            'itemsByDay' => $itemsByDay,
            'assets' => Asset::query()
                ->whereHas('rentalProfile', fn($q) => $q->where('is_rentable', true))
                ->orderBy('name')
                ->get(['id', 'name']),
            'groups' => RentalProfile::query()
                ->whereNotNull('group_code')
                ->distinct()
                ->orderBy('group_code')
                ->pluck('group_code'),
            'filterAsset' => $assetId,
            'filterGroup' => $groupCode,
        ]);
    }

    /**
     * Manuelles Belegungsfenster (Wartung/Reinigung/Transport/Vormerkung).
     */
    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', RentalCase::class);

        if ($request->filled('asset_id')) {
            $request->merge(['asset_id' => Sqid::decodeOrNumeric(Asset::class, $request->input('asset_id'))]);
        }

        $data = $request->validate([
            'asset_id' => ['required', 'integer', new ExistsInCurrentOrganization('assets')],
            'kind' => ['required', Rule::enum(RentalReservationKind::class)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $kind = RentalReservationKind::from($data['kind']);
        $asset = Asset::query()->whereKey($data['asset_id'])->firstOrFail();

        // Schreibstelle im Service (MVP-714): Kind-Regel + Konfliktprüfung
        // gelten identisch für manuelle Fenster und Portal-Anfragen.
        try {
            $this->availability->createWindow(
                $asset,
                $kind,
                Carbon::parse($data['starts_at']),
                Carbon::parse($data['ends_at']),
                $data['note'] ?? null,
                $request->user()?->id,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['kind' => $e->getMessage()]);
        } catch (\App\Exceptions\RentalConflictException $e) {
            return back()->withErrors(['starts_at' => $e->getMessage()]);
        }

        return back()->with('status', __('Belegungsfenster eingetragen.'));
    }

    public function cancel(Request $request, RentalReservation $reservation): RedirectResponse {
        Gate::authorize('create', RentalCase::class);

        if ($reservation->rental_case_id !== null) {
            return back()->withErrors(['reservation' => __('Aktenreservierungen werden über die Verleihakte gesteuert.')]);
        }

        $reservation->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();

        return back()->with('status', __('Belegungsfenster storniert.'));
    }
}
