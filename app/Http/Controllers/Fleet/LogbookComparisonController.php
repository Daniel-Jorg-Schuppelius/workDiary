<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LogbookComparisonController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use App\Models\Fleet\{Vehicle, VehicleAnnualCost};
use App\Models\Platform\User;
use App\Services\Fleet\PrivateUseComparison;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/** 1-%-Vergleich je Fahrzeug im Fahrtenbuch-Modus (MVP-993); Sicht wie der Fahrtenbuch-Nachweis. */
class LogbookComparisonController extends Controller {
    public function __construct(private readonly PrivateUseComparison $comparison) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', Vehicle::class);
        /** @var User $user */
        $user = Auth::user();
        $year = max(2000, min(2100, (int) $request->query('year', (string) now()->subYear()->year)));

        $vehicles = Vehicle::query()
            ->where('logbook_mode', true)
            ->when(! $user->isAdmin(), fn ($q) => $q->forUser((int) $user->id))
            ->orderBy('label')->orderBy('license_plate')
            ->get();

        return view('reports.logbook-comparison', [
            'year' => $year,
            'rows' => $vehicles->map(fn (Vehicle $vehicle): array => ['vehicle' => $vehicle, 'result' => $this->comparison->compare($vehicle, $year)])->all(),
        ]);
    }

    public function costsForm(Request $request, Vehicle $vehicle): View {
        Gate::authorize('update', $vehicle);
        $year = (int) $request->query('year', (string) now()->subYear()->year);

        return view('reports._vehicle_annual_cost_dialog', [
            'vehicle' => $vehicle,
            'year' => $year,
            'cost' => VehicleAnnualCost::query()->where('vehicle_id', $vehicle->id)->where('year', $year)->first(),
        ]);
    }

    public function storeCosts(Request $request, Vehicle $vehicle): RedirectResponse {
        Gate::authorize('update', $vehicle);
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'cost_amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $currency = $vehicle->currency ?? CurrencyCode::tryFrom(strtoupper((string) config('invoicing.default_currency', 'EUR'))) ?? CurrencyCode::Euro;
        VehicleAnnualCost::query()->updateOrCreate(
            ['vehicle_id' => $vehicle->id, 'year' => (int) $data['year']],
            ['organization_id' => $vehicle->organization_id, 'currency' => $currency, 'cost_amount' => Money::of((string) $data['cost_amount'], $currency), 'note' => $data['note'] ?? null, 'created_by' => Auth::id()],
        );

        return redirect()->route('reports.logbook-comparison', ['year' => (int) $data['year']])->with('success', __('Jahreskosten gespeichert.'));
    }
}
