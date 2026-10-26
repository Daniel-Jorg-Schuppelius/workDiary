<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HousingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Rental;

use App\Enums\Asset\AssetClass;
use App\Enums\Rental\{RentalMode, RentalTenancyStatus};
use App\Exceptions\{AssetNotUsableException, RentalConflictException};
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Rental\{RentalCase, RentalProfile, RentalRateCard, RentalReservation, RentalTenancy};
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Asset\AssetService;
use App\Services\Rental\{RentalAvailabilityService, RentalTenancyService};
use App\Support\{ErrorText, Sqid};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Wohnungen, Belegung und Mietverhältnisse des Branchenprofils Vermieter; Rechte wie der Verleih. */
class HousingController extends Controller {
    use ResolvesGlobalDateRange;

    public function index(Request $request, RentalAvailabilityService $availability): View {
        Gate::authorize('viewAny', RentalCase::class);
        $request->validate(['q' => ['nullable', 'string', 'max:255']]);
        [$from, $to] = $this->globalDateRangeBounds();
        $profiles = RentalProfile::query()->housing()->with('asset')
            ->when($request->filled('q'), fn ($q) => $q->whereHas('asset', fn ($a) => $a->whereLikeEscaped('name', $request->string('q')->toString())))
            ->orderBy('id')->paginate(25)->withQueryString();
        $assetIds = $profiles->pluck('asset_id');
        $tenancies = RentalTenancy::query()->with(['asset', 'customer'])
            ->whereIn('asset_id', $assetIds)
            ->where(fn ($q) => $q->where('status', RentalTenancyStatus::Requested->value)->orWhere(fn ($active) => $active->overlapping($from, $to)))
            ->orderBy('starts_on')->get();
        $reservations = RentalReservation::query()->active()->with('rentalCase.customer')->whereIn('asset_id', $assetIds)
            ->overlapping(Carbon::instance($from), Carbon::instance($to), 720)->orderBy('starts_at')->get()
            ->filter(fn (RentalReservation $r) => $r->overlapsWindow(Carbon::instance($from), Carbon::instance($to)));
        $available = [];
        foreach ($profiles as $profile) {
            $available[$profile->id] = $profile->asset !== null
                && $availability->isAvailable($profile->asset, Carbon::instance($from), Carbon::instance($to));
        }

        return view('rental.housing.index', compact('profiles', 'tenancies', 'reservations', 'available', 'from', 'to'));
    }

    public function create(): View {
        Gate::authorize('create', RentalCase::class);

        return $this->form(new RentalProfile);
    }

    public function edit(RentalProfile $profile): View {
        Gate::authorize('create', RentalCase::class);
        abort_unless($profile->isHousing(), 404);

        return $this->form($profile->load('asset'));
    }

    private function form(RentalProfile $profile): View {
        return view('rental.housing._form_dialog', [
            'profile' => $profile,
            'rateCards' => RentalRateCard::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AssetService $assets): RedirectResponse {
        Gate::authorize('create', RentalCase::class);
        $data = $this->validated($request);
        $actor = $this->authUser();
        DB::transaction(function () use ($data, $actor, $assets): void {
            $asset = $assets->create($actor, [
                'name' => $data['name'], 'location_text' => $data['location_text'] ?? null,
                'asset_class' => AssetClass::Other->value,
            ]);
            unset($data['name'], $data['location_text']);
            RentalProfile::query()->create($data + ['organization_id' => $asset->organization_id, 'asset_id' => $asset->id]);
        });

        return redirect()->toList('rental.housing.index')->with('status', __('housing.saved'));
    }

    public function update(Request $request, RentalProfile $profile, AssetService $assets): RedirectResponse {
        Gate::authorize('create', RentalCase::class);
        abort_unless($profile->isHousing(), 404);
        $data = $this->validated($request);
        $actor = $this->authUser();
        DB::transaction(function () use ($profile, $data, $actor, $assets): void {
            $asset = $profile->asset ?? abort(404);
            $assets->update($asset, $actor, ['name' => $data['name'], 'location_text' => $data['location_text'] ?? null]);
            unset($data['name'], $data['location_text']);
            $profile->fill($data)->save();
        });

        return redirect()->toList('rental.housing.index')->with('status', __('housing.saved'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        if ($request->filled('default_rate_card_id')) {
            $request->merge(['default_rate_card_id' => Sqid::decodeOrNumeric(RentalRateCard::class, $request->input('default_rate_card_id'))]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'rental_mode' => ['required', Rule::enum(RentalMode::class)],
            'public_description' => ['nullable', 'string', 'max:4000'],
            'is_rentable' => ['required', 'boolean'], 'portal_bookable' => ['required', 'boolean'],
            'max_guests' => ['required', 'integer', 'between:1,100'],
            'minimum_nights' => ['required', 'integer', 'between:1,365'],
            'check_in_time' => ['required', 'date_format:H:i'], 'check_out_time' => ['required', 'date_format:H:i'],
            'rent_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'ancillary_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'default_rate_card_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('rental_rate_cards')],
            'buffer_after_hours' => ['required', 'integer', 'between:0,720'],
        ]);
    }

    public function tenancyCreate(): View {
        Gate::authorize('create', RentalCase::class);

        return $this->tenancyForm(new RentalTenancy);
    }

    public function tenancyEdit(RentalTenancy $tenancy): View {
        Gate::authorize('create', RentalCase::class);

        return $this->tenancyForm($tenancy);
    }

    private function tenancyForm(RentalTenancy $tenancy): View {
        return view('rental.housing._tenancy_dialog', [
            'tenancy' => $tenancy,
            'profiles' => RentalProfile::query()->longTermRentable()->with('asset')->get(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function tenancyStore(Request $request, RentalTenancyService $service): RedirectResponse {
        Gate::authorize('create', RentalCase::class);
        $request->merge([
            'profile_id' => Sqid::decodeOrNumeric(RentalProfile::class, $request->input('profile_id')),
            'customer_id' => Sqid::decodeOrNumeric(Customer::class, $request->input('customer_id')),
        ]);
        $data = $request->validate($this->tenancyRules() + [
            'profile_id' => ['required', 'integer', new ExistsInCurrentOrganization('rental_profiles')],
            'customer_id' => ['required', 'integer', new ExistsInCurrentOrganization('customers')],
        ]);
        try {
            $service->create(RentalProfile::query()->whereKey($data['profile_id'])->firstOrFail(), Customer::query()->whereKey($data['customer_id'])->firstOrFail(), $this->authUser(), $data);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['starts_on' => ErrorText::for($e)]);
        }

        return redirect()->toList('rental.housing.index')->with('status', __('housing.tenancy_saved'));
    }

    public function tenancyUpdate(Request $request, RentalTenancy $tenancy, RentalTenancyService $service): RedirectResponse {
        Gate::authorize('create', RentalCase::class);
        $data = $request->validate($this->tenancyRules() + ['status' => ['required', Rule::enum(RentalTenancyStatus::class)->except(RentalTenancyStatus::Requested)]]);
        try {
            $service->update($tenancy, RentalTenancyStatus::from((string) $data['status']), $data);
        } catch (AssetNotUsableException|RentalConflictException $e) {
            return back()->withInput()->withErrors(['starts_on' => ErrorText::for($e)]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['status' => ErrorText::for($e)]);
        }

        return redirect()->toList('rental.housing.index')->with('status', __('housing.tenancy_saved'));
    }

    /** @return array<string, list<string>> */
    private function tenancyRules(): array {
        return [
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'rent_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'ancillary_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
