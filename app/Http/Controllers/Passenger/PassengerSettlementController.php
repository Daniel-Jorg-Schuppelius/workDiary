<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PassengerSettlementController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Passenger\PassengerShiftSettlement;
use App\Models\{User, Vehicle};
use App\Services\Passenger\PassengerRideService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Fahrer-/Schichtabrechnung (MVP-456, Konzept §8): Geräteumsatz getrennt von
 * den Zahlarten, Trinkgeld separat, Differenzen bleiben offen bis zur
 * begründeten Klärung. Abschluss verlangt die `settle`-Ability
 * (Kassenfunktion, getrennt von der Disposition).
 */
class PassengerSettlementController extends Controller {
    use ResolvesCurrentOrganization;
    use ResolvesGlobalDateRange;

    public function __construct(private readonly PassengerRideService $rides) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', PassengerShiftSettlement::class);
        $this->passengerOrganization();
        [$from, $to] = $this->globalDateRangeBounds();

        $settlements = PassengerShiftSettlement::query()
            ->with(['driver', 'vehicle'])
            // Offene immer zeigen; geschlossene nur im globalen Zeitraum.
            ->where(function ($query) use ($from, $to): void {
                $query->where('status', PassengerShiftSettlement::STATUS_OPEN)
                    ->orWhereBetween('shift_date', [$from->toDateString(), $to->toDateString()]);
            })
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('shift_date')
            ->paginate(25)
            ->withQueryString();

        // Kassenbuch-Übergabe (Issue #74): nur mit Kassenmodul + Kassenrecht.
        $cashEnabled = app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.kasse')
            && Gate::allows(\App\Enums\User\Permission::CashManage->value);

        return view('passenger.settlements.index', [
            'settlements' => $settlements,
            'openCount' => PassengerShiftSettlement::query()->where('status', PassengerShiftSettlement::STATUS_OPEN)->count(),
            'cashRegisters' => $cashEnabled
                ? \App\Models\CashRegister::query()->where('active', true)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'canPostCash' => $cashEnabled,
        ]);
    }

    public function create(): View {
        Gate::authorize('settle', PassengerShiftSettlement::class);
        $this->passengerOrganization();

        return view('passenger.settlements._form_dialog', [
            'settlement' => null,
            'drivers' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
            'vehicles' => Vehicle::query()->orderBy('license_plate')->get(),
        ]);
    }

    public function edit(PassengerShiftSettlement $settlement): View {
        Gate::authorize('settle', $settlement);
        $this->assertInOrganization($settlement->organization_id);

        return view('passenger.settlements._form_dialog', [
            'settlement' => $settlement,
            'drivers' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
            'vehicles' => Vehicle::query()->orderBy('license_plate')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('settle', PassengerShiftSettlement::class);
        $organization = $this->passengerOrganization();

        $validated = $this->validateSettlement($request);
        $exists = PassengerShiftSettlement::query()
            ->where('driver_user_id', $validated['driver_user_id'])
            ->whereDate('shift_date', $validated['shift_date'])
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['shift_date' => (string) __('passenger.error.settlement_exists')]);
        }

        $settlement = PassengerShiftSettlement::query()->create([
            'organization_id' => $organization->id,
            ...$validated,
        ]);
        $settlement->forceFill(['difference' => $settlement->computeDifference()])->save();
        $settlement->audit('passenger.settlement_created', []);

        return redirect()->route('passenger-settlements.index')->with('status', (string) __('passenger.flash.settlement_saved'));
    }

    public function update(Request $request, PassengerShiftSettlement $settlement): RedirectResponse {
        Gate::authorize('settle', $settlement);
        $this->assertInOrganization($settlement->organization_id);
        if ($settlement->status !== PassengerShiftSettlement::STATUS_OPEN) {
            throw ValidationException::withMessages(['status' => (string) __('passenger.error.settlement_closed')]);
        }

        $validated = $this->validateSettlement($request);
        unset($validated['driver_user_id'], $validated['shift_date']); // Schichtbezug unveränderlich
        $settlement->update($validated);
        $settlement->forceFill(['difference' => $settlement->computeDifference()])->save();
        $settlement->audit('passenger.settlement_updated', []);

        return redirect()->route('passenger-settlements.index')->with('status', (string) __('passenger.flash.settlement_saved'));
    }

    /**
     * Abschluss: glatt → ausgeglichen; Differenz nur mit Begründung als
     * „strittig" schließbar — nie stillschweigend (Konzept §8).
     */
    public function close(Request $request, PassengerShiftSettlement $settlement): RedirectResponse {
        Gate::authorize('settle', $settlement);
        $this->assertInOrganization($settlement->organization_id);
        if ($settlement->status !== PassengerShiftSettlement::STATUS_OPEN) {
            throw ValidationException::withMessages(['status' => (string) __('passenger.error.settlement_closed')]);
        }

        $validated = $request->validate(['difference_reason' => ['nullable', 'string', 'max:1000']]);
        $difference = $settlement->computeDifference();
        $balanced = bccomp($difference, '0', 2) === 0;
        if (! $balanced && trim((string) ($validated['difference_reason'] ?? '')) === '') {
            throw ValidationException::withMessages(['difference_reason' => (string) __('passenger.error.difference_reason_required')]);
        }

        $settlement->forceFill([
            'status' => $balanced ? PassengerShiftSettlement::STATUS_BALANCED : PassengerShiftSettlement::STATUS_DISPUTED,
            'difference' => $difference,
            'difference_reason' => trim((string) ($validated['difference_reason'] ?? '')) ?: null,
            'closed_by' => ($request->user() ?? abort(401))->id,
            'closed_at' => now(),
        ])->save();
        $settlement->audit('passenger.settlement_closed', ['status' => $settlement->status, 'difference' => $difference]);

        return redirect()->route('passenger-settlements.index')->with('status', (string) __('passenger.flash.settlement_closed'));
    }

    /**
     * Barumsatz einer abgeschlossenen Abrechnung ins Kassenbuch übernehmen
     * (Issue #74): genau eine Einnahme-Buchung je Abrechnung, rückverlinkt
     * über `cash_entry_id`. Der CashBookService bleibt die einzige
     * Schreibstelle (GoBD-Hash-Kette, Tagesabschluss-Sperre).
     */
    public function postCashEntry(Request $request, PassengerShiftSettlement $settlement): RedirectResponse {
        Gate::authorize('settle', $settlement);
        $this->assertInOrganization($settlement->organization_id);
        abort_unless(app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.kasse'), 404);
        Gate::authorize(\App\Enums\User\Permission::CashManage->value);

        if ($settlement->status === PassengerShiftSettlement::STATUS_OPEN) {
            throw ValidationException::withMessages(['status' => (string) __('passenger.error.settlement_not_closed')]);
        }
        if ($settlement->cash_entry_id !== null) {
            throw ValidationException::withMessages(['status' => (string) __('passenger.error.cash_already_posted')]);
        }
        if (bccomp((string) $settlement->cash_total, '0', 2) <= 0) {
            throw ValidationException::withMessages(['status' => (string) __('passenger.error.cash_nothing_to_post')]);
        }

        $request->merge(['cash_register_id' => Sqid::decodeOrNumeric(\App\Models\CashRegister::class, $request->input('cash_register_id'))]);
        $validated = $request->validate([
            'cash_register_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('cash_registers')],
        ]);
        $register = \App\Models\CashRegister::query()->findOrFail((int) $validated['cash_register_id']);
        $actor = $request->user() ?? abort(401);

        try {
            $entry = app(\App\Services\Finance\CashBookService::class)->record($register, [
                'booked_on' => $settlement->shift_date->toDateString(),
                'direction' => \App\Models\CashEntry::DIRECTION_IN,
                'amount' => (string) $settlement->cash_total,
                'purpose' => (string) __('passenger.cash.purpose', [
                    'driver' => (string) ($settlement->driver->name ?? '—'),
                    'date' => \App\Support\CarbonFmt::fdate($settlement->shift_date),
                ]),
                'counterparty' => $settlement->driver->name ?? null,
                'created_by' => $actor->id,
            ]);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['cash_register_id' => $exception->getMessage()]);
        }

        $settlement->forceFill(['cash_entry_id' => $entry->id])->save();
        $settlement->audit('passenger.settlement_cash_posted', [
            'cash_entry_id' => $entry->id,
            'register_id' => $register->id,
            'amount' => (string) $settlement->cash_total,
        ]);

        return redirect()->route('passenger-settlements.index')->with('status', (string) __('passenger.flash.cash_posted'));
    }

    /** @return array<string, mixed> */
    private function validateSettlement(Request $request): array {
        $request->merge([
            'driver_user_id' => Sqid::decodeOrNumeric(User::class, $request->input('driver_user_id')),
            'vehicle_id' => Sqid::decodeOrNumeric(Vehicle::class, $request->input('vehicle_id')),
        ]);

        return $request->validate([
            'driver_user_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('users')],
            'vehicle_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('vehicles')],
            'shift_date' => ['required', 'date'],
            'meter_total' => ['required', 'numeric', 'min:0'],
            'cash_total' => ['nullable', 'numeric', 'min:0'],
            'card_total' => ['nullable', 'numeric', 'min:0'],
            'voucher_total' => ['nullable', 'numeric', 'min:0'],
            'invoice_total' => ['nullable', 'numeric', 'min:0'],
            'mediator_total' => ['nullable', 'numeric', 'min:0'],
            'tip_total' => ['nullable', 'numeric', 'min:0'],
            'cancelled_total' => ['nullable', 'numeric', 'min:0'],
        ]);
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
