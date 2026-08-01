<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PassengerMasterDataController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Passenger;

use App\Enums\Passenger\RideOperationMode;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\{Organization, Vehicle};
use App\Models\Passenger\{PassengerConcession, PassengerFareTariff, PassengerFareTariffRule, PassengerVehicleProfile};
use App\Services\Passenger\PassengerRideService;
use App\Support\Sqid;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Stammdaten der Personenbeförderung (MVP-456): versionierte Tarife mit
 * Zuschlagsregeln, Konzessionen je Betriebsart und Fahrzeugprofile mit
 * Geräte-/Nachweisstatus — eine Seite, drei Fachtabellen (Muster Claims-
 * Inline-Formulare für die Regeln, Dialoge fürs Anlegen/Bearbeiten).
 */
class PassengerMasterDataController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly PassengerRideService $rides) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', PassengerFareTariff::class);
        $this->passengerOrganization();

        $selectedTariff = null;
        if ($request->filled('tariff')) {
            $id = Sqid::decodeOrNumeric(PassengerFareTariff::class, $request->input('tariff'));
            $selectedTariff = $id !== null ? PassengerFareTariff::query()->with('rules')->find((int) $id) : null;
        }

        return view('passenger.masterdata.index', [
            'tariffs' => PassengerFareTariff::query()->withCount('rules')->orderBy('operation_mode')->orderByDesc('valid_from')->get(),
            'concessions' => PassengerConcession::query()->orderBy('operation_mode')->orderBy('reference_no')->get(),
            'profiles' => PassengerVehicleProfile::query()->with('vehicle')->get(),
            'selectedTariff' => $selectedTariff,
        ]);
    }

    // ── Tarife ─────────────────────────────────────────────────────────

    public function createTariff(): View {
        Gate::authorize('create', PassengerFareTariff::class);
        $this->passengerOrganization();

        return view('passenger.masterdata._tariff_dialog', ['tariff' => null]);
    }

    public function editTariff(PassengerFareTariff $tariff): View {
        Gate::authorize('update', $tariff);
        $this->assertInOrganization($tariff->organization_id);

        return view('passenger.masterdata._tariff_dialog', ['tariff' => $tariff]);
    }

    public function storeTariff(Request $request): RedirectResponse {
        Gate::authorize('create', PassengerFareTariff::class);
        $organization = $this->passengerOrganization();

        $tariff = PassengerFareTariff::query()->create([
            'organization_id' => $organization->id,
            ...$this->validateTariff($request),
        ]);
        $tariff->audit('passenger.tariff_created', []);

        return redirect()->route('passenger-masterdata.index')->with('status', (string) __('passenger.flash.tariff_saved'));
    }

    public function updateTariff(Request $request, PassengerFareTariff $tariff): RedirectResponse {
        Gate::authorize('update', $tariff);
        $this->assertInOrganization($tariff->organization_id);

        $tariff->update($this->validateTariff($request));
        $tariff->audit('passenger.tariff_updated', []);

        return redirect()->route('passenger-masterdata.index')->with('status', (string) __('passenger.flash.tariff_saved'));
    }

    /** Zuschlags-/Rabattregel anlegen (Inline-Formular der Regel-Karte). */
    public function storeTariffRule(Request $request, PassengerFareTariff $tariff): RedirectResponse {
        Gate::authorize('update', $tariff);
        $this->assertInOrganization($tariff->organization_id);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'label' => ['required', 'string', 'max:160'],
            'kind' => ['required', 'string', 'in:' . PassengerFareTariffRule::KIND_SURCHARGE . ',' . PassengerFareTariffRule::KIND_DISCOUNT],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $tariff->rules()->create([
            'organization_id' => $tariff->organization_id,
            ...$validated,
            'sort_order' => (int) $tariff->rules()->max('sort_order') + 1,
        ]);
        $tariff->audit('passenger.tariff_rule_added', ['code' => $validated['code']]);

        return redirect()->route('passenger-masterdata.index', ['tariff' => $tariff->sqid])->with('status', (string) __('passenger.flash.tariff_saved'));
    }

    public function destroyTariffRule(PassengerFareTariff $tariff, PassengerFareTariffRule $rule): RedirectResponse {
        Gate::authorize('update', $tariff);
        $this->assertInOrganization($tariff->organization_id);
        abort_unless($rule->tariff_id === $tariff->id, 404);

        $rule->delete();
        $tariff->audit('passenger.tariff_rule_removed', ['code' => $rule->code]);

        return redirect()->route('passenger-masterdata.index', ['tariff' => $tariff->sqid])->with('status', (string) __('passenger.flash.tariff_saved'));
    }

    // ── Konzessionen ───────────────────────────────────────────────────

    public function createConcession(): View {
        Gate::authorize('create', PassengerConcession::class);
        $this->passengerOrganization();

        return view('passenger.masterdata._concession_dialog', ['concession' => null]);
    }

    public function editConcession(PassengerConcession $concession): View {
        Gate::authorize('update', $concession);
        $this->assertInOrganization($concession->organization_id);

        return view('passenger.masterdata._concession_dialog', ['concession' => $concession]);
    }

    public function storeConcession(Request $request): RedirectResponse {
        Gate::authorize('create', PassengerConcession::class);
        $organization = $this->passengerOrganization();

        $concession = PassengerConcession::query()->create([
            'organization_id' => $organization->id,
            ...$this->validateConcession($request),
            'created_by' => ($request->user() ?? abort(401))->id,
        ]);
        $concession->audit('passenger.concession_created', []);

        return redirect()->route('passenger-masterdata.index')->with('status', (string) __('passenger.flash.concession_saved'));
    }

    public function updateConcession(Request $request, PassengerConcession $concession): RedirectResponse {
        Gate::authorize('update', $concession);
        $this->assertInOrganization($concession->organization_id);

        $concession->update($this->validateConcession($request));
        $concession->audit('passenger.concession_updated', []);

        return redirect()->route('passenger-masterdata.index')->with('status', (string) __('passenger.flash.concession_saved'));
    }

    // ── Fahrzeugprofile ────────────────────────────────────────────────

    public function createVehicleProfile(): View {
        Gate::authorize('create', PassengerVehicleProfile::class);
        $this->passengerOrganization();

        return view('passenger.masterdata._vehicle_profile_dialog', [
            'profile' => null,
            'vehicles' => Vehicle::query()->orderBy('license_plate')->get(),
        ]);
    }

    public function editVehicleProfile(PassengerVehicleProfile $profile): View {
        Gate::authorize('update', $profile);
        $this->assertInOrganization($profile->organization_id);

        return view('passenger.masterdata._vehicle_profile_dialog', [
            'profile' => $profile,
            'vehicles' => Vehicle::query()->orderBy('license_plate')->get(),
        ]);
    }

    public function storeVehicleProfile(Request $request): RedirectResponse {
        Gate::authorize('create', PassengerVehicleProfile::class);
        $organization = $this->passengerOrganization();

        $validated = $this->validateVehicleProfile($request);
        abort_if(PassengerVehicleProfile::query()->where('vehicle_id', $validated['vehicle_id'])->exists(), 422);

        $profile = PassengerVehicleProfile::query()->create([
            'organization_id' => $organization->id,
            ...$validated,
        ]);
        $profile->audit('passenger.vehicle_profile_created', []);

        return redirect()->route('passenger-masterdata.index')->with('status', (string) __('passenger.flash.vehicle_profile_saved'));
    }

    public function updateVehicleProfile(Request $request, PassengerVehicleProfile $profile): RedirectResponse {
        Gate::authorize('update', $profile);
        $this->assertInOrganization($profile->organization_id);

        $validated = $this->validateVehicleProfile($request);
        unset($validated['vehicle_id']); // Fahrzeugbindung ist unveränderlich (1:1).
        $profile->update($validated);
        $profile->audit('passenger.vehicle_profile_updated', []);

        return redirect()->route('passenger-masterdata.index')->with('status', (string) __('passenger.flash.vehicle_profile_saved'));
    }

    // ── Validierung ────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validateTariff(Request $request): array {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'tariff_area' => ['nullable', 'string', 'max:200'],
            'operation_mode' => ['required', 'string', 'in:' . implode(',', RideOperationMode::values())],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'price_per_km' => ['required', 'numeric', 'min:0'],
            'price_per_minute' => ['required', 'numeric', 'min:0'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'fixed_price_min_percent' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'fixed_price_max_percent' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'active' => ['nullable', 'boolean'],
        ]);
        $validated['currency'] = CurrencyCode::Euro->value;
        $validated['active'] = (bool) ($validated['active'] ?? false);

        return $validated;
    }

    /** @return array<string, mixed> */
    private function validateConcession(Request $request): array {
        $validated = $request->validate([
            'operation_mode' => ['required', 'string', 'in:' . implode(',', RideOperationMode::values())],
            'authority' => ['required', 'string', 'max:160'],
            'reference_no' => ['required', 'string', 'max:64'],
            'business_seat' => ['nullable', 'string', 'max:200'],
            'service_area' => ['nullable', 'string', 'max:200'],
            'tariff_area' => ['nullable', 'string', 'max:200'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'licensed_vehicles' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'conditions' => ['nullable', 'string', 'max:2000'],
            'active' => ['nullable', 'boolean'],
        ]);
        $validated['active'] = (bool) ($validated['active'] ?? false);

        return $validated;
    }

    /** @return array<string, mixed> */
    private function validateVehicleProfile(Request $request): array {
        $request->merge(['vehicle_id' => Sqid::decodeOrNumeric(Vehicle::class, $request->input('vehicle_id'))]);
        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('vehicles')],
            'order_number' => ['nullable', 'string', 'max:32'],
            'operation_modes' => ['required', 'array', 'min:1'],
            'operation_modes.*' => ['string', 'in:' . implode(',', RideOperationMode::values())],
            'passenger_seats' => ['nullable', 'integer', 'min:1', 'max:60'],
            'wheelchair_places' => ['nullable', 'integer', 'min:0', 'max:10'],
            'barrier_free' => ['nullable', 'boolean'],
            'large_capacity' => ['nullable', 'boolean'],
            'meter_kind' => ['nullable', 'string', 'in:' . PassengerVehicleProfile::METER_TAXAMETER . ',' . PassengerVehicleProfile::METER_ODOMETER],
            'meter_serial' => ['nullable', 'string', 'max:64'],
            'meter_calibrated_until' => ['nullable', 'date'],
            'tse_reference' => ['nullable', 'string', 'max:120'],
            'bokraft_checked_until' => ['nullable', 'date'],
            'hu_valid_until' => ['nullable', 'date'],
        ]);
        $validated['wheelchair_places'] = (int) ($validated['wheelchair_places'] ?? 0);
        $validated['barrier_free'] = (bool) ($validated['barrier_free'] ?? false);
        $validated['large_capacity'] = (bool) ($validated['large_capacity'] ?? false);

        return $validated;
    }

    private function assertInOrganization(int $organizationId): void {
        $organization = $this->passengerOrganization();
        abort_unless($organizationId === $organization->id, 404);
    }

    /** Branchenprofil-Gate: 404 ohne installiertes Profil (Muster Recipes). */
    private function passengerOrganization(): Organization {
        $organization = $this->currentOrganization();
        abort_unless($this->rides->isPassengerProfileActive($organization), 404);

        return $organization;
    }
}
