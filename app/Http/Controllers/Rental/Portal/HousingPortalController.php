<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HousingPortalController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Rental\Portal;

use App\Enums\Rental\RentalMode;
use App\Http\Controllers\Concerns\ResolvesPortalUser;
use App\Http\Controllers\Controller;
use App\Models\Rental\{RentalProfile, RentalRequest, RentalTenancy};
use App\Services\Rental\{RentalAvailabilityService, RentalBillingService, RentalRequestService, RentalTenancyService};
use App\Support\{ErrorText, Sqid, Tz};
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

/** Wohnungen im Kundenportal: Ferienaufenthalt oder Dauervermietung anfragen (Branchenprofil Vermieter). */
class HousingPortalController extends Controller {
    use ResolvesPortalUser;

    public function index(Request $request, RentalRequestService $requests, RentalBillingService $billing): View {
        $user = $this->portalUser();
        $customer = $user->customer ?? abort(404);
        $data = $request->validate([
            'arrival' => ['nullable', 'required_with:departure', 'date_format:Y-m-d'],
            'departure' => ['nullable', 'required_with:arrival', 'date_format:Y-m-d', 'after:arrival'],
        ]);
        $profiles = RentalProfile::query()->where('organization_id', $customer->organization_id)->portalBookable()->housing()
            ->whereHas('asset')->with(['asset', 'defaultRateCard'])->orderBy('id')->paginate(12)->withQueryString();
        $availability = [];
        $prices = [];
        if (! empty($data['arrival']) && ! empty($data['departure'])) {
            foreach ($profiles as $profile) {
                if ($profile->rental_mode?->allowsHoliday() !== true || $profile->asset === null) {
                    continue;
                }
                [$from, $to] = $this->holidayRange($profile, $data['arrival'], $data['departure']);
                $availability[$profile->id] = $requests->isRoughlyAvailable($profile->asset, $from, $to);
                $prices[$profile->id] = $billing->estimate($profile, $from, $to);
            }
        }

        return view('customer.housing.index', [
            'profiles' => $profiles,
            'availability' => $availability, 'prices' => $prices,
            'direct' => $user->organization !== null && $requests->directBookingEnabled($user->organization),
            'tenancies' => RentalTenancy::query()->withoutGlobalScopes()->where('organization_id', $user->organization_id)
                ->where('customer_id', $customer->id)->with('asset:id,name')->orderByDesc('id')->paginate(10, ['*'], 'tenancies_page')->withQueryString(),
            'requests' => RentalRequest::query()->withoutGlobalScopes()->where('organization_id', $user->organization_id)
                ->where('customer_id', $customer->id)->whereHas('asset.rentalProfile', fn ($q) => $q->housing())
                ->with('asset:id,name')->orderByDesc('id')->paginate(10, ['*'], 'holidays_page')->withQueryString(),
        ]);
    }

    public function store(Request $request, RentalRequestService $requests, RentalTenancyService $tenancies, RentalAvailabilityService $availability): RedirectResponse {
        $user = $this->portalUser();
        $customer = $user->customer ?? abort(404);
        $today = CarbonImmutable::now(Tz::ofOrganization($user->organization ?? abort(404)))->toDateString();
        $data = $request->validate([
            'profile_id' => ['required', 'string', 'max:80'],
            'mode' => ['required', Rule::in([RentalMode::LongTerm->value, RentalMode::Holiday->value])],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:' . $today],
            'ends_on' => ['nullable', 'required_if:mode,holiday', 'date_format:Y-m-d', 'after:starts_on'],
            'guests' => ['required_if:mode,holiday', 'nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
            'booking' => ['nullable', 'in:direct'],
        ]);
        $profileId = Sqid::decodeOrNumeric(RentalProfile::class, $data['profile_id']);
        $profile = $requests->bookableProfiles($customer)->first(fn (RentalProfile $p) => $p->id === $profileId && $p->isHousing()) ?? abort(404);
        $longTerm = $data['mode'] === RentalMode::LongTerm->value;
        if (! ($longTerm ? $profile->rental_mode?->allowsLongTerm() : $profile->rental_mode?->allowsHoliday())) {
            throw ValidationException::withMessages(['mode' => __('housing.unavailable')]);
        }
        try {
            if ($longTerm) {
                $tenancies->create($profile, $customer, $user, $data, true);
            } else {
                $nights = (int) CarbonImmutable::parse($data['starts_on'])->diffInDays(CarbonImmutable::parse($data['ends_on']));
                if ($data['guests'] > $profile->max_guests || $nights < $profile->minimum_nights) {
                    throw ValidationException::withMessages(['guests' => __('housing.capacity_error')]);
                }
                [$from, $to] = $this->holidayRange($profile, $data['starts_on'], $data['ends_on']);
                $asset = $profile->asset ?? abort(404);
                $availability->assertAvailable($asset, $from, $to);
                if (($data['booking'] ?? null) === 'direct') {
                    $requests->bookFromPortal($customer, $user, $asset, $from, $to, $data['note'] ?? null, (int) $data['guests']);
                } else {
                    $requests->requestFromPortal($customer, $user, $asset, null, $from, $to, $data['note'] ?? null, (int) $data['guests']);
                }
            }
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['starts_on' => ErrorText::for($e)]);
        }

        return redirect()->route('customer.housing.index')->with('success', __('housing.request_saved'));
    }

    public function withdraw(RentalTenancy $tenancy, RentalTenancyService $tenancies): RedirectResponse {
        $user = $this->portalUser();
        abort_unless((int) $tenancy->organization_id === (int) $user->organization_id && (int) $tenancy->customer_id === (int) $user->customer_id, 404);
        try {
            $tenancies->withdraw($tenancy);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => ErrorText::for($e)]);
        }

        return back()->with('success', __('housing.request_withdrawn'));
    }

    /** @return array{Carbon, Carbon} */
    private function holidayRange(RentalProfile $profile, string $arrival, string $departure): array {
        $zone = Tz::ofOrganization($profile->organization ?? abort(404));
        return [
            Carbon::instance(CarbonImmutable::parse($arrival . ' ' . $profile->check_in_time, $zone)->utc()),
            Carbon::instance(CarbonImmutable::parse($departure . ' ' . $profile->check_out_time, $zone)->utc()),
        ];
    }
}
